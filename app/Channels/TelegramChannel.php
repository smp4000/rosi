<?php

namespace App\Channels;

use App\Services\TelegramService;
use Illuminate\Notifications\Notification;

/**
 * Custom Laravel Notification Channel fuer Telegram.
 * Nutzt TelegramService mit dem Bot-Token des Tenants.
 */
class TelegramChannel
{
    /**
     * Notification ueber Telegram senden.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        // Chat-ID ermitteln
        $chatId = $notifiable->routeNotificationForTelegram($notification);

        if (! $chatId) {
            return;
        }

        // Tenant-spezifischen TelegramService laden
        $tenant = $notifiable->tenant;

        if (! $tenant || ! $tenant->isTelegramEnabled()) {
            return;
        }

        $telegramService = TelegramService::forTenant($tenant);

        if (! $telegramService->isConfigured()) {
            return;
        }

        // Nachricht aus der Notification holen
        $message = $notification->toTelegram($notifiable);

        if (is_string($message)) {
            // Einfache Textnachricht
            $telegramService->sendMessage($chatId, $message);
        } elseif (is_array($message)) {
            // Erweitert: ['text' => ..., 'reply_markup' => ..., 'document' => ...]
            if (! empty($message['document'])) {
                $telegramService->sendDocument(
                    $chatId,
                    $message['document'],
                    $message['caption'] ?? null,
                );
            }

            if (! empty($message['text'])) {
                $telegramService->sendMessage(
                    $chatId,
                    $message['text'],
                    $message['reply_markup'] ?? null,
                );
            }
        }
    }
}
