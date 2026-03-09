<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Dokument-Model (Vertraege, Kuendigungen, Zeugnisse, etc.).
 * Unterstuetzt digitale Unterschriften und Versionierung.
 * Auditable: Alle Aenderungen werden im Audit-Log protokolliert.
 */
class Document extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'gas_station_id',
        'template_id',
        // Dokument
        'type',
        'category',
        'title',
        'content',
        'file_path',
        'file_size',
        // Signatur
        'requires_signature',
        'signed_at',
        'signature_data',
        'signed_by_name',
        'signed_by_ip',
        'counter_signed_at',
        'counter_signature_data',
        // Versand
        'sent_at',
        'sent_to_email',
        // Status
        'status',
        'version',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'requires_signature' => 'boolean',
            'signed_at' => 'datetime',
            'counter_signed_at' => 'datetime',
            'sent_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    // --- Beziehungen ---

    /**
     * Mitarbeiter dem das Dokument zugeordnet ist.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Tankstelle dem das Dokument zugeordnet ist.
     */
    public function gasStation(): BelongsTo
    {
        return $this->belongsTo(GasStation::class);
    }

    /**
     * Verwendete Vorlage.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    /**
     * Ersteller des Dokuments.
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // --- Hilfsmethoden ---

    /**
     * Ist das Dokument unterschrieben?
     */
    public function isSigned(): bool
    {
        return $this->signed_at !== null;
    }

    /**
     * Ist das Dokument gegengezeichnet?
     */
    public function isCounterSigned(): bool
    {
        return $this->counter_signed_at !== null;
    }

    /**
     * Wartet das Dokument auf eine Unterschrift?
     */
    public function isPendingSignature(): bool
    {
        return $this->requires_signature && ! $this->isSigned();
    }

    /**
     * Ist das Dokument ein Entwurf?
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Ist das Dokument ein HR-Dokument?
     */
    public function isHrDocument(): bool
    {
        return $this->category === 'hr';
    }
}
