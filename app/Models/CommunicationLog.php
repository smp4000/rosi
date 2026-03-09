<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Kommunikationshistorie-Model (CRM).
 * Polymorph: Kann zu Kunden oder Lieferanten gehoeren.
 * Typen: email, call, note, meeting, task
 */
class CommunicationLog extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'communicable_type',
        'communicable_id',
        'type',
        'direction',
        'subject',
        'content',
        'attachments',
        'scheduled_at',
        'completed_at',
        'reminder_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminder_at' => 'datetime',
        ];
    }

    // --- Beziehungen ---

    /**
     * Polymorphe Beziehung zu Kunde oder Lieferant.
     */
    public function communicable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Ersteller des Eintrags.
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // --- Hilfsmethoden ---

    /**
     * Ist der Eintrag eine offene Aufgabe?
     */
    public function isOpenTask(): bool
    {
        return $this->type === 'task' && $this->completed_at === null;
    }

    /**
     * Hat der Eintrag eine aktive Erinnerung?
     */
    public function hasActiveReminder(): bool
    {
        return $this->reminder_at !== null && $this->reminder_at->isFuture();
    }
}
