<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Abschrift (Warenverlust). Wird in der POS-App erfasst und ist die
 * Datenquelle fuer die Abschriften-/Tagesberichte.
 */
class DepreciationEntry extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = [
        'tenant_id',
        'station_id',
        'user_id',
        'ean',
        'tms_no',
        'article_description',
        'article_id',
        'quantity',
        'depreciation_reason_id',
        'purchasing_price',
        'selling_price',
        'source',
        'mhd_id',
        'note',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'tms_no' => 'integer',
            'purchasing_price' => 'decimal:3',
            'selling_price' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    // ── Beziehungen ──────────────────────────────────

    public function station(): BelongsTo
    {
        return $this->belongsTo(GasStation::class, 'station_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(DepreciationReason::class, 'depreciation_reason_id');
    }

    // ── Berechnete Werte ─────────────────────────────

    /** Gesamt-Einkaufswert dieser Position */
    public function getTotalPurchasingAttribute(): float
    {
        return (float) ($this->purchasing_price ?? 0) * $this->quantity;
    }

    /** Gesamt-Verkaufswert dieser Position */
    public function getTotalSellingAttribute(): float
    {
        return (float) ($this->selling_price ?? 0) * $this->quantity;
    }
}
