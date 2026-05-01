<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Antwort auf eine Prueffrage innerhalb einer Schichtabrechnung.
 */
class ShiftSettlementCheckAnswer extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'shift_settlement_id',
        'question_id',
        'answer_bool',
        'answer_text',
        'answer_number',
    ];

    protected function casts(): array
    {
        return [
            'answer_bool' => 'boolean',
            'answer_number' => 'decimal:2',
        ];
    }

    // --- Beziehungen ---

    /** Zugehoerige Schichtabrechnung */
    public function shiftSettlement(): BelongsTo
    {
        return $this->belongsTo(ShiftSettlement::class);
    }

    /** Zugehoerige Prueffrage */
    public function question(): BelongsTo
    {
        return $this->belongsTo(ShiftSettlementCheckQuestion::class, 'question_id');
    }
}
