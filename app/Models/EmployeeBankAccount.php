<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bankkonto eines Mitarbeiters.
 * Sensible Felder (IBAN, BIC, etc.) werden verschluesselt gespeichert.
 */
class EmployeeBankAccount extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'type',
        'account_holder',
        'iban',
        'bic',
        'bank_name',
        'is_primary',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'account_holder' => 'encrypted',
            'iban' => 'encrypted',
            'bic' => 'encrypted',
            'bank_name' => 'encrypted',
            'is_primary' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
