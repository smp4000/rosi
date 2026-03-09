<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * KI-Chat-Nachrichten-Model.
 * Speichert Chat-Verlaeufe mit dem KI-Assistenten.
 * Rollen: user, assistant, system
 */
class AiChatMessage extends Model
{
    use HasFactory, BelongsToTenant;

    /**
     * Kein updated_at Feld in der Tabelle.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'conversation_id',
        'role',
        'content',
        'context',
        'tokens_used',
        'model',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'tokens_used' => 'integer',
        ];
    }

    // --- Beziehungen ---

    /**
     * Benutzer der die Nachricht gesendet/empfangen hat.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // --- Scopes ---

    /**
     * Nachrichten einer bestimmten Konversation.
     */
    public function scopeForConversation($query, string $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    /**
     * Nur Benutzer-Nachrichten.
     */
    public function scopeUserMessages($query)
    {
        return $query->where('role', 'user');
    }

    /**
     * Nur Assistenten-Nachrichten.
     */
    public function scopeAssistantMessages($query)
    {
        return $query->where('role', 'assistant');
    }

    // --- Hilfsmethoden ---

    /**
     * Ist die Nachricht vom Benutzer?
     */
    public function isFromUser(): bool
    {
        return $this->role === 'user';
    }

    /**
     * Ist die Nachricht vom Assistenten?
     */
    public function isFromAssistant(): bool
    {
        return $this->role === 'assistant';
    }
}
