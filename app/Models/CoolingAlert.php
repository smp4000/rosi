<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stoerung/Alarm eines Kuehlmoebels (zu warm/kalt, offline, Batterie).
 */
class CoolingAlert extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'cooling_unit_id', 'tenant_id', 'station_id',
        'type', 'value', 'threshold', 'status',
        'started_at', 'ended_at',
        'acknowledged_at', 'acknowledged_by', 'note', 'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'threshold' => 'decimal:2',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    public const TYPE_LABELS = [
        'high' => 'Zu warm',
        'low' => 'Zu kalt',
        'offline' => 'Sensor offline',
        'lowbat' => 'Batterie schwach',
        'hum_high' => 'Feuchte zu hoch',
        'hum_low' => 'Feuchte zu niedrig',
    ];

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(CoolingUnit::class, 'cooling_unit_id');
    }
}
