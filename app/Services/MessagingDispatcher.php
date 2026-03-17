<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Multi-Channel Messaging Dispatcher.
 * Versendet Nachrichten ueber den bevorzugten Kanal (E-Mail, Telegram, WhatsApp).
 * E-Mail bleibt Hauptkanal, Messenger sind zusaetzlich.
 */
class MessagingDispatcher
{
    public function __construct(
        protected ChatService $chatService,
    ) {}

    /**
     * Nachricht an einen Empfaenger ueber seinen bevorzugten Kanal senden.
     * E-Mail wird IMMER gesendet, Messenger zusaetzlich.
     *
     * @param  array  $data  ['subject', 'body', 'type']
     * @return array  ['email' => bool, 'telegram' => bool, 'app' => bool]
     */
    public function dispatch(User $recipient, string $message, string $type = 'text', array $data = []): array
    {
        $results = [
            'email' => false,
            'telegram' => false,
            'app' => false,
        ];

        $tenant = $recipient->tenant;

        // 1. E-Mail immer senden (Hauptkanal)
        if (! empty($data['subject'])) {
            try {
                // E-Mail-Versand wird vom aufrufenden Code uebernommen
                // (z.B. DocumentService, Notification etc.)
                $results['email'] = true;
            } catch (\Exception $e) {
                Log::error('MessagingDispatcher: E-Mail-Fehler', [
                    'user_id' => $recipient->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 2. Telegram zusaetzlich senden (wenn verfuegbar + aktiviert)
        if ($tenant && $tenant->isTelegramEnabled() && $recipient->hasTelegram()) {
            try {
                $telegramService = TelegramService::forTenant($tenant);
                $chatId = $recipient->routeNotificationForTelegram();

                if ($chatId) {
                    $result = $telegramService->sendMessage($chatId, $message);
                    $results['telegram'] = $result !== null;
                }
            } catch (\Exception $e) {
                Log::error('MessagingDispatcher: Telegram-Fehler', [
                    'user_id' => $recipient->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 3. In-App Chat-Nachricht erstellen (wenn Chat aktiviert)
        if ($tenant && $tenant->isChatEnabled() && ! empty($data['sender'])) {
            try {
                $sender = $data['sender'] instanceof User ? $data['sender'] : User::find($data['sender']);
                if ($sender) {
                    $conversation = $this->chatService->getOrCreateConversation($sender, $recipient);
                    $this->chatService->sendMessage($conversation, $sender, $message, $type);
                    $results['app'] = true;
                }
            } catch (\Exception $e) {
                Log::error('MessagingDispatcher: Chat-Fehler', [
                    'user_id' => $recipient->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Dokument multi-channel versenden (PDF + Signatur-Link).
     *
     * @return array  Versand-Ergebnisse pro Kanal
     */
    public function dispatchDocument(User $recipient, Document $document, ?User $sender = null): array
    {
        $results = [
            'email' => false,
            'telegram' => false,
            'app' => false,
        ];

        $tenant = $recipient->tenant;

        // 1. Telegram: PDF + Signatur-Link senden
        if ($tenant && $tenant->isTelegramEnabled() && $recipient->hasTelegram()) {
            try {
                $telegramService = TelegramService::forTenant($tenant);
                $chatId = $recipient->routeNotificationForTelegram();

                if ($chatId) {
                    // PDF als Datei senden
                    if ($document->file_path) {
                        $filePath = Storage::disk('local')->path($document->file_path);
                        $caption = "📄 <b>{$document->title}</b>";

                        if ($document->requires_signature && $document->signature_token) {
                            $signUrl = route('document.sign', $document->signature_token);
                            $caption .= "\n\n✍️ Bitte unterschreiben Sie das Dokument online.";

                            $telegramService->sendDocument($chatId, $filePath, $caption);

                            // Unterschreiben-Button als separate Nachricht
                            $keyboard = TelegramService::inlineKeyboard([
                                TelegramService::urlButton('✍️ Jetzt unterschreiben', $signUrl),
                            ]);
                            $telegramService->sendMessage($chatId, 'Klicken Sie auf den Button um das Dokument zu unterschreiben:', $keyboard);
                        } else {
                            $telegramService->sendDocument($chatId, $filePath, $caption);
                        }

                        $results['telegram'] = true;
                    }
                }
            } catch (\Exception $e) {
                Log::error('MessagingDispatcher: Telegram-Dokument-Fehler', [
                    'document_id' => $document->id,
                    'user_id' => $recipient->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 2. In-App Chat: Dokument-Nachricht
        if ($tenant && $tenant->isChatEnabled() && $sender) {
            try {
                $conversation = $this->chatService->getOrCreateConversation($sender, $recipient);
                $chatMessage = "📄 Neues Dokument: {$document->title}";

                if ($document->requires_signature) {
                    $chatMessage .= "\n✍️ Bitte unterschreiben Sie das Dokument.";
                }

                $this->chatService->sendMessage(
                    conversation: $conversation,
                    sender: $sender,
                    content: $chatMessage,
                    type: 'document',
                    metadata: [
                        'document_id' => $document->id,
                        'document_title' => $document->title,
                        'requires_signature' => $document->requires_signature,
                        'signature_url' => $document->signature_token
                            ? route('document.sign', $document->signature_token)
                            : null,
                    ],
                );

                $results['app'] = true;
            } catch (\Exception $e) {
                Log::error('MessagingDispatcher: Chat-Dokument-Fehler', [
                    'document_id' => $document->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }
}
