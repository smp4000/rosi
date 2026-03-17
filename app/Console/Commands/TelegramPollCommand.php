<?php

namespace App\Console\Commands;

use App\Http\Controllers\TelegramWebhookController;
use App\Models\Tenant;
use App\Services\ChatService;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Telegram Polling fuer lokale Entwicklung (statt Webhook).
 * Fragt alle 2 Sekunden die Telegram getUpdates API ab.
 *
 * Verwendung: php artisan telegram:poll
 * Stoppen: Ctrl+C
 */
class TelegramPollCommand extends Command
{
    protected $signature = 'telegram:poll {--tenant= : Tenant-Slug (optional, sonst alle)}';

    protected $description = 'Telegram-Bot Updates per Polling abfragen (Entwicklung)';

    protected int $offset = 0;

    public function handle(): int
    {
        $this->info('🤖 Telegram Polling gestartet...');
        $this->info('   Druecke Ctrl+C zum Beenden.');
        $this->newLine();

        // Tenants mit aktivem Telegram laden
        $tenants = $this->getTenants();

        if ($tenants->isEmpty()) {
            $this->error('Keine Tenants mit aktivem Telegram-Bot gefunden.');
            $this->info('Konfigurieren Sie einen Bot unter /dashboard/messaging-settings');
            return 1;
        }

        foreach ($tenants as $tenant) {
            $this->info("📡 Tenant: {$tenant->name} (@{$tenant->telegram_bot_username})");

            // Webhook entfernen falls gesetzt (Polling und Webhook schliessen sich aus)
            $service = TelegramService::forTenant($tenant);
            $service->deleteWebhook();
        }

        $this->newLine();
        $this->info('Warte auf Nachrichten...');
        $this->newLine();

        // Polling-Loop
        while (true) {
            foreach ($tenants as $tenant) {
                $this->pollTenant($tenant);
            }

            sleep(2);
        }

        return 0;
    }

    /**
     * Updates fuer einen Tenant abfragen.
     */
    protected function pollTenant(Tenant $tenant): void
    {
        $service = TelegramService::forTenant($tenant);

        if (! $service->isConfigured()) {
            return;
        }

        $updates = $this->getUpdates($service);

        if (empty($updates)) {
            return;
        }

        $controller = app(TelegramWebhookController::class);

        foreach ($updates as $update) {
            // Offset aktualisieren (naechstes Update)
            $this->offset = ($update['update_id'] ?? 0) + 1;

            // Update anzeigen
            $text = $update['message']['text'] ?? '[kein Text]';
            $from = $update['message']['from']['first_name'] ?? 'Unbekannt';
            $chatId = $update['message']['chat']['id'] ?? '?';

            $this->line("<fg=cyan>[{$tenant->name}]</> <fg=green>{$from}</> (Chat {$chatId}): {$text}");

            // Request simulieren (mit Secret-Header damit Validierung durchgeht)
            $request = Request::create(
                uri: "/webhook/telegram/{$tenant->slug}",
                method: 'POST',
                content: json_encode($update)
            );
            $request->headers->set('Content-Type', 'application/json');

            // Webhook-Secret setzen damit der Controller die Validierung besteht
            $secret = data_get($tenant->settings, 'messaging.telegram_webhook_secret');
            if ($secret) {
                $request->headers->set('X-Telegram-Bot-Api-Secret-Token', $secret);
            }

            try {
                $controller->handle($request, $tenant->slug);
                $this->line("  <fg=green>✓ Verarbeitet</>");
            } catch (\Exception $e) {
                $this->error("  Fehler: {$e->getMessage()}");
                Log::error('Telegram Poll Error', ['message' => $e->getMessage()]);
            }
        }
    }

    /**
     * getUpdates API aufrufen.
     */
    protected function getUpdates(TelegramService $service): array
    {
        try {
            // Wir nutzen die Reflection, um an den Token zu kommen
            $reflection = new \ReflectionClass($service);
            $tokenProp = $reflection->getProperty('botToken');
            $tokenProp->setAccessible(true);
            $token = $tokenProp->getValue($service);

            $response = Http::withoutVerifying()
                ->timeout(35) // Long polling: 30s + 5s Buffer
                ->post("https://api.telegram.org/bot{$token}/getUpdates", [
                    'offset' => $this->offset,
                    'timeout' => 30, // Long polling
                    'allowed_updates' => ['message'],
                ]);

            $data = $response->json();

            if (! ($data['ok'] ?? false)) {
                $desc = $data['description'] ?? 'Unbekannter Fehler';
                $this->error("  API-Fehler: {$desc}");
                return [];
            }

            return $data['result'] ?? [];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Timeout ist normal bei Long Polling
            return [];
        } catch (\Exception $e) {
            $this->error("  Verbindungsfehler: {$e->getMessage()}");
            sleep(5); // Bei Fehler 5s warten
            return [];
        }
    }

    /**
     * Tenants mit konfiguriertem Telegram laden.
     */
    protected function getTenants()
    {
        $query = Tenant::query();

        if ($slug = $this->option('tenant')) {
            $query->where('slug', $slug);
        }

        return $query->get()->filter(function ($tenant) {
            return $tenant->isTelegramEnabled() && $tenant->telegram_bot_token;
        });
    }
}
