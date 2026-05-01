<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zaehlerstand (Waschanlage, Kaffee, Pfand, Hermes) innerhalb einer Schichtabrechnung.
 */
class ShiftSettlementCounter extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'shift_settlement_id',
        'counter_type',
        'counter_label',
        'value_start',
        'value_end',
        'value_used',
    ];

    protected function casts(): array
    {
        return [
            'value_start' => 'decimal:2',
            'value_end' => 'decimal:2',
            'value_used' => 'decimal:2',
        ];
    }

    /**
     * Beim Speichern automatisch Verbrauch berechnen.
     */
    protected static function booted(): void
    {
        static::saving(function (self $model) {
            $model->value_used = (float) $model->value_end - (float) $model->value_start;
        });
    }

    // --- Beziehungen ---

    /** Zugehoerige Schichtabrechnung */
    public function shiftSettlement(): BelongsTo
    {
        return $this->belongsTo(ShiftSettlement::class);
    }

    // --- Accessors ---

    /** Anzeigename des Zaehlers */
    public function getDisplayNameAttribute(): string
    {
        return match ($this->counter_type) {
            'waschanlage' => 'Waschanlage',
            'kaffee' => 'Kaffee (Dosen)',
            'pfand' => 'Pfand',
            'hermes' => 'Hermes Pakete',
            'custom' => $this->counter_label ?? 'Sonstiges',
            default => $this->counter_type,
        };
    }
}
