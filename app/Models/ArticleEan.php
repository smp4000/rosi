<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Artikel-EAN/GTIN-Codes.
 * Ein Artikel kann mehrere EAN-Codes haben (verschiedene Gebinde, Lieferanten).
 */
class ArticleEan extends \Illuminate\Database\Eloquent\Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'gas_station_id',
        'article_number',
        'ean',
        'selling_price',
        'base_quantity',
        'reference_quantity',
        'base_unit',
        'quantity_factor',
        'sales_packaging',
        'is_locked',
        'csv_printed_at',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
            'base_quantity' => 'decimal:3',
            'reference_quantity' => 'decimal:3',
            'is_locked' => 'boolean',
            'csv_printed_at' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    public function gasStation(): BelongsTo
    {
        return $this->belongsTo(GasStation::class);
    }

    /**
     * Zugehoeriger Artikel (ueber article_number + gas_station_id).
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_number', 'article_number')
            ->where('gas_station_id', $this->gas_station_id);
    }
}
