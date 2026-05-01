<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tresor-Einlage / Safebag innerhalb einer Schichtabrechnung.
 *
 * Fuer jede Einlage wird ein DYMO-Etikett gedruckt mit:
 * Station, Mitarbeiter, Datum, Uhrzeit, Betrag und Barcode.
 */
class ShiftSettlementSafeDeposit extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'shift_settlement_id',
        'amount',
        'has_coins',
        'deposited_at',
        'label_printed',
        'barcode',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'has_coins' => 'boolean',
            'deposited_at' => 'datetime',
            'label_printed' => 'boolean',
        ];
    }

    // --- Beziehungen ---

    /** Zugehoerige Schichtabrechnung */
    public function shiftSettlement(): BelongsTo
    {
        return $this->belongsTo(ShiftSettlement::class);
    }

    // --- Accessors ---

    /** Formattierter Betrag */
    public function getFormattedAmountAttribute(): string
    {
        return number_format((float) $this->amount, 2, ',', '.') . ' €';
    }
}
