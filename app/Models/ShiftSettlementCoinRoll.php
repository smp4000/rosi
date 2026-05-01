<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Muenzrollen-Bestand fuer eine Stueckelung innerhalb einer Schichtabrechnung.
 *
 * Stueckelungen:
 * - 200 Cent (2€-Rollen, 50,00€ pro Rolle)
 * - 100 Cent (1€-Rollen, 25,00€ pro Rolle)
 * - 50 Cent (50ct-Rollen, 20,00€ pro Rolle)
 * - 20 Cent (20ct-Rollen, 8,00€ pro Rolle)
 * - 10 Cent (10ct-Rollen, 4,00€ pro Rolle)
 * - 5 Cent (5ct-Rollen, 2,50€ pro Rolle)
 * - 2 Cent (2ct-Rollen, 1,00€ pro Rolle)
 * - 1 Cent (1ct-Rollen, 0,50€ pro Rolle)
 */
class ShiftSettlementCoinRoll extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'shift_settlement_id',
        'denomination',
        'roll_value',
        'count_start',
        'count_end',
        'value_start',
        'value_end',
        'value_used',
    ];

    protected function casts(): array
    {
        return [
            'denomination' => 'integer',
            'roll_value' => 'decimal:2',
            'count_start' => 'integer',
            'count_end' => 'integer',
            'value_start' => 'decimal:2',
            'value_end' => 'decimal:2',
            'value_used' => 'decimal:2',
        ];
    }

    /**
     * Beim Speichern automatisch Werte berechnen.
     */
    protected static function booted(): void
    {
        static::saving(function (self $model) {
            $model->value_start = $model->count_start * (float) $model->roll_value;
            $model->value_end = $model->count_end * (float) $model->roll_value;
            $model->value_used = $model->value_start - $model->value_end;
        });
    }

    // --- Beziehungen ---

    /** Zugehoerige Schichtabrechnung */
    public function shiftSettlement(): BelongsTo
    {
        return $this->belongsTo(ShiftSettlement::class);
    }

    // --- Accessors ---

    /** Verbrauchte Rollen (Anzahl) */
    public function getCountUsedAttribute(): int
    {
        return $this->count_start - $this->count_end;
    }

    /** Stueckelungs-Label (z.B. "2,00 € / 50,00 €") */
    public function getDenominationLabelAttribute(): string
    {
        $euro = number_format($this->denomination / 100, 2, ',', '');

        return $euro . ' € / ' . number_format((float) $this->roll_value, 2, ',', '') . ' €';
    }
}
