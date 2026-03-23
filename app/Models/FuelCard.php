<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tankkarte/Teilnehmer fuer Firmenkunden.
 * Unique pro Tankstelle (card_number + gas_station_id).
 */
class FuelCard extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory, HasUuids, SoftDeletes, Auditable;

    protected $fillable = [
        'gas_station_id',
        'corporate_customer_id',
        'card_number',
        'driver_name',
        'license_plate',
        'notes',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'card_number' => 'integer',
            'imported_at' => 'datetime',
        ];
    }

    // ── Relationships ──

    public function gasStation(): BelongsTo
    {
        return $this->belongsTo(GasStation::class);
    }

    public function corporateCustomer(): BelongsTo
    {
        return $this->belongsTo(CorporateCustomer::class);
    }

    // ── Scopes ──

    public function scopeForStation($query, string $gasStationId)
    {
        return $query->where('gas_station_id', $gasStationId);
    }
}
