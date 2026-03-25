<?php

namespace App\Filament\Pages;

use App\Models\SystemSetting;
use App\Services\TelegramService;
use App\Services\TenantMailerService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * Admin-Einstellungen: System-SMTP, Chat, Telegram.
 * Unabhaengig von Tenant-Einstellungen.
 */
class AdminSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Einstellungen';

    protected static ?string $title = 'System-Einstellungen';

    protected static ?string $slug = 'system-settings';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.admin-settings';

    public ?array $data = [];

    public bool $hasStoredPassword = false;

    /**
     * SMTP-Konfigurationen der gaengigsten Provider.
     */
    public const PROVIDERS = [
        'ionos' => [
            'label' => 'IONOS (1&1)',
            'smtp_host' => 'smtp.ionos.de',
            'smtp_port' => '465',
            'smtp_encryption' => 'ssl',
        ],
        'strato' => [
            'label' => 'Strato',
            'smtp_host' => 'smtp.strato.de',
            'smtp_port' => '465',
            'smtp_encryption' => 'ssl',
        ],
        'gmail' => [
            'label' => 'Gmail (Google)',
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
        ],
        'icloud' => [
            'label' => 'iCloud (Apple)',
            'smtp_host' => 'smtp.mail.me.com',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
        ],
        'office365' => [
            'label' => 'Microsoft 365 / Outlook',
            'smtp_host' => 'smtp.office365.com',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
        ],
        'web_de' => [
            'label' => 'WEB.DE',
            'smtp_host' => 'smtp.web.de',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
        ],
        'gmx' => [
            'label' => 'GMX',
            'smtp_host' => 'mail.gmx.net',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
        ],
        'o2_online' => [
            'label' => 'O2 Online',
            'smtp_host' => 'mail.o2online.de',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
        ],
        't_online' => [
            'label' => 'T-Online (Telekom)',
            'smtp_host' => 'securesmtp.t-online.de',
            'smtp_port' => '465',
            'smtp_encryption' => 'ssl',
        ],
        'custom' => [
            'label' => 'Eigener Server...',
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
        ],
    ];

    public function mount(): void
    {
        $mailSettings = SystemSetting::getGroup('mail');
        $commSettings = SystemSetting::getGroup('communication');

        $smtpHost = $mailSettings['smtp_host'] ?? '';
        $this->hasStoredPassword = ! empty($mailSettings['smtp_password'] ?? '');

        $this->form->fill([
            // Tab: Kommunikation
            'admin_chat_enabled' => ($commSettings['chat_enabled'] ?? 'true') !== 'false',
            'admin_telegram_enabled' => ($commSettings['telegram_enabled'] ?? 'false') !== 'false',
            'admin_telegram_bot_token' => '',
            'admin_telegram_bot_username' => $commSettings['telegram_bot_username'] ?? '',
            'admin_telegram_chat_id' => $commSettings['telegram_chat_id'] ?? '',

            // Tab: E-Mail
            'provider' => $this->detectProvider($smtpHost),
            'smtp_host' => $smtpHost,
            'smtp_port' => $mailSettings['smtp_port'] ?? '587',
            'smtp_username' => $mailSettings['smtp_username'] ?? '',
            'smtp_password' => '',
            'smtp_encryption' => $mailSettings['smtp_encryption'] ?? 'tls',
            'mail_from_address' => $mailSettings['mail_from_address'] ?? '',
            'mail_from_name' => $mailSettings['mail_from_name'] ?? '',
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Tabs::make('admin_settings_tabs')
                    ->tabs([
                        Tabs\Tab::make('Kommunikation')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->schema([
                                Section::make('Admin-Chat')
                                    ->description('Chat-Funktion fuer das Admin-Panel.')
                                    ->schema([
                                        Toggle::make('admin_chat_enabled')
                                            ->label('Admin-Chat aktivieren')
                                            ->helperText('Ermoeglicht den Chat zwischen Admin und Partnern.')
                                            ->default(true),
                                    ]),

                                Section::make('Admin-Telegram')
                                    ->description('Separater Telegram-Bot fuer System-Benachrichtigungen.')
                                    ->columns(2)
                                    ->schema([
                                        Toggle::make('admin_telegram_enabled')
                                            ->label('Telegram aktivieren')
                                            ->helperText('Aktiviert Telegram fuer System-Benachrichtigungen (z.B. neue Tenant-Registrierungen).')
                                            ->live()
                                            ->columnSpanFull(),

                                        Placeholder::make('telegram_setup_guide')
                                            ->label('')
                                            ->content(new HtmlString('
                                                <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px; padding: 16px;">
                                                    <p style="font-weight: 600; color: #0369a1; margin-bottom: 8px;">Einrichtung:</p>
                                                    <ol style="color: #475569; font-size: 14px; padding-left: 20px; margin: 0;">
                                                        <li>Erstellen Sie einen Bot bei <strong>@BotFather</strong> in Telegram (<code>/newbot</code>)</li>
                                                        <li>Tragen Sie den <strong>Bot-Token</strong> und <strong>Username</strong> unten ein</li>
                                                        <li>Schreiben Sie dem Bot eine Nachricht in Telegram (z.B. <code>/start</code>)</li>
                                                        <li>Klicken Sie auf <strong>"Chat-ID ermitteln"</strong> um Ihre Chat-ID zu laden</li>
                                                        <li>Testen Sie mit <strong>"Test-Nachricht senden"</strong></li>
                                                    </ol>
                                                </div>
                                            '))
                                            ->visible(fn (Get $get) => $get('admin_telegram_enabled'))
                                            ->columnSpanFull(),

                                        TextInput::make('admin_telegram_bot_token')
                                            ->label('Bot-Token')
                                            ->placeholder('123456789:ABCdefGHIjklMNOpqrSTUvwxYZ')
                                            ->password()
                                            ->revealable()
                                            ->autocomplete('new-password')
                                            ->helperText('Separater Bot fuer Admin-Benachrichtigungen (nicht identisch mit Partner-Bots).')
                                            ->visible(fn (Get $get) => $get('admin_telegram_enabled')),

                                        TextInput::make('admin_telegram_bot_username')
                                            ->label('Bot-Username')
                                            ->placeholder('rosi_admin_bot')
                                            ->prefix('@')
                                            ->helperText('Der Username des Admin-Bots (ohne @).')
                                            ->visible(fn (Get $get) => $get('admin_telegram_enabled')),

                                        TextInput::make('admin_telegram_chat_id')
                                            ->label('Admin Chat-ID')
                                            ->placeholder('123456789')
                                            ->numeric()
                                            ->helperText('Ihre persoenliche Telegram Chat-ID. Klicken Sie auf "Chat-ID ermitteln" nachdem Sie dem Bot geschrieben haben.')
                                            ->visible(fn (Get $get) => $get('admin_telegram_enabled'))
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('E-Mail / SMTP')
                            ->icon('heroicon-o-envelope')
                            ->schema([
                                Placeholder::make('admin_mail_info')
                                    ->label('')
                                    ->content(new HtmlString('
                                        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px;">
                                            <strong>System-SMTP:</strong> Dieser Mailserver wird fuer Admin-Mails verwendet
                                            (z.B. System-Benachrichtigungen, Passwort-Reset).
                                            Partner verwenden ihre eigenen SMTP-Einstellungen.
                                        </div>
                                    ')),

                                Section::make('SMTP-Server')
                                    ->icon('heroicon-o-server-stack')
                                    ->columns(3)
                                    ->schema([
                                        Select::make('provider')
                                            ->label('Provider')
                                            ->options(collect(self::PROVIDERS)->mapWithKeys(fn ($p, $key) => [$key => $p['label']]))
                                            ->placeholder('Provider waehlen...')
                                            ->live()
                                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                                if ($state && isset(self::PROVIDERS[$state]) && $state !== 'custom') {
                                                    $provider = self::PROVIDERS[$state];
                                                    $set('smtp_host', $provider['smtp_host']);
                                                    $set('smtp_port', $provider['smtp_port']);
                                                    $set('smtp_encryption', $provider['smtp_encryption']);
                                                }
                                            })
                                            ->columnSpanFull(),

                                        TextInput::make('smtp_host')
                                            ->label('SMTP-Host')
                                            ->placeholder('smtp.example.com')
                                            ->prefixIcon('heroicon-o-server-stack')
                                            ->maxLength(255),

                                        TextInput::make('smtp_port')
                                            ->label('Port')
                                            ->numeric()
                                            ->default(587)
                                            ->placeholder('587')
                                            ->prefixIcon('heroicon-o-hashtag'),

                                        Select::make('smtp_encryption')
                                            ->label('Verschluesselung')
                                            ->options([
                                                'tls' => 'STARTTLS (587)',
                                                'ssl' => 'SSL/TLS (465)',
                                                'none' => 'Keine',
                                            ])
                                            ->default('tls'),

                                        TextInput::make('smtp_username')
                                            ->label('Benutzername')
                                            ->placeholder('admin@example.com')
                                            ->prefixIcon('heroicon-o-user')
                                            ->maxLength(255),

                                        TextInput::make('smtp_password')
                                            ->label('Passwort')
                                            ->password()
                                            ->revealable()
                                            ->autocomplete('new-password')
                                            ->placeholder(fn () => $this->hasStoredPassword ? '••••••••' : '')
                                            ->helperText($this->hasStoredPassword
                                                ? 'Passwort ist gespeichert. Leer lassen um es beizubehalten.'
                                                : 'Wird verschluesselt gespeichert.')
                                            ->prefixIcon('heroicon-o-lock-closed')
                                            ->maxLength(255),
                                    ]),

                                Section::make('Absender')
                                    ->icon('heroicon-o-identification')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('mail_from_address')
                                            ->label('Absender-Adresse')
                                            ->email()
                                            ->placeholder('noreply@rosi-system.de')
                                            ->prefixIcon('heroicon-o-envelope'),

                                        TextInput::make('mail_from_name')
                                            ->label('Absender-Name')
                                            ->placeholder('ROSI System')
                                            ->prefixIcon('heroicon-o-building-storefront')
                                            ->maxLength(255),
                                    ]),
                            ]),
                    ])
                    ->persistTabInQueryString('tab')
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // --- Kommunikation ---
        SystemSetting::set('chat_enabled', $data['admin_chat_enabled'] ? 'true' : 'false', 'communication');
        SystemSetting::set('telegram_enabled', $data['admin_telegram_enabled'] ? 'true' : 'false', 'communication');

        if (! empty($data['admin_telegram_bot_token'])) {
            SystemSetting::set('telegram_bot_token', $data['admin_telegram_bot_token'], 'communication');
        }

        if (! empty($data['admin_telegram_bot_username'])) {
            $username = ltrim($data['admin_telegram_bot_username'], '@');
            SystemSetting::set('telegram_bot_username', $username, 'communication');
        }

        if (! empty($data['admin_telegram_chat_id'])) {
            SystemSetting::set('telegram_chat_id', $data['admin_telegram_chat_id'], 'communication');
        }

        // --- E-Mail / SMTP ---
        SystemSetting::set('smtp_host', $data['smtp_host'] ?? '', 'mail');
        SystemSetting::set('smtp_port', $data['smtp_port'] ?? '587', 'mail');
        SystemSetting::set('smtp_encryption', $data['smtp_encryption'] ?? 'tls', 'mail');
        SystemSetting::set('smtp_username', $data['smtp_username'] ?? '', 'mail');

        if (! empty($data['smtp_password'])) {
            SystemSetting::set('smtp_password', $data['smtp_password'], 'mail');
        }

        SystemSetting::set('mail_from_address', $data['mail_from_address'] ?? '', 'mail');
        SystemSetting::set('mail_from_name', $data['mail_from_name'] ?? '', 'mail');

        Notification::make()
            ->title('Einstellungen gespeichert')
            ->success()
            ->send();
    }

    /**
     * System-SMTP testen.
     */
    public function testSmtp(): void
    {
        $this->save();

        $config = $this->getSystemSmtpConfig();

        if (! $config) {
            Notification::make()
                ->title('Keine SMTP-Konfiguration')
                ->body('Bitte konfigurieren Sie zuerst den SMTP-Server.')
                ->danger()
                ->send();
            return;
        }

        try {
            $factory = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory();
            $scheme = match ($config['encryption']) {
                'ssl', 'SSL/TLS' => 'smtps',
                'tls', 'STARTTLS' => 'smtp',
                default => 'smtp',
            };

            $dsn = new \Symfony\Component\Mailer\Transport\Dsn(
                $scheme,
                $config['host'],
                $config['username'],
                $config['password'],
                (int) $config['port'],
                ['verify_peer' => '0'],
            );

            $transport = $factory->create($dsn);
            $transport->start();
            $transport->stop();

            Notification::make()
                ->title('SMTP-Verbindung erfolgreich!')
                ->body('Die Verbindung zum System-Mailserver wurde hergestellt.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('SMTP-Verbindung fehlgeschlagen')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('send_test_email')
                ->label('Test-Mail senden')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->form([
                    TextInput::make('test_email')
                        ->label('Empfaenger-Adresse')
                        ->email()
                        ->required()
                        ->default(fn () => auth()->user()->email)
                        ->prefixIcon('heroicon-o-envelope'),
                ])
                ->modalHeading('System Test-Mail senden')
                ->modalDescription('Sendet eine Test-Mail ueber den System-SMTP-Server.')
                ->modalSubmitActionLabel('Jetzt senden')
                ->action(function (array $data) {
                    $this->save();

                    try {
                        $config = $this->getSystemSmtpConfig();
                        if (! $config) {
                            throw new \Exception('Kein System-SMTP konfiguriert.');
                        }

                        $fromAddress = $config['from_address'] ?: 'noreply@rosi-system.de';
                        $fromName = $config['from_name'] ?: 'ROSI System';

                        // Dynamischen System-Mailer konfigurieren
                        config([
                            'mail.mailers.system_dynamic' => [
                                'transport' => 'smtp',
                                'host' => $config['host'],
                                'port' => (int) $config['port'],
                                'username' => $config['username'],
                                'password' => $config['password'],
                                'encryption' => in_array($config['encryption'], ['ssl', 'SSL/TLS']) ? 'ssl' : 'tls',
                                'verify_peer' => false,
                                'verify_peer_name' => false,
                                'stream' => [
                                    'ssl' => [
                                        'allow_self_signed' => true,
                                        'verify_peer' => false,
                                        'verify_peer_name' => false,
                                    ],
                                ],
                            ],
                        ]);

                        $mailable = new \Illuminate\Mail\Mailable();
                        $mailable->subject('ROSI System - SMTP Test erfolgreich')
                            ->from($fromAddress, $fromName)
                            ->html('<div style="font-family: sans-serif; max-width: 500px; margin: 0 auto; padding: 32px;">
                                <h2 style="color: #4f46e5;">System-SMTP erfolgreich konfiguriert!</h2>
                                <p style="color: #374151;">Diese Test-Mail wurde ueber den <strong>System-SMTP-Server</strong> versendet (Admin-Bereich).</p>
                                <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 24px 0;">
                                <p style="color: #9ca3af; font-size: 12px;">Gesendet ueber ' . ($config['host'] ?? '-') . ':' . ($config['port'] ?? '-') . '</p>
                            </div>');

                        \Illuminate\Support\Facades\Mail::mailer('system_dynamic')
                            ->to($data['test_email'])
                            ->send($mailable);

                        Notification::make()
                            ->title('Test-Mail versendet!')
                            ->body('Die Test-Mail wurde an ' . $data['test_email'] . ' gesendet.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Test-Mail fehlgeschlagen')
                            ->body(substr($e->getMessage(), 0, 200))
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('test_smtp')
                ->label('SMTP testen')
                ->icon('heroicon-o-signal')
                ->color('info')
                ->action('testSmtp'),

            Action::make('fetch_chat_id')
                ->label('Chat-ID ermitteln')
                ->icon('heroicon-o-magnifying-glass')
                ->color('warning')
                ->action('fetchTelegramChatId'),

            Action::make('send_test_telegram')
                ->label('Test-Nachricht senden')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->form([
                    TextInput::make('test_message')
                        ->label('Nachricht')
                        ->default('Dies ist eine Test-Nachricht vom ROSI Admin-System.')
                        ->required()
                        ->maxLength(500),
                ])
                ->modalHeading('Telegram Test-Nachricht')
                ->modalDescription('Sendet eine Nachricht ueber den Admin-Telegram-Bot.')
                ->modalSubmitActionLabel('Senden')
                ->action(function (array $data) {
                    $token = $this->getAdminTelegramToken();
                    $chatId = SystemSetting::get('telegram_chat_id', null, 'communication');

                    if (! $token) {
                        Notification::make()
                            ->title('Kein Bot-Token')
                            ->body('Bitte konfigurieren Sie zuerst einen Bot-Token.')
                            ->danger()
                            ->send();
                        return;
                    }

                    if (! $chatId) {
                        Notification::make()
                            ->title('Keine Chat-ID')
                            ->body('Bitte ermitteln Sie zuerst Ihre Chat-ID (schreiben Sie dem Bot und klicken Sie "Chat-ID ermitteln").')
                            ->danger()
                            ->send();
                        return;
                    }

                    $service = new TelegramService($token);
                    $result = $service->sendMessage((int) $chatId, $data['test_message']);

                    if ($result) {
                        Notification::make()
                            ->title('Nachricht gesendet!')
                            ->body('Die Test-Nachricht wurde an Chat-ID ' . $chatId . ' gesendet.')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Senden fehlgeschlagen')
                            ->body('Pruefen Sie Bot-Token und Chat-ID.')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    /**
     * Chat-ID ueber getUpdates von Telegram holen.
     * Der Admin muss dem Bot zuerst eine Nachricht geschrieben haben.
     */
    public function fetchTelegramChatId(): void
    {
        $token = $this->getAdminTelegramToken();

        if (! $token) {
            Notification::make()
                ->title('Kein Bot-Token')
                ->body('Bitte speichern Sie zuerst einen Bot-Token.')
                ->danger()
                ->send();
            return;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withoutVerifying()
                ->timeout(10)
                ->get("https://api.telegram.org/bot{$token}/getUpdates");

            $data = $response->json();

            if (! ($data['ok'] ?? false) || empty($data['result'])) {
                Notification::make()
                    ->title('Keine Nachrichten gefunden')
                    ->body('Bitte schreiben Sie dem Bot zuerst eine Nachricht in Telegram (z.B. /start) und versuchen Sie es erneut.')
                    ->warning()
                    ->send();
                return;
            }

            // Letzte Nachricht nehmen
            $lastUpdate = end($data['result']);
            $chatId = $lastUpdate['message']['chat']['id'] ?? null;
            $firstName = $lastUpdate['message']['chat']['first_name'] ?? '';
            $username = $lastUpdate['message']['chat']['username'] ?? '';

            if (! $chatId) {
                Notification::make()
                    ->title('Chat-ID nicht erkannt')
                    ->body('Konnte keine Chat-ID aus den Updates extrahieren.')
                    ->danger()
                    ->send();
                return;
            }

            // Chat-ID ins Formular und in DB speichern
            $this->data['admin_telegram_chat_id'] = (string) $chatId;
            SystemSetting::set('telegram_chat_id', (string) $chatId, 'communication');

            $who = $firstName ?: $username ?: 'Unbekannt';

            Notification::make()
                ->title("Chat-ID gefunden: {$chatId}")
                ->body("Benutzer: {$who}" . ($username ? " (@{$username})" : ''))
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Fehler')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Admin-Telegram-Token lesen (aus Formular oder DB).
     */
    private function getAdminTelegramToken(): ?string
    {
        $formToken = $this->data['admin_telegram_bot_token'] ?? '';

        if (! empty($formToken)) {
            // Neuen Token zuerst speichern
            SystemSetting::set('telegram_bot_token', $formToken, 'communication');
            return $formToken;
        }

        return SystemSetting::get('telegram_bot_token', null, 'communication');
    }

    /**
     * System-SMTP-Config aus system_settings lesen.
     */
    private function getSystemSmtpConfig(): ?array
    {
        $host = SystemSetting::get('smtp_host', null, 'mail');

        if (! $host) {
            return null;
        }

        return [
            'host' => $host,
            'port' => SystemSetting::get('smtp_port', '587', 'mail'),
            'username' => SystemSetting::get('smtp_username', '', 'mail'),
            'password' => SystemSetting::get('smtp_password', '', 'mail'),
            'encryption' => SystemSetting::get('smtp_encryption', 'tls', 'mail'),
            'from_address' => SystemSetting::get('mail_from_address', '', 'mail'),
            'from_name' => SystemSetting::get('mail_from_name', '', 'mail'),
        ];
    }

    private function detectProvider(string $smtpHost): ?string
    {
        if (! $smtpHost) {
            return null;
        }

        foreach (self::PROVIDERS as $key => $provider) {
            if ($key !== 'custom' && $provider['smtp_host'] === $smtpHost) {
                return $key;
            }
        }

        return 'custom';
    }
}
