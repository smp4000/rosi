<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Service fuer den In-App Chat.
 * Verwaltet Konversationen und Nachrichten.
 */
class ChatService
{
    /**
     * Konversation zwischen zwei Usern finden oder erstellen.
     */
    public function getOrCreateConversation(User $userA, User $userB): ChatConversation
    {
        $conversation = ChatConversation::findBetween($userA->id, $userB->id);

        if ($conversation) {
            return $conversation;
        }

        // tenant_id: Partner-Tenant verwenden (Admin hat kein tenant_id)
        $tenantId = $userA->tenant_id ?? $userB->tenant_id;

        return ChatConversation::create([
            'tenant_id' => $tenantId,
            'participant_one_id' => $userA->id,
            'participant_two_id' => $userB->id,
        ]);
    }

    /**
     * Nachricht senden.
     */
    public function sendMessage(
        ChatConversation $conversation,
        User $sender,
        string $content,
        string $type = 'text',
        string $channel = 'app',
        ?array $metadata = null,
        ?string $externalMessageId = null,
    ): ChatMessage {
        $message = ChatMessage::create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'content' => $content,
            'type' => $type,
            'channel' => $channel,
            'metadata' => $metadata,
            'external_message_id' => $externalMessageId,
        ]);

        // last_message_at aktualisieren
        $conversation->update(['last_message_at' => now()]);

        return $message;
    }

    /**
     * System-Nachricht senden (z.B. "Telegram verknuepft").
     */
    public function sendSystemMessage(ChatConversation $conversation, string $content, ?array $metadata = null): ChatMessage
    {
        // System-Nachrichten verwenden participant_one als Sender (Convention)
        return $this->sendMessage(
            conversation: $conversation,
            sender: $conversation->participantOne,
            content: $content,
            type: 'system',
            metadata: $metadata,
        );
    }

    /**
     * Nachrichten einer Konversation laden (paginiert).
     */
    public function getMessages(ChatConversation $conversation, int $limit = 50, ?int $beforeId = null): Collection
    {
        $query = $conversation->messages()
            ->with('sender')
            ->orderByDesc('created_at');

        if ($beforeId) {
            $query->where('id', '<', $beforeId);
        }

        return $query->limit($limit)->get()->reverse()->values();
    }

    /**
     * Alle Nachrichten als gelesen markieren fuer einen User.
     */
    public function markAsRead(ChatConversation $conversation, User $user): int
    {
        return $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Konversationsliste fuer einen User laden (sortiert nach letzter Nachricht).
     */
    public function getConversations(User $user, bool $includeArchived = false): Collection
    {
        $query = ChatConversation::where(function ($q) use ($user) {
            $q->where('participant_one_id', $user->id)
              ->orWhere('participant_two_id', $user->id);
        })
        ->with(['participantOne', 'participantTwo'])
        ->orderByDesc('last_message_at');

        if (! $includeArchived) {
            $query->where('is_archived', false);
        }

        return $query->get();
    }

    /**
     * Gesamte Anzahl ungelesener Nachrichten fuer einen User.
     */
    public function getUnreadCount(User $user): int
    {
        return ChatMessage::whereHas('conversation', function ($q) use ($user) {
            $q->where('participant_one_id', $user->id)
              ->orWhere('participant_two_id', $user->id);
        })
        ->where('sender_id', '!=', $user->id)
        ->whereNull('read_at')
        ->count();
    }

    /**
     * Konversation archivieren/entarchivieren.
     */
    public function toggleArchive(ChatConversation $conversation): void
    {
        $conversation->update(['is_archived' => ! $conversation->is_archived]);
    }
}
