<div wire:poll.5s style="display: flex; height: calc(100vh - 12rem); border-radius: 16px; border: 1px solid #e5e7eb; background: white; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); overflow: hidden;">

    {{-- ===== LINKE SPALTE: Konversationsliste ===== --}}
    <div style="width: 320px; flex-shrink: 0; border-right: 1px solid #e5e7eb; display: flex; flex-direction: column;">

        {{-- Header --}}
        <div style="border-bottom: 1px solid #f3f4f6; padding: 12px 16px;">
            <h2 style="font-size: 18px; font-weight: 700; color: #111827; margin: 0;">Nachrichten</h2>
        </div>

        {{-- Neue Konversation --}}
        <div style="border-bottom: 1px solid #f3f4f6; padding: 12px 16px;">
            <div style="display: flex; gap: 8px;">
                <select wire:model="newConversationUserId"
                        style="flex: 1; border-radius: 8px; border: 1px solid #d1d5db; padding: 8px 12px; font-size: 13px; outline: none;">
                    <option value="">Mitarbeiter waehlen...</option>
                    @foreach($this->availableUsers as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
                <button wire:click="startConversation"
                        style="border-radius: 8px; background: #2563eb; padding: 8px 12px; color: white; border: none; cursor: pointer;"
                        @if(!$newConversationUserId) disabled @endif>
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </button>
            </div>
        </div>

        {{-- Konversations-Liste --}}
        <div style="flex: 1; overflow-y: auto;">
            @forelse($this->conversations as $conversation)
                @php
                    $other = $conversation->getOtherParticipant(auth()->user());
                    $unread = $conversation->unreadCountFor(auth()->user());
                    $isActive = $activeConversationId === $conversation->id;
                    $lastMsg = $conversation->messages()->latest('created_at')->first();
                @endphp
                <button
                    wire:click="selectConversation('{{ $conversation->id }}')"
                    style="width: 100%; display: flex; align-items: center; gap: 12px; padding: 12px 16px; text-align: left; border: none; cursor: pointer;
                           border-left: 4px solid {{ $isActive ? '#3b82f6' : 'transparent' }};
                           background: {{ $isActive ? '#eff6ff' : 'white' }};"
                    onmouseover="if(!{{ $isActive ? 'true' : 'false' }}) this.style.background='#f9fafb'"
                    onmouseout="if(!{{ $isActive ? 'true' : 'false' }}) this.style.background='white'"
                >
                    {{-- Avatar --}}
                    <div style="width: 40px; height: 40px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: 50%;
                                background: {{ $isActive ? '#dbeafe' : '#f3f4f6' }};
                                color: {{ $isActive ? '#1d4ed8' : '#4b5563' }};">
                        <span style="font-size: 13px; font-weight: 700;">
                            {{ $other ? strtoupper(substr($other->first_name ?? $other->last_name, 0, 1)) . strtoupper(substr($other->last_name, 0, 1)) : '?' }}
                        </span>
                    </div>

                    {{-- Info --}}
                    <div style="flex: 1; min-width: 0;">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <span style="font-weight: 600; font-size: 13px; color: #111827; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                {{ $other?->name ?? 'Unbekannt' }}
                            </span>
                            @if($conversation->last_message_at)
                                <span style="font-size: 11px; color: #9ca3af; flex-shrink: 0;">
                                    {{ $conversation->last_message_at->diffForHumans(short: true) }}
                                </span>
                            @endif
                        </div>
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 2px;">
                            <p style="font-size: 12px; color: #6b7280; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin: 0;">
                                @if($lastMsg)
                                    @if($lastMsg->type === 'system')
                                        <em>{{ \Illuminate\Support\Str::limit($lastMsg->content, 40) }}</em>
                                    @else
                                        {{ \Illuminate\Support\Str::limit($lastMsg->content, 40) }}
                                    @endif
                                @else
                                    Keine Nachrichten
                                @endif
                            </p>
                            @if($unread > 0)
                                <span style="display: inline-flex; height: 20px; min-width: 20px; align-items: center; justify-content: center; border-radius: 10px; background: #2563eb; padding: 0 6px; font-size: 11px; font-weight: 700; color: white; flex-shrink: 0;">
                                    {{ $unread }}
                                </span>
                            @endif
                        </div>
                    </div>
                </button>
            @empty
                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #9ca3af; padding: 16px;">
                    <span style="font-size: 48px; margin-bottom: 8px;">💬</span>
                    <p style="font-size: 13px; text-align: center; margin: 0;">Noch keine Konversationen.<br>Waehlen Sie einen Mitarbeiter aus.</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- ===== RECHTE SPALTE: Nachrichtenverlauf ===== --}}
    <div style="flex: 1; display: flex; flex-direction: column;">
        @if($activeConversationId && $this->otherUser)
            {{-- Chat-Header --}}
            <div style="border-bottom: 1px solid #f3f4f6; padding: 12px 24px; display: flex; align-items: center; gap: 12px;">
                <div style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: #dbeafe; color: #1d4ed8;">
                    <span style="font-size: 13px; font-weight: 700;">
                        {{ strtoupper(substr($this->otherUser->first_name ?? $this->otherUser->last_name, 0, 1)) }}{{ strtoupper(substr($this->otherUser->last_name, 0, 1)) }}
                    </span>
                </div>
                <div>
                    <h3 style="font-weight: 600; color: #111827; margin: 0; font-size: 15px;">{{ $this->otherUser->name }}</h3>
                    @if($this->otherUser->hasTelegram())
                        <span style="font-size: 11px; color: #6b7280; display: flex; align-items: center; gap: 4px;">
                            📱 Telegram verknuepft
                        </span>
                    @endif
                </div>
            </div>

            {{-- Nachrichten --}}
            <div style="flex: 1; overflow-y: auto; padding: 16px 24px;" id="chat-messages" x-data x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight })">
                @forelse($this->messages as $message)
                    @php
                        $isMine = $message->sender_id === auth()->id();
                    @endphp

                    @if($message->type === 'system')
                        {{-- System-Nachricht --}}
                        <div style="display: flex; justify-content: center; margin-bottom: 12px;">
                            <span style="border-radius: 16px; background: #f3f4f6; padding: 4px 16px; font-size: 12px; color: #6b7280;">
                                {{ $message->content }}
                            </span>
                        </div>
                    @else
                        <div style="display: flex; justify-content: {{ $isMine ? 'flex-end' : 'flex-start' }}; margin-bottom: 12px;">
                            <div style="max-width: 75%;">
                                {{-- Bubble --}}
                                <div style="border-radius: 16px; padding: 10px 16px;
                                    {{ $isMine
                                        ? 'background: #2563eb; color: white; border-bottom-right-radius: 6px;'
                                        : 'background: #f3f4f6; color: #111827; border-bottom-left-radius: 6px;' }}">

                                    {{-- Dokument-Nachricht --}}
                                    @if($message->type === 'document' && $message->metadata)
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                            <span>📄</span>
                                            <span style="font-weight: 600; font-size: 13px;">{{ $message->metadata['document_title'] ?? 'Dokument' }}</span>
                                        </div>
                                        @if(!empty($message->metadata['signature_url']))
                                            <a href="{{ $message->metadata['signature_url'] }}" target="_blank"
                                               style="display: inline-flex; align-items: center; gap: 4px; font-size: 12px; text-decoration: underline;
                                                      color: {{ $isMine ? '#bfdbfe' : '#2563eb' }};">
                                                ✍️ Unterschreiben
                                            </a>
                                        @endif
                                    @endif

                                    <p style="font-size: 14px; white-space: pre-wrap; word-break: break-word; margin: 0;">{{ $message->content }}</p>
                                </div>

                                {{-- Meta-Zeile --}}
                                <div style="margin-top: 4px; display: flex; align-items: center; gap: 6px; justify-content: {{ $isMine ? 'flex-end' : 'flex-start' }};">
                                    <span style="font-size: 10px; color: #9ca3af;">
                                        {{ $message->created_at->format('H:i') }}
                                    </span>
                                    @if($message->channel !== 'app')
                                        <span style="font-size: 10px; padding: 2px 6px; border-radius: 8px;
                                            {{ $message->channel === 'telegram'
                                                ? 'background: #dbeafe; color: #2563eb;'
                                                : 'background: #dcfce7; color: #16a34a;' }}">
                                            {{ $message->channel_label }}
                                        </span>
                                    @endif
                                    @if($isMine && $message->read_at)
                                        <span style="color: #60a5fa; font-size: 12px;">✓</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                @empty
                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #9ca3af;">
                        <span style="font-size: 48px; margin-bottom: 8px;">💬</span>
                        <p style="font-size: 14px; margin: 0;">Schreiben Sie die erste Nachricht!</p>
                    </div>
                @endforelse
            </div>

            {{-- Eingabefeld --}}
            <div style="border-top: 1px solid #f3f4f6; padding: 12px 16px;">
                <form wire:submit="sendMessage" style="display: flex; gap: 8px;">
                    <input type="text" wire:model="newMessage"
                           placeholder="Nachricht schreiben..."
                           style="flex: 1; border-radius: 12px; border: 1px solid #d1d5db; background: #f9fafb; padding: 10px 16px; font-size: 14px; outline: none;"
                           autocomplete="off">
                    <button type="submit"
                            style="border-radius: 12px; background: #2563eb; padding: 10px 16px; color: white; border: none; cursor: pointer;"
                            @if(!$newMessage) disabled @endif>
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                        </svg>
                    </button>
                </form>
            </div>
        @else
            {{-- Kein Chat ausgewaehlt --}}
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #9ca3af;">
                <span style="font-size: 64px; margin-bottom: 16px;">💬</span>
                <p style="font-size: 18px; font-weight: 500; margin: 0; color: #6b7280;">Waehlen Sie eine Konversation</p>
                <p style="font-size: 14px; margin-top: 4px; color: #9ca3af;">oder starten Sie einen neuen Chat</p>
            </div>
        @endif
    </div>
</div>
