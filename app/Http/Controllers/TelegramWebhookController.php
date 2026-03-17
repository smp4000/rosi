<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\Tenant;
use App\Models\TelegramLink;
use App\Models\User;
use App\Services\ChatService;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook-Controller fuer eingehende Telegram-Nachrichten.
 * Route: POST /webhook/telegram/{tenantSlug}
 * CSRF-exempt (Telegram ruft diesen Endpoint auf).
 */
class TelegramWebhookController extends Controller
{
    public function __construct(
        protected ChatService $chatService,
    ) {}

    /**
     * Eingehenden Telegram-Webhook verarbeiten.
     */
    public function handle(Request $request, string $tenantSlug): JsonResponse
    {
        // 1. Tenant finden
        $tenant = Tenant::where('slug', $tenantSlug)->first();

        if (! $tenant) {
            Log::warning('Telegram Webhook: Tenant nicht gefunden', ['slug' => $tenantSlug]);
            return response()->json(['ok' => true]); // 200 zurueckgeben damit Telegram nicht erneut sendet
        }

        // 2. Webhook-Secret validieren (optional, empfohlen)
        $expectedSecret = data_get($tenant->settings, 'messaging.telegram_webhook_secret');
        if ($expectedSecret && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $expectedSecret) {
            Log::warning('Telegram Webhook: Ungueltiges Secret', ['slug' => $tenantSlug]);
            return response()->json(['ok' => true]);
        }

        // 3. Update parsen
        $telegramService = TelegramService::forTenant($tenant);
        $update = $telegramService->parseUpdate($request->all());

        if (! $update || ! $update['chat_id']) {
            return response()->json(['ok' => true]);
        }

        // 4. Kommando oder Nachricht verarbeiten
        if ($update['is_command']) {
            $this->handleCommand($telegramService, $tenant, $update);
        } else {
            $this->handleMessage($telegramService, $tenant, $update);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Bot-Kommando verarbeiten (/start, /hilfe etc.).
     */
    protected function handleCommand(TelegramService $telegramService, Tenant $tenant, array $update): void
    {
        $parsed = $telegramService->parseCommand($update['text']);

        if (! $parsed) {
            return;
        }

        match ($parsed['command']) {
            'start' => $this->handleStartCommand($telegramService, $tenant, $update, $parsed['args']),
            'hilfe', 'help' => $this->handleHelpCommand($telegramService, $update),
            'status' => $this->handleStatusCommand($telegramService, $tenant, $update),
            default => $telegramService->sendMessage(
                $update['chat_id'],
                "❓ Unbekanntes Kommando. Tippen Sie /hilfe fuer eine Uebersicht."
            ),
        };
    }

    /**
     * /start {token} — Telegram-Account verknuepfen.
     */
    protected function handleStartCommand(TelegramService $telegramService, Tenant $tenant, array $update, string $token): void
    {
        if (empty($token)) {
            $telegramService->sendMessage(
                $update['chat_id'],
                "👋 Willkommen beim <b>{$tenant->name}</b> ROSI-Bot!\n\n"
                . "Um Ihren Account zu verknuepfen, klicken Sie bitte auf den Link in der ROSI-App.\n\n"
                . "Tippen Sie /hilfe fuer eine Uebersicht der Befehle."
            );
            return;
        }

        // Token suchen (ohne TenantScope, da wir den Tenant schon kennen)
        $link = TelegramLink::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where('link_token', $token)
            ->where('link_token_expires_at', '>', now())
            ->first();

        if (! $link) {
            $telegramService->sendMessage(
                $update['chat_id'],
                "❌ Dieser Verknuepfungs-Link ist ungueltig oder abgelaufen.\n"
                . "Bitte fordern Sie in der ROSI-App einen neuen Link an."
            );
            return;
        }

        // Bereits verknuepft?
        if ($link->isLinked()) {
            $telegramService->sendMessage(
                $update['chat_id'],
                "✅ Ihr Account ist bereits mit ROSI verknuepft!"
            );
            return;
        }

        // Verknuepfen
        $link->linkTelegram(
            chatId: $update['chat_id'],
            username: $update['username'],
            firstName: $update['first_name'],
        );

        $userName = $link->user->name ?? 'Mitarbeiter';

        $telegramService->sendMessage(
            $update['chat_id'],
            "✅ Erfolgreich verknuepft!\n\n"
            . "Hallo <b>{$userName}</b>, Ihr Telegram-Account ist jetzt mit ROSI verbunden.\n"
            . "Sie erhalten ab sofort Benachrichtigungen ueber Telegram.\n\n"
            . "Tippen Sie /hilfe fuer eine Uebersicht der Befehle."
        );

        Log::info('Telegram verknuepft', [
            'user_id' => $link->user_id,
            'telegram_chat_id' => $update['chat_id'],
            'username' => $update['username'],
        ]);
    }

    /**
     * /hilfe — Befehls-Uebersicht.
     */
    protected function handleHelpCommand(TelegramService $telegramService, array $update): void
    {
        $telegramService->sendMessage(
            $update['chat_id'],
            "📋 <b>ROSI-Bot Befehle:</b>\n\n"
            . "/hilfe — Diese Uebersicht\n"
            . "/status — Verknuepfungs-Status anzeigen\n\n"
            . "Sie koennen mir auch Nachrichten schreiben — diese werden an Ihren Arbeitgeber in der ROSI-App weitergeleitet."
        );
    }

    /**
     * /status — Verknuepfungs-Status anzeigen.
     */
    protected function handleStatusCommand(TelegramService $telegramService, Tenant $tenant, array $update): void
    {
        $link = TelegramLink::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where('telegram_chat_id', $update['chat_id'])
            ->where('is_active', true)
            ->first();

        if (! $link) {
            $telegramService->sendMessage(
                $update['chat_id'],
                "❌ Kein verknuepfter ROSI-Account gefunden."
            );
            return;
        }

        $userName = $link->user->name ?? 'Unbekannt';
        $linkedAt = $link->linked_at?->format('d.m.Y H:i') ?? '-';

        $telegramService->sendMessage(
            $update['chat_id'],
            "📊 <b>Verknuepfungs-Status:</b>\n\n"
            . "👤 Name: {$userName}\n"
            . "🏢 Firma: {$tenant->name}\n"
            . "📅 Verknuepft seit: {$linkedAt}\n"
            . "✅ Status: Aktiv"
        );
    }

    /**
     * Eingehende Textnachricht verarbeiten — an den Chat weiterleiten.
     */
    protected function handleMessage(TelegramService $telegramService, Tenant $tenant, array $update): void
    {
        if (empty($update['text'])) {
            return;
        }

        // Telegram-Link finden (wer schreibt?)
        $link = TelegramLink::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where('telegram_chat_id', $update['chat_id'])
            ->where('is_active', true)
            ->first();

        if (! $link) {
            $telegramService->sendMessage(
                $update['chat_id'],
                "❌ Ihr Telegram-Account ist nicht verknuepft.\n"
                . "Bitte verknuepfen Sie ihn zuerst in der ROSI-App."
            );
            return;
        }

        $sender = $link->user;
        $owner = $tenant->owner;

        if (! $owner) {
            Log::warning('Telegram Webhook: Tenant hat keinen Owner', ['tenant_id' => $tenant->id]);
            return;
        }

        // Konversation mit dem Tenant-Owner (Partner) erstellen/finden
        $conversation = $this->chatService->getOrCreateConversation($sender, $owner);

        // Nachricht im Chat speichern (Kanal = telegram)
        $this->chatService->sendMessage(
            conversation: $conversation,
            sender: $sender,
            content: $update['text'],
            channel: 'telegram',
            externalMessageId: (string) $update['message_id'],
        );

        // Bestaetigung an den Absender
        $telegramService->sendMessage(
            $update['chat_id'],
            "✅ Nachricht zugestellt."
        );
    }
}
