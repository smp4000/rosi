<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Warenruecknahme / Storno innerhalb einer Schichtabrechnung.
 * Der zugehoerige Bon wird fotografiert.
 */
class ShiftSettlementReturn extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'shift_settlement_id',
        'receipt_number',
        'time',
        'product',
        'reason',
        'amount',
        'photo',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
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
