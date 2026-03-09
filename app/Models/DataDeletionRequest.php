<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DSGVO Loeschantrags-Model.
 * Verwaltet Antraege auf Datenloesch ung, Export oder Berichtigung.
 */
class DataDeletionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'type',
        'status',
        'description',
        'requested_at',
        'processed_at',
        'completed_at',
        'processed_by',
        'rejection_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // --- Beziehungen ---

    /**
     * Antragsteller.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mandant (optional).
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Bearbeiter des Antrags.
     */
    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // --- Hilfsmethoden ---

    /**
     * Ist der Antrag noch offen?
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Wird der Antrag gerade bearbeitet?
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Ist der Antrag abgeschlossen?
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Wurde der Antrag abgelehnt?
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Antrag als "in Bearbeitung" markieren.
     */
    public function markAsProcessing(string $processedById): void
    {
        $this->update([
            'status' => 'processing',
            'processed_at' => now(),
            'processed_by' => $processedById,
        ]);
    }

    /**
     * Antrag als abgeschlossen markieren.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Antrag ablehnen.
     */
    public function reject(string $reason, string $processedById): void
    {
        $this->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'processed_at' => now(),
            'processed_by' => $processedById,
        ]);
    }
}
