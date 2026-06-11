<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Article;
use App\Models\ArticleEan;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ArticleController extends ApiController
{
    /**
     * GET /api/v1/articles/search?q={query}&device_token={token}
     *
     * Artikelsuche per EAN, Artikelnummer oder Textsuche.
     * Gibt Artikel mit EANs und Artikelgruppe zurueck.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:255',
            'device_token' => 'required|string',
        ]);

        $query = trim($request->q);

        // Geraet finden → Station ermitteln
        $device = $this->findDevice($request->device_token);
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $stationId = $device->station_id;

        // Suchstrategie: EAN → Artikelnummer → Textsuche
        $articles = collect();

        // 1. Suche per EAN
        $ean = ArticleEan::where('gas_station_id', $stationId)
            ->where('ean', $query)
            ->first();

        if ($ean) {
            $article = Article::where('gas_station_id', $stationId)
                ->where('article_number', $ean->article_number)
                ->with(['eans', 'articleGroup'])
                ->first();

            if ($article) {
                $articles->push($article);
            }
        }

        // 2. Suche per Artikelnummer (falls nichts per EAN gefunden)
        if ($articles->isEmpty() && is_numeric($query)) {
            $article = Article::where('gas_station_id', $stationId)
                ->where('article_number', $query)
                ->with(['eans', 'articleGroup'])
                ->first();

            if ($article) {
                $articles->push($article);
            }
        }

        // 3. Textsuche im Artikelnamen (falls immer noch nichts)
        if ($articles->isEmpty()) {
            $articles = Article::where('gas_station_id', $stationId)
                ->where('name', 'LIKE', "%{$query}%")
                ->with(['eans', 'articleGroup'])
                ->orderBy('name')
                ->limit(20)
                ->get();
        }

        if ($articles->isEmpty()) {
            return $this->error('Kein Artikel gefunden.', 404);
        }

        $data = $articles->map(fn (Article $article) => $this->formatArticle($article));

        return $this->success($data->toArray(), $articles->count() . ' Artikel gefunden.');
    }

    /**
     * Artikel fuer API-Response formatieren.
     */
    private function formatArticle(Article $article): array
    {
        return [
            'article_number' => $article->article_number,
            'name' => $article->name,
            'unit' => $article->unit,
            'selling_price' => (float) $article->selling_price,
            'purchasing_price' => (float) $article->purchasing_price,
            'current_stock' => $article->current_stock,
            'min_stock' => $article->min_stock,
            'max_stock' => $article->max_stock,
            'status' => $article->status,
            'is_locked' => (bool) $article->is_locked,
            'supplier_name' => $article->supplier_name,
            'supplier_number' => $article->supplier_number,
            'order_number' => $article->order_number,
            'article_group' => $article->articleGroup ? [
                'business_area' => $article->articleGroup->business_area,
                'product_group' => $article->articleGroup->product_group,
                'vat_rate' => (float) $article->articleGroup->vat_rate,
                'youth_protection' => $article->articleGroup->youth_protection,
            ] : null,
            'eans' => $article->eans->map(fn (ArticleEan $ean) => [
                'ean' => $ean->ean,
                'selling_price' => (float) $ean->selling_price,
                'sales_packaging' => $ean->sales_packaging,
            ])->toArray(),
        ];
    }

    /**
     * Geraet anhand des Device-Tokens finden.
     */
    private function findDevice(string $plainToken): ?Device
    {
        return Device::findByPlainToken($plainToken);
    }
}
