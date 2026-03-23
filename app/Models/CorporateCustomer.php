<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Firmenkunde (Corporate Customer).
 * Unique pro Tankstelle (customer_number + gas_station_id).
 * Bankdaten DSGVO-verschluesselt.
 */
class CorporateCustomer extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory, HasUuids, SoftDeletes, Auditable;

    protected $fillable = [
        'gas_station_id',
        'customer_number',
        'salutation',
        'name',
        'name_2',
        'department',
        'street',
        'zip',
        'city',
        'phone',
        'email',
        'iban',
        'bic',
        'bank_name',
        'mandate_reference',
        'contract_number',
        'payment_method',
        'discount_percent',
        'credit_limit',
        'prepayment',
        'print_delivery_note',
        'print_prepayment',
        'notes',
        'send_via_email',
        'send_via_print',
        'is_active',
        'csv_printed_at',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'customer_number' => 'integer',
            'iban' => 'encrypted',
            'bic' => 'encrypted',
            'bank_name' => 'encrypted',
            'discount_percent' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'prepayment' => 'decimal:2',
            'print_delivery_note' => 'boolean',
            'print_prepayment' => 'boolean',
            'send_via_email' => 'boolean',
            'send_via_print' => 'boolean',
            'is_active' => 'boolean',
            'csv_printed_at' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    // ── Relationships ──

    public function gasStation(): BelongsTo
    {
        return $this->belongsTo(GasStation::class);
    }

    public function fuelCards(): HasMany
    {
        return $this->hasMany(FuelCard::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    // ── Scopes ──

    public function scopeForStation($query, string $gasStationId)
    {
        return $query->where('gas_station_id', $gasStationId);
    }

    // ── Accessors ──

    /**
     * Vollstaendige Adresse als String.
     */
    public function getFullAddressAttribute(): string
    {
        return collect([$this->street, "{$this->zip} {$this->city}"])
            ->filter()
            ->implode(', ');
    }

    /**
     * Anzeigename: Anrede + Name.
     */
    public function getDisplayNameAttribute(): string
    {
        return collect([$this->salutation, $this->name])
            ->filter()
            ->implode(' ');
    }
}
