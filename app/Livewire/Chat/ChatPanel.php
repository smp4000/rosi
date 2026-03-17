<?php

namespace App\Livewire\Chat;

use App\Models\ChatConversation;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Chat-Panel Livewire-Komponente.
 * 2-Spalten-Layout: Konversationsliste + Nachrichtenverlauf.
 * Near-Realtime via wire:poll.5s.
 */
class ChatPanel extends Component
{
    public ?string $activeConversationId = null;
    public string $newMessage = '';
    public ?string $newConversationUserId = null;

    /**
     * Nachricht senden.
     */
    public function sendMessage(): void
    {
        $this->validate([
            'newMessage' => 'required|string|max:5000',
        ]);

        if (! $this->activeConversationId) {
            return;
        }

        $chatService = app(ChatService::class);
        $conversation = ChatConversation::find($this->activeConversationId);

        if (! $conversation || ! $conversation->hasParticipant(auth()->user())) {
            return;
        }

        $chatService->sendMessage(
            conversation: $conversation,
            sender: auth()->user(),
            content: trim($this->newMessage),
        );

        $this->newMessage = '';
    }

    /**
     * Konversation auswaehlen.
     */
    public function selectConversation(string $conversationId): void
    {
        $conversation = ChatConversation::find($conversationId);

        if (! $conversation || ! $conversation->hasParticipant(auth()->user())) {
            return;
        }

        $this->activeConversationId = $conversationId;

        // Nachrichten als gelesen markieren
        app(ChatService::class)->markAsRead($conversation, auth()->user());
    }

    /**
     * Neue Konversation starten.
     */
    public function startConversation(): void
    {
        if (! $this->newConversationUserId) {
            return;
        }

        $otherUser = User::find($this->newConversationUserId);

        if (! $otherUser || $otherUser->tenant_id !== auth()->user()->tenant_id) {
            return;
        }

        $chatService = app(ChatService::class);
        $conversation = $chatService->getOrCreateConversation(auth()->user(), $otherUser);

        $this->activeConversationId = $conversation->id;
        $this->newConversationUserId = null;
    }

    /**
     * Konversationsliste laden.
     */
    public function getConversationsProperty(): Collection
    {
        return app(ChatService::class)->getConversations(auth()->user());
    }

    /**
     * Nachrichten der aktiven Konversation laden.
     */
    public function getMessagesProperty(): Collection
    {
        if (! $this->activeConversationId) {
            return collect();
        }

        $conversation = ChatConversation::find($this->activeConversationId);

        if (! $conversation) {
            return collect();
        }

        // Nachrichten als gelesen markieren bei jedem Poll
        app(ChatService::class)->markAsRead($conversation, auth()->user());

        return app(ChatService::class)->getMessages($conversation);
    }

    /**
     * Aktive Konversation.
     */
    public function getActiveConversationProperty(): ?ChatConversation
    {
        if (! $this->activeConversationId) {
            return null;
        }

        return ChatConversation::with(['participantOne', 'participantTwo'])->find($this->activeConversationId);
    }

    /**
     * Chat-Partner der aktiven Konversation.
     */
    public function getOtherUserProperty(): ?User
    {
        $conversation = $this->activeConversation;

        if (! $conversation) {
            return null;
        }

        return $conversation->getOtherParticipant(auth()->user());
    }

    /**
     * Verfuegbare Benutzer fuer neue Konversation (gleicher Tenant).
     */
    public function getAvailableUsersProperty(): Collection
    {
        return User::where('tenant_id', auth()->user()->tenant_id)
            ->where('id', '!=', auth()->id())
            ->where('is_active', true)
            ->orderBy('last_name')
            ->get();
    }

    public function render()
    {
        return view('livewire.chat.chat-panel');
    }
}
