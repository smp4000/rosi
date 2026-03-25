<?php

namespace App\Livewire\Chat;

use App\Models\ChatConversation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Admin-Chat-Panel: Tenant-uebergreifend.
 * Der Admin kann mit allen Partnern und Mitarbeitern chatten.
 */
class AdminChatPanel extends Component
{
    public ?string $activeConversationId = null;
    public string $newMessage = '';
    public ?string $selectedTenantId = null;
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

        $conversation = ChatConversation::withoutGlobalScopes()->find($this->activeConversationId);

        if (! $conversation || ! $conversation->hasParticipant(auth()->user())) {
            return;
        }

        \App\Models\ChatMessage::create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'sender_id' => auth()->id(),
            'content' => trim($this->newMessage),
            'type' => 'text',
            'channel' => 'app',
        ]);

        $conversation->update(['last_message_at' => now()]);

        $this->newMessage = '';
    }

    /**
     * Konversation auswaehlen.
     */
    public function selectConversation(string $conversationId): void
    {
        $conversation = ChatConversation::withoutGlobalScopes()->find($conversationId);

        if (! $conversation || ! $conversation->hasParticipant(auth()->user())) {
            return;
        }

        $this->activeConversationId = $conversationId;

        // Nachrichten als gelesen markieren
        $conversation->messages()
            ->where('sender_id', '!=', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Tenant-Filter aendern.
     */
    public function updatedSelectedTenantId(): void
    {
        $this->newConversationUserId = null;
    }

    /**
     * Neue Konversation starten (tenant-uebergreifend).
     */
    public function startConversation(): void
    {
        \Log::info('AdminChat::startConversation', [
            'newConversationUserId' => $this->newConversationUserId,
            'authId' => auth()->id(),
        ]);

        if (! $this->newConversationUserId) {
            \Log::warning('AdminChat: no user selected');
            return;
        }

        // Admin sieht alle User, kein Tenant-Filter
        $otherUser = User::withoutGlobalScopes()->find($this->newConversationUserId);

        if (! $otherUser) {
            \Log::warning('AdminChat: user not found', ['id' => $this->newConversationUserId]);
            return;
        }

        // findBetween muss auch ohne TenantScope suchen
        $conversation = ChatConversation::withoutGlobalScopes()
            ->where(function ($q) use ($otherUser) {
                $q->where('participant_one_id', auth()->id())
                  ->where('participant_two_id', $otherUser->id);
            })->orWhere(function ($q) use ($otherUser) {
                $q->where('participant_one_id', $otherUser->id)
                  ->where('participant_two_id', auth()->id());
            })->first();

        if (! $conversation) {
            $tenantId = $otherUser->tenant_id ?? auth()->user()->tenant_id;
            $conversation = ChatConversation::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'participant_one_id' => auth()->id(),
                'participant_two_id' => $otherUser->id,
            ]);
        }

        \Log::info('AdminChat: conversation ready', ['id' => $conversation->id]);
        $this->activeConversationId = $conversation->id;
        $this->newConversationUserId = null;
    }

    /**
     * Alle Konversationen des Admins (tenant-uebergreifend).
     */
    public function getConversationsProperty(): Collection
    {
        return ChatConversation::withoutGlobalScopes()
            ->where(function ($q) {
                $q->where('participant_one_id', auth()->id())
                  ->orWhere('participant_two_id', auth()->id());
            })
            ->with(['participantOne', 'participantTwo'])
            ->orderByDesc('last_message_at')
            ->get();
    }

    /**
     * Nachrichten der aktiven Konversation.
     */
    public function getMessagesProperty(): Collection
    {
        if (! $this->activeConversationId) {
            return collect();
        }

        $conversation = ChatConversation::withoutGlobalScopes()->find($this->activeConversationId);

        if (! $conversation) {
            return collect();
        }

        // Nachrichten als gelesen markieren
        $conversation->messages()
            ->where('sender_id', '!=', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $conversation->messages()
            ->with('sender')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * Aktive Konversation.
     */
    public function getActiveConversationProperty(): ?ChatConversation
    {
        if (! $this->activeConversationId) {
            return null;
        }

        return ChatConversation::withoutGlobalScopes()
            ->with(['participantOne', 'participantTwo'])
            ->find($this->activeConversationId);
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
     * Verfuegbare Tenants fuer die Auswahl.
     */
    public function getTenantsProperty(): Collection
    {
        return Tenant::orderBy('name')->get();
    }

    /**
     * Verfuegbare Benutzer fuer neue Konversation.
     * Wenn ein Tenant ausgewaehlt ist, nur dessen Benutzer.
     * Sonst alle aktiven Benutzer (ohne den Admin selbst).
     */
    public function getAvailableUsersProperty(): Collection
    {
        $query = User::where('id', '!=', auth()->id())
            ->where('is_active', true);

        if ($this->selectedTenantId) {
            $query->where('tenant_id', $this->selectedTenantId);
        } else {
            $query->whereNotNull('tenant_id');
        }

        return $query->orderBy('last_name')->get();
    }

    public function render()
    {
        return view('livewire.chat.admin-chat-panel');
    }
}
