<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gutschein-Einloesung.
 *
 * Jede Zeile dokumentiert eine (Teil-)Einloesung eines Gutscheins.
 * Ein Gutschein kann mehrere Einloesungen haben (Teileinloesung).
 */
class VoucherRedemption extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory, HasUuids, Auditable;
    use \App\Traits\BelongsToTenant; // T-2: automatischer Mandanten-Filter (TenantScope) + tenant_id-Autofill

    protected $fillable = [
        'voucher_id',
        'station_id',
        'amount',
        'remaining_after',
        'employee_id',
        'employee_name',
        'redeemed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'remaining_after' => 'decimal:2',
            'redeemed_at' => 'datetime',
        ];
    }

    // -- Relationships --

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(GasStation::class, 'station_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    // -- Accessors --

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2, ',', '.') . ' €';
    }
}
