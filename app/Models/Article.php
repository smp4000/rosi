<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tankstellen-Artikel (aus CSV-Import).
 * Tenant-Isolation ueber gas_station.tenant_id.
 */
class Article extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory, HasUuids, SoftDeletes, Auditable;

    protected $fillable = [
        'gas_station_id',
        'article_group_id',
        'article_group_description',
        'article_number',
        'name',
        'unit',
        'purchasing_price',
        'selling_price',
        'supplier_number',
        'supplier_name',
        'order_number',
        'quantity_factor',
        'min_stock',
        'max_stock',
        'current_stock',
        'modus',
        'status',
        'csv_printed_at',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'article_number' => 'integer',
            'purchasing_price' => 'decimal:3',
            'selling_price' => 'decimal:2',
            'min_stock' => 'integer',
            'max_stock' => 'integer',
            'current_stock' => 'integer',
            'modus' => 'integer',
            'csv_printed_at' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    // ── Relationships ──

    public function gasStation(): BelongsTo
    {
        return $this->belongsTo(GasStation::class);
    }

    public function articleGroup(): BelongsTo
    {
        return $this->belongsTo(ArticleGroup::class);
    }

    public function eans(): HasMany
    {
        return $this->hasMany(ArticleEan::class, 'article_number', 'article_number')
            ->where('article_eans.gas_station_id', $this->gas_station_id);
    }


    public function changes(): HasMany
    {
        return $this->hasMany(ArticleChange::class)->orderByDesc('created_at');
    }

    // ── Scopes ──

    public function scopeForStation($query, string $gasStationId)
    {
        return $query->where('gas_station_id', $gasStationId);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    // ── Helpers ──

    /**
     * Geschaeftsbereich ueber die Artikelgruppe.
     */
    public function getBusinessAreaAttribute(): ?string
    {
        return $this->articleGroup?->business_area;
    }
}
