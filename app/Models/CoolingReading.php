<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine gespeicherte Temperaturmessung (Historie).
 */
class CoolingReading extends Model
{
    protected $fillable = [
        'cooling_unit_id', 'tenant_id', 'station_id',
        'value', 'humidity', 'measured_at', 'received_at', 'mobile_idx',
        'out_of_range', 'disconnected',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'humidity' => 'decimal:2',
            'measured_at' => 'datetime',
            'received_at' => 'datetime',
            'out_of_range' => 'boolean',
            'disconnected' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(CoolingUnit::class, 'cooling_unit_id');
    }
}
