<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Personalbogen-Model (erweiterter Mitarbeiterbogen).
 * Sensible Daten werden mit Laravel Encryption verschluesselt.
 * Auditable: Alle Aenderungen werden im Audit-Log protokolliert.
 */
class EmployeeProfile extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, Auditable;

    protected $fillable = [
        'user_id',
        'tenant_id',
        // Persoenliche Daten
        'date_of_birth',
        'place_of_birth',
        'gender',
        'marital_status',
        'nationality',
        'second_nationality',
        'residence_permit_type',
        'residence_permit_expires',
        'residence_permit_file',
        // Adresse
        'street',
        'zip',
        'city',
        'country',
        // Steuer & Sozialversicherung
        'tax_id',
        'tax_class',
        'church_tax',
        'denomination',
        'social_security_number',
        'health_insurance_name',
        'health_insurance_type',
        'health_insurance_number',
        'pension_insurance',
        // Bankverbindung
        'bank_name',
        'iban',
        'bic',
        'account_holder',
        // Notfallkontakt
        'emergency_name',
        'emergency_phone',
        'emergency_relation',
        // Kinder
        'num_children',
        'children_data',
        'child_allowance',
        // Qualifikationen
        'education_level',
        'professional_training',
        'drivers_license',
        'drivers_license_expiry',
        'certifications',
        'safety_training_date',
        'first_aid_training_date',
        'hazmat_training_date',
        // Arbeitsmedizin
        'medical_exam_date',
        'medical_exam_next',
        'medical_restrictions',
        'health_certificate',
        'health_certificate_date',
        // Beschaeftigung
        'employment_start',
        'employment_end',
        'employment_type',
        'weekly_hours',
        'probation_end',
        'vacation_days',
        'salary',
        'salary_type',
        // Status
        'status',
        'completed_at',
        'notes',
    ];

    /**
     * Casts: Verschluesselung sensibler Felder + Typen.
     * DSGVO: Bankdaten, SV-Nummer, Steuer-ID etc. werden mit AES-256-CBC verschluesselt.
     */
    protected function casts(): array
    {
        return [
            // Verschluesselte Felder (DSGVO)
            'date_of_birth' => 'encrypted',
            'tax_id' => 'encrypted',
            'social_security_number' => 'encrypted',
            'health_insurance_number' => 'encrypted',
            'bank_name' => 'encrypted',
            'iban' => 'encrypted',
            'bic' => 'encrypted',
            'account_holder' => 'encrypted',
            'children_data' => 'encrypted:array',
            'medical_restrictions' => 'encrypted',
            'salary' => 'encrypted',
            // Datums-Felder
            'residence_permit_expires' => 'date',
            'drivers_license_expiry' => 'date',
            'safety_training_date' => 'date',
            'first_aid_training_date' => 'date',
            'hazmat_training_date' => 'date',
            'medical_exam_date' => 'date',
            'medical_exam_next' => 'date',
            'health_certificate_date' => 'date',
            'employment_start' => 'date',
            'employment_end' => 'date',
            'probation_end' => 'date',
            'completed_at' => 'datetime',
            // JSON-Felder
            'drivers_license' => 'array',
            'certifications' => 'array',
            // Boolean-Felder
            'church_tax' => 'boolean',
            'pension_insurance' => 'boolean',
            'child_allowance' => 'boolean',
            'health_certificate' => 'boolean',
            // Zahlen
            'num_children' => 'integer',
            'vacation_days' => 'integer',
            'weekly_hours' => 'decimal:1',
        ];
    }

    // --- Beziehungen ---

    /**
     * Zugehoeriger Benutzer (Mitarbeiter).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // --- Hilfsmethoden ---

    /**
     * Pruefen ob der Personalbogen vollstaendig ausgefuellt ist.
     */
    public function isComplete(): bool
    {
        return $this->status === 'complete';
    }

    /**
     * Pruefen ob der Personalbogen noch unvollstaendig ist.
     */
    public function isIncomplete(): bool
    {
        return in_array($this->status, ['pending', 'incomplete']);
    }

    /**
     * Vollstaendige Adresse als String.
     */
    public function getFullAddressAttribute(): string
    {
        return trim("{$this->street}, {$this->zip} {$this->city}");
    }
}
