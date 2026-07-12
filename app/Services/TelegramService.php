<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Telegram Bot API Service.
 * Verwaltet alle Kommunikation mit der Telegram API.
 * TLS-Verifikation: zentral im AppServiceProvider geregelt (A-5) —
 * lokal (XAMPP) aus, auf dem Server an. Kein withoutVerifying() mehr noetig.
 */
class TelegramService
{
    protected string $baseUrl = 'https://api.telegram.org/bot';

    protected ?string $botToken;

    public function __construct(?string $botToken = null)
    {
        $this->botToken = $botToken;
    }

    /**
     * Service fuer einen bestimmten Tenant erstellen.
     */
    public static function forTenant(Tenant $tenant): self
    {
        return new self($tenant->telegram_bot_token);
    }

    /**
     * Pruefen ob ein Bot-Token konfiguriert ist.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->botToken);
    }

    // --- Bot-Verwaltung ---

    /**
     * Bot-Info abrufen (getMe) — zum Testen der Verbindung.
     */
    public function getMe(): mixed
    {
        return $this->apiCall('getMe');
    }

    /**
     * Webhook setzen fuer eingehende Nachrichten.
     */
    public function setWebhook(string $url, ?string $secretToken = null): mixed
    {
        $params = ['url' => $url];

        if ($secretToken) {
            $params['secret_token'] = $secretToken;
        }

        // Nur Text-Nachrichten und Kommandos empfangen
        $params['allowed_updates'] = ['message'];

        return $this->apiCall('setWebhook', $params);
    }

    /**
     * Webhook entfernen.
     */
    public function deleteWebhook(): mixed
    {
        return $this->apiCall('deleteWebhook');
    }

    /**
     * Webhook-Info abrufen.
     */
    public function getWebhookInfo(): mixed
    {
        return $this->apiCall('getWebhookInfo');
    }

    // --- Nachrichten senden ---

    /**
     * Textnachricht senden.
     */
    public function sendMessage(int $chatId, string $text, ?array $replyMarkup = null): mixed
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }

        return $this->apiCall('sendMessage', $params);
    }

    /**
     * Dokument (Datei) senden.
     */
    public function sendDocument(int $chatId, string $filePath, ?string $caption = null): mixed
    {
        if (! file_exists($filePath)) {
            Log::warning('TelegramService: Datei nicht gefunden', ['path' => $filePath]);
            return null;
        }

        $params = [
            'chat_id' => $chatId,
        ];

        if ($caption) {
            $params['caption'] = $caption;
            $params['parse_mode'] = 'HTML';
        }

        return $this->apiCallWithFile('sendDocument', $params, 'document', $filePath);
    }

    /**
     * Inline-Buttons erstellen (z.B. "Jetzt unterschreiben").
     */
    public static function inlineKeyboard(array $buttons): array
    {
        return [
            'inline_keyboard' => [$buttons],
        ];
    }

    /**
     * Einzelnen Inline-Button erstellen.
     */
    public static function urlButton(string $text, string $url): array
    {
        return [
            'text' => $text,
            'url' => $url,
        ];
    }

    // --- Webhook-Verarbeitung ---

    /**
     * Eingehenden Webhook-Update parsen.
     */
    public function parseUpdate(array $payload): ?array
    {
        if (! isset($payload['message'])) {
            return null;
        }

        $message = $payload['message'];
        $chat = $message['chat'] ?? [];
        $from = $message['from'] ?? [];

        return [
            'update_id' => $payload['update_id'] ?? null,
            'message_id' => $message['message_id'] ?? null,
            'chat_id' => $chat['id'] ?? null,
            'chat_type' => $chat['type'] ?? 'private',
            'from_id' => $from['id'] ?? null,
            'username' => $from['username'] ?? null,
            'first_name' => $from['first_name'] ?? null,
            'text' => $message['text'] ?? null,
            'is_command' => isset($message['entities']) && ($message['entities'][0]['type'] ?? null) === 'bot_command',
        ];
    }

    /**
     * Kommando und Argumente aus Text extrahieren.
     * z.B. "/start abc123" => ['command' => 'start', 'args' => 'abc123']
     */
    public function parseCommand(?string $text): ?array
    {
        if (! $text || ! str_starts_with($text, '/')) {
            return null;
        }

        $parts = explode(' ', $text, 2);
        $command = ltrim($parts[0], '/');

        // @BotUsername entfernen falls vorhanden
        if (str_contains($command, '@')) {
            $command = explode('@', $command)[0];
        }

        return [
            'command' => strtolower($command),
            'args' => trim($parts[1] ?? ''),
        ];
    }

    // --- API-Aufrufe (intern) ---

    /**
     * API-Aufruf an Telegram (JSON).
     */
    protected function apiCall(string $method, array $params = []): mixed
    {
        if (! $this->isConfigured()) {
            Log::warning('TelegramService: Kein Bot-Token konfiguriert');
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->post("{$this->baseUrl}{$this->botToken}/{$method}", $params);

            $data = $response->json();

            if (! ($data['ok'] ?? false)) {
                Log::warning("TelegramService: API-Fehler bei {$method}", [
                    'error_code' => $data['error_code'] ?? null,
                    'description' => $data['description'] ?? 'Unbekannt',
                ]);
                return null;
            }

            return $data['result'] ?? [];
        } catch (\Exception $e) {
            Log::error("TelegramService: Exception bei {$method}", [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * API-Aufruf mit Datei-Upload (Multipart).
     */
    protected function apiCallWithFile(string $method, array $params, string $fileField, string $filePath): ?array
    {
        if (! $this->isConfigured()) {
            Log::warning('TelegramService: Kein Bot-Token konfiguriert');
            return null;
        }

        try {
            $request = Http::timeout(30)
                ->asMultipart();

            // Parameter als Multipart-Felder
            foreach ($params as $key => $value) {
                $request = $request->attach($key, (string) $value);
            }

            // Datei anhaengen
            $request = $request->attach(
                $fileField,
                file_get_contents($filePath),
                basename($filePath)
            );

            $response = $request->post("{$this->baseUrl}{$this->botToken}/{$method}");
            $data = $response->json();

            if (! ($data['ok'] ?? false)) {
                Log::warning("TelegramService: API-Fehler bei {$method}", [
                    'error_code' => $data['error_code'] ?? null,
                    'description' => $data['description'] ?? 'Unbekannt',
                ]);
                return null;
            }

            return $data['result'] ?? [];
        } catch (\Exception $e) {
            Log::error("TelegramService: Exception bei {$method}", [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
