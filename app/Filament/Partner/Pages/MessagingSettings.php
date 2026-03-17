<?php

namespace App\Filament\Partner\Pages;

use App\Services\TelegramService;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Messaging-Einstellungen im Partner-Panel.
 * Telegram-Bot konfigurieren, Kanaele aktivieren.
 */
class MessagingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Einstellungen';

    protected static ?string $title = 'Kommunikations-Einstellungen';

    protected static ?string $slug = 'messaging-settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Kommunikation';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.partner.pages.messaging-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $tenant = auth()->user()->tenant;
        $settings = $tenant->settings ?? [];

        $this->form->fill([
            'chat_enabled' => data_get($settings, 'messaging.chat_enabled', true),
            'telegram_enabled' => data_get($settings, 'messaging.telegram_enabled', false),
            'telegram_bot_token' => '', // Nie vorausfuellen (Sicherheit)
            'telegram_bot_username' => data_get($settings, 'messaging.telegram_bot_username', ''),
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('In-App Chat')
                    ->description('Chat direkt in der ROSI-Plattform.')
                    ->schema([
                        Toggle::make('chat_enabled')
                            ->label('Chat aktivieren')
                            ->helperText('Ermoeglicht den direkten Chat zwischen Partner und Mitarbeitern in der App.')
                            ->default(true),
                    ]),

                Section::make('Telegram')
                    ->description('Nachrichten und Dokumente per Telegram versenden.')
                    ->schema([
                        Toggle::make('telegram_enabled')
                            ->label('Telegram aktivieren')
                            ->helperText('Aktiviert Telegram als zusaetzlichen Kommunikationskanal.')
                            ->live(),

                        Placeholder::make('telegram_anleitung')
                            ->label('')
                            ->content(new HtmlString('
                                <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px; padding: 16px; margin-bottom: 8px;">
                                    <p style="font-weight: 600; color: #0369a1; margin-bottom: 8px;">📋 Telegram-Bot erstellen:</p>
                                    <ol style="color: #475569; font-size: 14px; padding-left: 20px; margin: 0;">
                                        <li>Oeffnen Sie Telegram und suchen Sie <strong>@BotFather</strong></li>
                                        <li>Senden Sie <code>/newbot</code> und folgen Sie den Anweisungen</li>
                                        <li>Kopieren Sie den <strong>Bot-Token</strong> und fuegen Sie ihn unten ein</li>
                                        <li>Klicken Sie auf <strong>"Verbindung testen"</strong></li>
                                    </ol>
                                </div>
                            '))
                            ->visible(fn (Get $get) => $get('telegram_enabled')),

                        TextInput::make('telegram_bot_token')
                            ->label('Bot-Token')
                            ->placeholder('123456789:ABCdefGHIjklMNOpqrSTUvwxYZ')
                            ->password()
                            ->revealable()
                            ->helperText('Den Token erhalten Sie von @BotFather in Telegram.')
                            ->visible(fn (Get $get) => $get('telegram_enabled')),

                        TextInput::make('telegram_bot_username')
                            ->label('Bot-Username')
                            ->placeholder('mein_rosi_bot')
                            ->prefix('@')
                            ->helperText('Der Username Ihres Bots (ohne @).')
                            ->visible(fn (Get $get) => $get('telegram_enabled')),
                    ]),

                Section::make('DSGVO-Hinweis')
                    ->schema([
                        Placeholder::make('dsgvo_hinweis')
                            ->label('')
                            ->content(new HtmlString('
                                <div style="background: #fefce8; border: 1px solid #fde68a; border-radius: 12px; padding: 16px;">
                                    <p style="font-weight: 600; color: #92400e; margin-bottom: 8px;">⚠️ Datenschutz-Hinweis</p>
                                    <p style="color: #78716c; font-size: 14px; margin: 0;">
                                        Beim Einsatz von Telegram als Kommunikationskanal muessen Sie sicherstellen, dass
                                        Ihre Mitarbeiter der Nutzung zustimmen (DSGVO Art. 6 Abs. 1 lit. a).
                                        Die Einwilligung wird beim Verknuepfen des Telegram-Accounts automatisch protokolliert.
                                        Bot-Tokens werden verschluesselt gespeichert.
                                    </p>
                                </div>
                            ')),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * Einstellungen speichern.
     */
    public function save(): void
    {
        $data = $this->form->getState();
        $tenant = auth()->user()->tenant;

        // Chat-Einstellung
        $tenant->setMessagingSetting('chat_enabled', $data['chat_enabled']);

        // Telegram-Einstellungen
        $tenant->setMessagingSetting('telegram_enabled', $data['telegram_enabled']);

        if (! empty($data['telegram_bot_token'])) {
            $tenant->setTelegramBotToken($data['telegram_bot_token']);
        }

        if (! empty($data['telegram_bot_username'])) {
            // @ immer entfernen — wird nur als Prefix im UI angezeigt
            $username = ltrim($data['telegram_bot_username'], '@');
            $tenant->setMessagingSetting('telegram_bot_username', $username);
        }

        Notification::make()
            ->title('Einstellungen gespeichert')
            ->success()
            ->send();
    }

    /**
     * Telegram-Verbindung testen.
     */
    public function testConnection(): void
    {
        $data = $this->form->getState();
        $token = $data['telegram_bot_token'];

        if (empty($token)) {
            // Fallback: Gespeicherten Token verwenden
            $token = auth()->user()->tenant->telegram_bot_token;
        }

        if (empty($token)) {
            Notification::make()
                ->title('Kein Bot-Token')
                ->body('Bitte geben Sie zuerst einen Bot-Token ein.')
                ->danger()
                ->send();
            return;
        }

        $service = new TelegramService($token);
        $result = $service->getMe();

        if ($result) {
            $botName = $result['first_name'] ?? 'Unbekannt';
            $botUsername = $result['username'] ?? '-';

            // Username automatisch setzen (ohne @)
            $this->data['telegram_bot_username'] = ltrim($botUsername, '@');

            Notification::make()
                ->title('Verbindung erfolgreich!')
                ->body("Bot: {$botName} (@{$botUsername})")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Verbindung fehlgeschlagen')
                ->body('Pruefen Sie den Bot-Token und versuchen Sie es erneut.')
                ->danger()
                ->send();
        }
    }

    /**
     * Webhook bei Telegram einrichten (nur fuer Produktion mit oeffentlicher URL).
     * Fuer lokale Entwicklung: php artisan telegram:poll verwenden.
     */
    protected function setupWebhook(string $token, $tenant): void
    {
        // Auf localhost keinen Webhook setzen — stattdessen Polling verwenden
        if (str_contains(config('app.url'), 'localhost') || str_contains(config('app.url'), '127.0.0.1')) {
            return;
        }

        $service = new TelegramService($token);

        $secret = data_get($tenant->settings, 'messaging.telegram_webhook_secret');
        if (! $secret) {
            $secret = Str::random(64);
            $tenant->setMessagingSetting('telegram_webhook_secret', $secret);
        }

        $webhookUrl = route('webhook.telegram', $tenant->slug);
        $service->setWebhook($webhookUrl, $secret);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test_connection')
                ->label('Verbindung testen')
                ->icon('heroicon-o-signal')
                ->color('info')
                ->action('testConnection'),
        ];
    }
}
