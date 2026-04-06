<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kraftstoffdiebstahl-Meldung.
 * Wird per Wizard-Formular (Web) oder POS-App erfasst.
 * Status-Workflow: draft → submitted → in_progress → resolved/rejected
 */
class FuelTheft extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant, Auditable;

    protected $fillable = [
        'tenant_id',
        'gas_station_id',
        'reported_by',
        'status',
        // Step 1: Vorfall
        'incident_at',
        'product',
        'pump_number',
        'quantity',
        'amount',
        // Step 2: Fahrzeug
        'license_plate_available',
        'license_plate',
        'is_special_plate',
        'has_video_evidence',
        'plate_not_readable',
        'plate_stolen',
        'camera_defective',
        'camera_missing',
        'no_plate_other_reason',
        // Step 3: Umstaende
        'pump_confusion',
        'card_payment_error',
        'shop_purchase',
        'transfer_inkasso',
        'witness_first_name',
        'witness_last_name',
        'witness_address',
        'witness_zip',
        'witness_city',
        'witness_country',
        'comment',
        // Step 4: Belege
        'receipt_path',
        'additional_documents',
        'signature',
        // Step 5: Rechtsbelehrung
        'legal_notice_accepted',
        'legal_notice_accepted_at',
        'media',
        // Verwaltung
        'police_reference',
        'internal_notes',
        'submitted_at',
        'resolved_at',
        'police_report_at',
        'submitted_to_company_at',
        'payment_status',
        'payment_amount',
        'paid_by',
        'payment_method',
        'payment_received_at',
    ];

    protected array $auditExclude = ['signature'];

    protected function casts(): array
    {
        return [
            'incident_at' => 'datetime',
            'quantity' => 'decimal:3',
            'amount' => 'decimal:2',
            'license_plate_available' => 'boolean',
            'is_special_plate' => 'boolean',
            'has_video_evidence' => 'boolean',
            'plate_not_readable' => 'boolean',
            'plate_stolen' => 'boolean',
            'camera_defective' => 'boolean',
            'camera_missing' => 'boolean',
            'pump_confusion' => 'boolean',
            'card_payment_error' => 'boolean',
            'transfer_inkasso' => 'boolean',
            'additional_documents' => 'array',
            'media' => 'array',
            'legal_notice_accepted' => 'boolean',
            'legal_notice_accepted_at' => 'datetime',
            'submitted_at' => 'datetime',
            'resolved_at' => 'datetime',
            'police_report_at' => 'datetime',
            'submitted_to_company_at' => 'datetime',
            'payment_amount' => 'decimal:2',
            'payment_received_at' => 'datetime',
        ];
    }

    // --- Beziehungen ---

    /**
     * Tankstelle an der der Diebstahl stattfand.
     */
    public function gasStation(): BelongsTo
    {
        return $this->belongsTo(GasStation::class);
    }

    /**
     * Mitarbeiter/Partner der den Vorfall gemeldet hat.
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    // --- Status-Helfer ---

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    /**
     * Vorfall absenden (Status: draft → submitted).
     */
    public function submit(): void
    {
        $this->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    /**
     * Kann eine Anzeige generiert werden?
     * Erst 4 Tage nach dem Vorfall und noch nicht generiert.
     */
    public function canGeneratePoliceReport(): bool
    {
        if ($this->police_report_at) {
            return false;
        }

        if (! $this->incident_at) {
            return false;
        }

        return $this->incident_at->copy()->addDays(4)->isPast();
    }

    /**
     * Tage bis die Anzeige generiert werden kann.
     */
    public function daysUntilPoliceReport(): int
    {
        if (! $this->incident_at) {
            return 4;
        }

        $availableAt = $this->incident_at->copy()->addDays(4);

        return max(0, (int) now()->diffInDays($availableAt, false));
    }

    /**
     * Zahlungsstatus-Label.
     */
    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            'open' => 'Offen',
            'partial' => 'Teilzahlung',
            'paid' => 'Bezahlt',
            'written_off' => 'Abgeschrieben',
            default => 'Offen',
        };
    }

    /**
     * Offener Betrag.
     */
    public function getOpenAmountAttribute(): float
    {
        return max(0, (float) $this->amount - (float) $this->payment_amount);
    }

    // --- Accessors ---

    /**
     * Kraftstoffart fuer Anzeige.
     */
    public function getProductLabelAttribute(): string
    {
        return match ($this->product) {
            'super_e5' => 'Super E5',
            'super_e10' => 'Super E10',
            'diesel' => 'Diesel',
            'super_plus' => 'Super Plus',
            'ultimate_diesel' => 'Aral Ultimate Diesel',
            'ultimate_102' => 'Aral Ultimate 102',
            default => $this->product,
        };
    }

    /**
     * Shopkauf-Label fuer Anzeige.
     */
    public function getShopPurchaseLabelAttribute(): string
    {
        return match ($this->shop_purchase) {
            'card' => 'Shopkauf mit Kartenzahlung',
            'cash' => 'Shopkauf mit Barzahlung',
            'none' => 'Kein Shopkauf',
            default => '-',
        };
    }

    /**
     * Formattierter Betrag.
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format((float) $this->amount, 2, ',', '.') . ' €';
    }

    /**
     * Formattierte Menge.
     */
    public function getFormattedQuantityAttribute(): string
    {
        return number_format((float) $this->quantity, 3, ',', '.') . ' l';
    }
}
