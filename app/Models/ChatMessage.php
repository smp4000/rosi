<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Chat-Nachricht — unveraenderlich (kein updated_at).
 * Bigint Auto-Increment statt UUID (hohe Menge).
 * Kanaele: app, telegram, whatsapp.
 */
class ChatMessage extends Model
{
    use HasFactory, BelongsToTenant;

    /**
     * Kein updated_at — Nachrichten sind unveraenderlich.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'sender_id',
        'content',
        'type',
        'metadata',
        'channel',
        'external_message_id',
        'read_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'read_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    // --- Beziehungen ---

    /**
     * Zugehoerige Konversation.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    /**
     * Absender der Nachricht.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // --- Hilfsmethoden ---

    /**
     * Ist die Nachricht eine Textnachricht?
     */
    public function isText(): bool
    {
        return $this->type === 'text';
    }

    /**
     * Ist die Nachricht ein Dokument?
     */
    public function isDocument(): bool
    {
        return $this->type === 'document';
    }

    /**
     * Ist die Nachricht eine Systemnachricht?
     */
    public function isSystem(): bool
    {
        return $this->type === 'system';
    }

    /**
     * Wurde die Nachricht ueber die App gesendet?
     */
    public function isFromApp(): bool
    {
        return $this->channel === 'app';
    }

    /**
     * Wurde die Nachricht ueber Telegram gesendet?
     */
    public function isFromTelegram(): bool
    {
        return $this->channel === 'telegram';
    }

    /**
     * Wurde die Nachricht gelesen?
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Nachricht als gelesen markieren.
     */
    public function markAsRead(): void
    {
        if (! $this->isRead()) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * Kanal-Label fuer die Anzeige.
     */
    public function getChannelLabelAttribute(): string
    {
        return match ($this->channel) {
            'telegram' => 'Telegram',
            'whatsapp' => 'WhatsApp',
            default => 'App',
        };
    }
}
