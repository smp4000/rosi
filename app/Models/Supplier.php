<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lieferanten-Model mit CRM-Funktionen.
 * Mandantenfaehig mit Tankstellen-Zuordnung, Preislisten und Reklamationen.
 * Auditable: Alle Aenderungen werden im Audit-Log protokolliert.
 */
class Supplier extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = [
        'tenant_id',
        'supplier_number',
        // Firmendaten
        'company_name',
        'vat_id',
        'trade_register',
        'website',
        // Kontakt
        'contact_salutation',
        'contact_first_name',
        'contact_last_name',
        'contact_position',
        'contact_email',
        'contact_phone',
        'contact_mobile',
        // Adresse
        'street',
        'zip',
        'city',
        'country',
        // Details
        'category',
        'supply_types',
        // Vertrag
        'contract_start',
        'contract_end',
        'contract_auto_renew',
        'contract_notice_period',
        'contract_file',
        'payment_terms',
        'minimum_order',
        'delivery_days',
        // Bewertung
        'rating',
        'rating_notes',
        // CRM
        'status',
        'tags',
        'notes',
        'last_contact_at',
        'next_followup_at',
        'assigned_to',
    ];

    protected function casts(): array
    {
        return [
            'supply_types' => 'array',
            'contract_start' => 'date',
            'contract_end' => 'date',
            'contract_auto_renew' => 'boolean',
            'minimum_order' => 'decimal:2',
            'delivery_days' => 'array',
            'rating' => 'integer',
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
        return $this->belongsToMany(GasStation::class, 'supplier_gas_station');
    }

    /**
     * Preislisten des Lieferanten.
     */
    public function priceLists(): HasMany
    {
        return $this->hasMany(SupplierPriceList::class);
    }

    /**
     * Reklamationen des Lieferanten.
     */
    public function complaints(): HasMany
    {
        return $this->hasMany(SupplierComplaint::class);
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
     * Anzeigename des Kontakts.
     */
    public function getContactNameAttribute(): string
    {
        return trim(($this->contact_first_name ?? '') . ' ' . ($this->contact_last_name ?? ''));
    }

    // --- Hilfsmethoden ---

    /**
     * Pruefen ob der Vertrag aktiv ist.
     */
    public function hasActiveContract(): bool
    {
        if (! $this->contract_start) {
            return false;
        }

        if ($this->contract_end && $this->contract_end->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Pruefen ob der Vertrag bald auslaeuft (30 Tage).
     */
    public function isContractExpiringSoon(): bool
    {
        if (! $this->contract_end) {
            return false;
        }

        return $this->contract_end->isFuture()
            && $this->contract_end->diffInDays(now()) <= 30;
    }
}
