<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Device;
use App\Models\Kiosk\Article;
use App\Models\Kiosk\Delivery;
use App\Models\Kiosk\DeliveryItem;
use App\Models\Kiosk\InventoryItem;
use App\Models\Kiosk\InventoryRun;
use App\Models\Kiosk\RemiItem;
use App\Models\Kiosk\RemiPackage;
use App\Services\Kiosk\EanInspectorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Kiosk-API: Lieferung, Remission, Inventur, Artikelsuche.
 */
class KioskController extends ApiController
{
    public function __construct(
        private readonly EanInspectorService $eanInspector,
    ) {}

    // ── Health ───────────────────────────────────────────

    public function ping(): JsonResponse
    {
        return $this->success([
            'service' => 'rosi-kiosk-api',
            'version' => '1.0',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    // ── Artikel-Lookup ───────────────────────────────────

    /**
     * GET /api/v1/kiosk/articles/lookup?ean=...&device_token=...
     */
    public function lookupByEan(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        if (! $tenant) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $ean = preg_replace('/\D/', '', (string) $request->query('ean', ''));
        if (strlen($ean) !== 13) {
            return $this->error('Ungueltige EAN.', 422);
        }

        $articles = Article::where('tenant_id', $tenant)
            ->where('ean', $ean)
            ->with('issues')
            ->get();

        return $this->success([
            'ean' => $ean,
            'count' => $articles->count(),
            'articles' => $articles->map(fn ($a) => $this->formatArticle($a))->all(),
            'eanInfo' => $this->eanInspector->inspect($ean),
        ]);
    }

    /**
     * GET /api/v1/kiosk/articles/by-objekt?objekt=...&supplier=PVG&device_token=...
     */
    public function lookupByObjekt(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        if (! $tenant) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $supplier = $request->query('supplier', 'PVG');
        $objekt = trim((string) $request->query('objekt', ''));
        if ($objekt === '') {
            return $this->error('Objektnummer fehlt.', 422);
        }

        $article = Article::where('tenant_id', $tenant)
            ->where('supplier', $supplier)
            ->where('objekt', $objekt)
            ->with('issues')
            ->first();

        if (! $article) {
            return $this->error('Artikel nicht gefunden.', 404);
        }

        return $this->success([
            'article' => $this->formatArticle($article),
            'ausgaben' => $article->issues->pluck('ausgabe')->sort()->values(),
        ]);
    }

    /**
     * POST /api/v1/kiosk/articles/upsert-pending
     * Body: ean, bezeichnung, weekday, device_token
     */
    public function upsertPending(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        if (! $tenant) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $data = $request->validate([
            'ean' => 'required|string|size:13',
            'bezeichnung' => 'required|string|max:255',
            'weekday' => 'nullable|integer|min:1|max:7',
        ]);

        $info = $this->eanInspector->inspect($data['ean']);

        // Objektnummer aus EAN ableiten (Stelle 4-8)
        $objekt = substr($data['ean'], 3, 5);

        $article = Article::firstOrCreate(
            ['tenant_id' => $tenant, 'supplier' => 'PVG', 'objekt' => $objekt],
            [
                'ean' => $data['ean'],
                'weekday' => $data['weekday'] ?? null,
                'bezeichnung' => $data['bezeichnung'],
                'aktueller_preis_brutto' => $info['preis_brutto'] ?? 0,
                'aktueller_preis_netto' => $info['preis_netto'] ?? 0,
                'mwst_satz' => $info['mwst_satz'] ?? 0,
                'is_pending' => true,
                'last_seen_at' => now(),
            ],
        );

        return $this->success([
            'article_id' => $article->id,
            'is_pending' => $article->is_pending,
            'article' => $this->formatArticle($article->fresh('issues')),
        ], $article->wasRecentlyCreated ? 'Artikel angelegt.' : 'Artikel existiert bereits.', 201);
    }

    // ── Lieferung ────────────────────────────────────────

    public function saveDelivery(Request $request): JsonResponse
    {
        return $this->saveBewegung($request, 'delivery');
    }

    public function saveRemission(Request $request): JsonResponse
    {
        return $this->saveBewegung($request, 'remission');
    }

    public function saveInventory(Request $request): JsonResponse
    {
        return $this->saveBewegung($request, 'inventory');
    }

    /**
     * Generischer Save-Handler fuer alle drei Bewegungs-Typen.
     */
    private function saveBewegung(Request $request, string $kind): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Nicht angemeldet.', 401);
        }
        $device = $this->findDevice($request->input('device_token', ''));
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $rules = [
            'mitarbeiter' => 'nullable|string|max:100',
            'station_id' => 'nullable|string',
            'notiz' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.article_id' => 'required|string',
            'items.*.menge' => 'required|integer',
            'items.*.ausgabe' => 'nullable|string|max:10',
            'items.*.vkp_brutto' => 'nullable|numeric',
            'items.*.mwst_satz' => 'nullable|numeric',
            'items.*.scanned_ean' => 'nullable|string|max:20',
        ];
        if ($kind === 'delivery') {
            $rules['lieferschein_nr'] = 'nullable|string|max:50';
            $rules['lieferschein_datum'] = 'nullable|date';
        } elseif ($kind === 'remission') {
            $rules['paket'] = 'nullable|string|max:50';
            $rules['paket_datum'] = 'nullable|date';
        } else {
            $rules['bezeichnung'] = 'nullable|string|max:255';
            $rules['modus'] = 'nullable|in:full,partial';
            $rules['stufe'] = 'nullable|integer';
        }
        $data = $request->validate($rules);

        return DB::transaction(function () use ($data, $kind, $user, $device, $request) {
            $shared = [
                'tenant_id' => $user->tenant_id,
                'gas_station_id' => $device->station_id,
                'user_id' => $user->id,
                'mitarbeiter' => $data['mitarbeiter'] ?? $user->name,
                'notiz' => $data['notiz'] ?? null,
                'status' => 'closed',
                'scanned_at' => now(),
            ];

            if ($kind === 'delivery') {
                $head = Delivery::create(array_merge($shared, [
                    'lieferschein_nr' => $data['lieferschein_nr'] ?? null,
                    'lieferschein_datum' => $data['lieferschein_datum'] ?? null,
                ]));
                $itemModel = DeliveryItem::class;
                $foreignKey = 'delivery_id';
                $negate = false;
                $responseKey = 'delivery_id';
            } elseif ($kind === 'remission') {
                $head = RemiPackage::create(array_merge($shared, [
                    'paket' => $data['paket'] ?? null,
                    'paket_datum' => $data['paket_datum'] ?? null,
                ]));
                $itemModel = RemiItem::class;
                $foreignKey = 'remi_package_id';
                $negate = true;
                $responseKey = 'remi_package_id';
            } else {
                $head = InventoryRun::create(array_merge($shared, [
                    'bezeichnung' => $data['bezeichnung'] ?? ('Inventur ' . now()->format('Y-m-d')),
                    'modus' => $data['modus'] ?? 'partial',
                    'stufe' => $data['stufe'] ?? 1,
                ]));
                $itemModel = InventoryItem::class;
                $foreignKey = 'inventory_run_id';
                $negate = false;
                $responseKey = 'inventory_run_id';
            }

            $count = 0;
            foreach ($data['items'] as $item) {
                $itemModel::create([
                    $foreignKey => $head->id,
                    'article_id' => $item['article_id'],
                    'ausgabe' => $item['ausgabe'] ?? null,
                    'menge' => $negate ? -1 * abs($item['menge']) : $item['menge'],
                    'einzelpreis_brutto' => $item['vkp_brutto'] ?? 0,
                    'mwst_satz' => $item['mwst_satz'] ?? 0,
                    'scanned_ean' => $item['scanned_ean'] ?? null,
                    'scanned_at' => now(),
                ]);
                $count++;
            }

            return $this->success([
                $responseKey => $head->id,
                'items_saved' => $count,
            ], 'Gespeichert.', 201);
        });
    }

    // ── Helpers ──────────────────────────────────────────

    private function resolveTenant(Request $request): ?string
    {
        $device = $this->findDevice($request->query('device_token', $request->input('device_token', '')));
        return $device?->tenant_id;
    }

    private function findDevice(string $plainToken): ?Device
    {
        return Device::findByPlainToken($plainToken);
    }

    private function formatArticle(Article $a): array
    {
        return [
            'id' => $a->id,
            'supplier' => $a->supplier,
            'objekt' => $a->objekt,
            'ean' => $a->ean,
            'weekday' => $a->weekday,
            'bezeichnung' => $a->bezeichnung,
            'aktueller_preis_netto' => (float) $a->aktueller_preis_netto,
            'aktueller_preis_brutto' => (float) $a->aktueller_preis_brutto,
            'mwst_satz' => (float) $a->mwst_satz,
            'ek' => $a->ek !== null ? (float) $a->ek : null,
            'is_pending' => (bool) $a->is_pending,
            'last_seen_at' => $a->last_seen_at?->toDateString(),
            'ausgaben' => $a->relationLoaded('issues')
                ? $a->issues->pluck('ausgabe')->sort()->values()
                : [],
        ];
    }
}
