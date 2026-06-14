<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Article;
use App\Models\ArticleEan;
use App\Models\DepreciationEntry;
use App\Models\DepreciationReason;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Abschriften erfassen (POS-App).
 * Einzel- und Batch-Abschreibung; EK/VK werden — falls nicht mitgegeben —
 * automatisch aus dem Artikel (ueber EAN) als Snapshot uebernommen.
 */
class DepreciationController extends ApiController
{
    /**
     * GET /api/v1/depreciation-reasons?device_token=...
     * Aktive Abschreibgruende (global + mandantenspezifisch).
     */
    public function reasons(Request $request): JsonResponse
    {
        $device = $this->findDevice($request->query('device_token', ''));
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $reasons = DepreciationReason::query()
            ->where('status', true)
            ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $device->tenant_id))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'is_default'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'is_default' => (bool) $r->is_default,
            ])
            ->values();

        return $this->success(['reasons' => $reasons]);
    }

    /**
     * POST /api/v1/depreciations
     * Abschriften speichern. Eine oder mehrere Positionen (Batch).
     *
     * Body:
     *   device_token, source (batch|single),
     *   depreciation_reason_id  (gemeinsamer Grund, optional je Position ueberschreibbar),
     *   items: [{ ean?, tms_no?, article_description, quantity, depreciation_reason_id?,
     *             purchasing_price?, selling_price? }]
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'device_token' => 'required|string',
            'source' => 'nullable|in:batch,single',
            'depreciation_reason_id' => 'nullable|integer',
            'items' => 'required|array|min:1',
            'items.*.article_description' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.ean' => 'nullable|string',
            'items.*.tms_no' => 'nullable|integer',
            'items.*.depreciation_reason_id' => 'nullable|integer',
            'items.*.purchasing_price' => 'nullable|numeric',
            'items.*.selling_price' => 'nullable|numeric',
        ]);

        $device = $this->findDevice($request->device_token);
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $source = $request->input('source', count($request->items) > 1 ? 'batch' : 'single');
        $commonReason = $request->input('depreciation_reason_id');

        $created = DB::transaction(function () use ($request, $device, $source, $commonReason) {
            $entries = [];
            foreach ($request->items as $item) {
                $reasonId = $item['depreciation_reason_id'] ?? $commonReason;

                // EK/VK aus dem Artikel ergaenzen, falls nicht mitgegeben
                $ek = $item['purchasing_price'] ?? null;
                $vk = $item['selling_price'] ?? null;
                $articleId = null;
                if (($ek === null || $vk === null) && ! empty($item['ean'])) {
                    $article = $this->findArticleByEan($item['ean'], $device->station_id);
                    if ($article) {
                        $articleId = $article->id;
                        $ek ??= $article->purchasing_price;
                        $vk ??= $article->selling_price;
                    }
                }

                $entries[] = DepreciationEntry::create([
                    'tenant_id' => $device->tenant_id,
                    'station_id' => $device->station_id,
                    'user_id' => $request->user()?->id,
                    'ean' => $item['ean'] ?? null,
                    'tms_no' => $item['tms_no'] ?? null,
                    'article_description' => $item['article_description'],
                    'article_id' => $articleId,
                    'quantity' => $item['quantity'],
                    'depreciation_reason_id' => $reasonId,
                    'purchasing_price' => $ek,
                    'selling_price' => $vk,
                    'source' => $source,
                    'recorded_at' => now(),
                ]);
            }

            return $entries;
        });

        return $this->success(['count' => count($created)], count($created) . ' Abschreibung(en) gespeichert.', 201);
    }

    /**
     * Artikel ueber EAN finden (fuer EK/VK-Snapshot).
     */
    private function findArticleByEan(string $ean, string $stationId): ?Article
    {
        $eanRow = ArticleEan::where('gas_station_id', $stationId)->where('ean', $ean)->first();
        if (! $eanRow) {
            return null;
        }

        return Article::where('gas_station_id', $stationId)
            ->where('article_number', $eanRow->article_number)
            ->first();
    }

    private function findDevice(string $plainToken): ?Device
    {
        return Device::findByPlainToken($plainToken);
    }
}
