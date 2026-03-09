<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kunden-Model (B2B + B2C CRM).
 * Mandantenfaehig mit Tankstellen-Zuordnung.
 * Auditable: Alle Aenderungen werden im Audit-Log protokolliert.
 */
class Customer extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = [
        'tenant_id',
        'customer_number',
        'customer_type',
        // B2B
        'company_name',
        'vat_id',
        'trade_register',
        'industry',
        'company_size',
        'website',
        // Kontakt
        'salutation',
        'first_name',
        'last_name',
        'position',
        'email',
        'phone',
        'mobile',
        'fax',
        // Adresse
        'street',
        'zip',
        'city',
        'country',
        // CRM
        'status',
        'source',
        'payment_terms',
        'credit_limit',
        'discount_rate',
        'customer_card_number',
        'loyalty_points',
        'tags',
        'notes',
        'last_contact_at',
        'next_followup_at',
        'assigned_to',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'discount_rate' => 'decimal:2',
            'loyalty_points' => 'integer',
            'tags' => 'array',
            'last_contact_at' => 'datetime',
            'next_followup_at' => 'datetime',
        ];
    }

    // --- Beziehungen ---

    /**
     * Zustaendiger Mitarbeiter.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Zugeordnete Tankstellen (Many-to-Many).
     */
    public function gasStations(): BelongsToMany
    {
        return $this->belongsToMany(GasStation::class, 'customer_gas_station');
    }

    /**
     * Kommunikationshistorie (polymorph).
     */
    public function communicationLogs(): MorphMany
    {
        return $this->morphMany(CommunicationLog::class, 'communicable');
    }

    // --- Accessors ---

    /**
     * Vollstaendiger Name (Firma oder Person).
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->customer_type === 'b2b' && $this->company_name) {
            return $this->company_name;
        }

        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    // --- Hilfsmethoden ---

    /**
     * Ist der Kunde ein Geschaeftskunde?
     */
    public function isB2B(): bool
    {
        return $this->customer_type === 'b2b';
    }

    /**
     * Ist der Kunde ein Privatkunde?
     */
    public function isB2C(): bool
    {
        return $this->customer_type === 'b2c';
    }
}
