<?php

namespace App\Filament\Partner\Pages;

use App\Models\TenantSetting;
use App\Services\TelegramService;
use App\Services\TenantMailerService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Kommunikations-Einstellungen: Chat, Telegram und E-Mail/SMTP.
 * Zwei Tabs: Kommunikation (Chat + Telegram) und E-Mail (SMTP).
 */
class MessagingSettings extends Page implements HasForms
{
    use \App\Filament\Concerns\HasPageCatalogPermission;

    /** Permission fuer den Seitenzugriff (Rollen-Matrix) */
    protected static string $accessPermission = 'partner.settings.manage';

    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Kommunikation';

    protected static ?string $title = 'Kommunikations-Einstellungen';

    protected static ?string $slug = 'messaging-settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Einstellungen';

    protected static ?int $navigationSort = 89;

    protected string $view = 'filament.partner.pages.messaging-settings';

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
        $tenant = auth()->user()->tenant;
        $settings = $tenant->settings ?? [];

        // Mail-Settings aus TenantSetting
        $mailSettings = TenantSetting::getGroup('mail');
        $smtpHost = $mailSettings['smtp_host'] ?? '';
        $this->hasStoredPassword = ! empty($mailSettings['smtp_password'] ?? '');

        $this->form->fill([
            // Tab: Kommunikation
            'chat_enabled' => data_get($settings, 'messaging.chat_enabled', true),
            'telegram_enabled' => data_get($settings, 'messaging.telegram_enabled', false),
            'telegram_bot_token' => '',
            'telegram_bot_username' => data_get($settings, 'messaging.telegram_bot_username', ''),

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
                Tabs::make('settings_tabs')
                    ->tabs([
                        Tabs\Tab::make(__('partner.messaging.tab_communication'))
                            ->icon('heroicon-o-chat-bubble-left-right')
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
                                    ->columns(2)
                                    ->schema([
                                        Toggle::make('telegram_enabled')
                                            ->label('Telegram aktivieren')
                                            ->helperText('Aktiviert Telegram als zusaetzlichen Kommunikationskanal.')
                                            ->live()
                                            ->columnSpanFull(),

                                        Placeholder::make('telegram_anleitung')
                                            ->label('')
                                            ->content(new HtmlString('
                                                <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px; padding: 16px;">
                                                    <p style="font-weight: 600; color: #0369a1; margin-bottom: 8px;">Telegram-Bot erstellen:</p>
                                                    <ol style="color: #475569; font-size: 14px; padding-left: 20px; margin: 0;">
                                                        <li>Oeffnen Sie Telegram und suchen Sie <strong>@BotFather</strong></li>
                                                        <li>Senden Sie <code>/newbot</code> und folgen Sie den Anweisungen</li>
                                                        <li>Kopieren Sie den <strong>Bot-Token</strong> und fuegen Sie ihn unten ein</li>
                                                        <li>Klicken Sie auf <strong>"Telegram testen"</strong></li>
                                                    </ol>
                                                </div>
                                            '))
                                            ->visible(fn (Get $get) => $get('telegram_enabled'))
                                            ->columnSpanFull(),

                                        TextInput::make('telegram_bot_token')
                                            ->label('Bot-Token')
                                            ->placeholder('123456789:ABCdefGHIjklMNOpqrSTUvwxYZ')
                                            ->password()
                                            ->revealable()
                                            ->autocomplete('new-password')
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
                                                    <p style="font-weight: 600; color: #92400e; margin-bottom: 8px;">Datenschutz-Hinweis</p>
                                                    <p style="color: #78716c; font-size: 14px; margin: 0;">
                                                        Beim Einsatz von Telegram als Kommunikationskanal muessen Sie sicherstellen, dass
                                                        Ihre Mitarbeiter der Nutzung zustimmen (DSGVO Art. 6 Abs. 1 lit. a).
                                                        Die Einwilligung wird beim Verknuepfen des Telegram-Accounts automatisch protokolliert.
                                                        Bot-Tokens werden verschluesselt gespeichert.
                                                    </p>
                                                </div>
                                            ')),
                                    ]),
                            ]),

                        Tabs\Tab::make(__('partner.messaging.tab_email'))
                            ->icon('heroicon-o-envelope')
                            ->schema([
                                Section::make(__('partner.mail_settings.smtp_section'))
                                    ->icon('heroicon-o-server-stack')
                                    ->columns(3)
                                    ->schema([
                                        Select::make('provider')
                                            ->label(__('partner.mail_settings.provider'))
                                            ->options(collect(self::PROVIDERS)->mapWithKeys(fn ($p, $key) => [$key => $p['label']]))
                                            ->placeholder(__('partner.mail_settings.provider_placeholder'))
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

                                        Placeholder::make('provider_info')
                                            ->label('')
                                            ->content(fn (Get $get) => new HtmlString($this->getProviderHint($get('provider'))))
                                            ->visible(fn (Get $get) => $get('provider') && $get('provider') !== 'custom')
                                            ->columnSpanFull(),

                                        TextInput::make('smtp_host')
                                            ->label(__('partner.mail_settings.smtp_host'))
                                            ->placeholder('smtp.example.com')
                                            ->prefixIcon('heroicon-o-server-stack')
                                            ->maxLength(255),

                                        TextInput::make('smtp_port')
                                            ->label(__('partner.mail_settings.smtp_port'))
                                            ->numeric()
                                            ->default(587)
                                            ->placeholder('587')
                                            ->prefixIcon('heroicon-o-hashtag'),

                                        Select::make('smtp_encryption')
                                            ->label(__('partner.mail_settings.smtp_encryption'))
                                            ->options([
                                                'tls' => 'STARTTLS (587)',
                                                'ssl' => 'SSL/TLS (465)',
                                                'none' => __('partner.mail_settings.no_encryption'),
                                            ])
                                            ->default('tls'),

                                        TextInput::make('smtp_username')
                                            ->label(__('partner.mail_settings.smtp_username'))
                                            ->placeholder('user@example.com')
                                            ->prefixIcon('heroicon-o-user')
                                            ->maxLength(255),

                                        TextInput::make('smtp_password')
                                            ->label(__('partner.mail_settings.smtp_password'))
                                            ->password()
                                            ->revealable()
                                            ->autocomplete('new-password')
                                            ->placeholder(fn () => $this->hasStoredPassword ? '••••••••' : '')
                                            ->helperText($this->hasStoredPassword
                                                ? __('partner.mail_settings.smtp_password_stored')
                                                : __('partner.mail_settings.smtp_password_hint'))
                                            ->prefixIcon('heroicon-o-lock-closed')
                                            ->maxLength(255),
                                    ]),

                                Section::make(__('partner.mail_settings.sender_section'))
                                    ->icon('heroicon-o-identification')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('mail_from_address')
                                            ->label(__('partner.mail_settings.from_address'))
                                            ->email()
                                            ->placeholder('rechnung@meine-tankstelle.de')
                                            ->prefixIcon('heroicon-o-envelope'),

                                        TextInput::make('mail_from_name')
                                            ->label(__('partner.mail_settings.from_name'))
                                            ->placeholder('Aral Tankstelle Musterstadt')
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

    /**
     * Alles speichern (beide Tabs).
     */
    public function save(): void
    {
        $data = $this->form->getState();
        $tenant = auth()->user()->tenant;

        // --- Kommunikation ---
        $tenant->setMessagingSetting('chat_enabled', $data['chat_enabled']);
        $tenant->setMessagingSetting('telegram_enabled', $data['telegram_enabled']);

        if (! empty($data['telegram_bot_token'])) {
            $tenant->setTelegramBotToken($data['telegram_bot_token']);
        }

        if (! empty($data['telegram_bot_username'])) {
            $username = ltrim($data['telegram_bot_username'], '@');
            $tenant->setMessagingSetting('telegram_bot_username', $username);
        }

        // --- E-Mail / SMTP ---
        TenantSetting::set('smtp_host', $data['smtp_host'] ?? '', null, 'mail');
        TenantSetting::set('smtp_port', $data['smtp_port'] ?? '587', null, 'mail');
        TenantSetting::set('smtp_encryption', $data['smtp_encryption'] ?? 'tls', null, 'mail');
        TenantSetting::set('smtp_username', $data['smtp_username'] ?? '', null, 'mail');

        if (! empty($data['smtp_password'])) {
            TenantSetting::set('smtp_password', $data['smtp_password'], null, 'mail');
        }

        TenantSetting::set('mail_from_address', $data['mail_from_address'] ?? '', null, 'mail');
        TenantSetting::set('mail_from_name', $data['mail_from_name'] ?? '', null, 'mail');

        Notification::make()
            ->title('Einstellungen gespeichert')
            ->success()
            ->send();
    }

    /**
     * Telegram-Verbindung testen.
     */
    public function testTelegram(): void
    {
        $data = $this->form->getState();
        $token = $data['telegram_bot_token'];

        if (empty($token)) {
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
     * SMTP-Verbindung testen.
     */
    public function testSmtp(): void
    {
        $this->save();

        $result = TenantMailerService::testConnection();

        if ($result === true) {
            Notification::make()
                ->title(__('partner.mail_settings.test_success'))
                ->body(__('partner.mail_settings.test_success_body'))
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title(__('partner.mail_settings.test_failed'))
                ->body($result)
                ->danger()
                ->send();
        }
    }

    /**
     * Webhook bei Telegram einrichten.
     */
    protected function setupWebhook(string $token, $tenant): void
    {
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
            Action::make('send_test_email')
                ->label(__('partner.mail_settings.send_test'))
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->form([
                    TextInput::make('test_email')
                        ->label(__('partner.mail_settings.test_email_label'))
                        ->email()
                        ->required()
                        ->default(fn () => auth()->user()->email)
                        ->prefixIcon('heroicon-o-envelope'),
                ])
                ->modalHeading(__('partner.mail_settings.test_modal_heading'))
                ->modalDescription(__('partner.mail_settings.test_modal_desc'))
                ->modalSubmitActionLabel(__('partner.mail_settings.test_modal_send'))
                ->action(function (array $data) {
                    $this->save();

                    try {
                        $config = TenantMailerService::getSmtpConfig();
                        $fromAddress = $config['from_address'] ?? $this->data['mail_from_address'] ?? 'noreply@example.com';
                        $fromName = $config['from_name'] ?? $this->data['mail_from_name'] ?? 'ROSI';

                        $mailable = new \Illuminate\Mail\Mailable();
                        $mailable->subject(__('partner.mail_settings.test_email_subject'))
                            ->from($fromAddress, $fromName)
                            ->html('<div style="font-family: sans-serif; max-width: 500px; margin: 0 auto; padding: 32px;">
                                <h2 style="color: #1d4ed8;">' . __('partner.mail_settings.test_email_heading') . '</h2>
                                <p style="color: #374151;">' . __('partner.mail_settings.test_email_body') . '</p>
                                <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 24px 0;">
                                <p style="color: #9ca3af; font-size: 12px;">
                                    ' . __('partner.mail_settings.test_email_footer', [
                                        'host' => $this->data['smtp_host'] ?? '-',
                                        'port' => $this->data['smtp_port'] ?? '-',
                                    ]) . '
                                </p>
                            </div>');

                        TenantMailerService::send($mailable, $data['test_email']);

                        Notification::make()
                            ->title(__('partner.mail_settings.test_email_sent'))
                            ->body(__('partner.mail_settings.test_email_sent_body', ['email' => $data['test_email']]))
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title(__('partner.mail_settings.test_email_failed'))
                            ->body(substr($e->getMessage(), 0, 200))
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('test_smtp')
                ->label(__('partner.mail_settings.test_button'))
                ->icon('heroicon-o-signal')
                ->color('info')
                ->action('testSmtp'),

            Action::make('test_telegram')
                ->label('Telegram testen')
                ->icon('heroicon-o-signal')
                ->color('info')
                ->action('testTelegram'),
        ];
    }

    /**
     * Provider anhand des SMTP-Hosts erkennen.
     */
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

    /**
     * Provider-spezifische Hinweise.
     */
    private function getProviderHint(?string $provider): string
    {
        $hints = [
            'gmail' => '<div style="background: #fef3c7; border: 1px solid #fde68a; border-radius: 8px; padding: 12px;">
                <strong>Hinweis:</strong> Gmail erfordert ein <strong>App-Passwort</strong> statt Ihres normalen Passworts.
                Aktivieren Sie die 2-Faktor-Authentifizierung und erstellen Sie ein App-Passwort unter
                <em>Google-Konto &rarr; Sicherheit &rarr; App-Passwoerter</em>.</div>',

            'icloud' => '<div style="background: #fef3c7; border: 1px solid #fde68a; border-radius: 8px; padding: 12px;">
                <strong>Hinweis:</strong> iCloud erfordert ein <strong>App-spezifisches Passwort</strong>.
                Erstellen Sie eines unter <em>appleid.apple.com &rarr; Anmeldung und Sicherheit &rarr; App-spezifische Passwoerter</em>.</div>',

            'office365' => '<div style="background: #fef3c7; border: 1px solid #fde68a; border-radius: 8px; padding: 12px;">
                <strong>Hinweis:</strong> Bei Microsoft 365 muss <strong>SMTP AUTH</strong> fuer Ihr Postfach aktiviert sein.
                Ihr Admin kann dies im Microsoft 365 Admin Center unter <em>Benutzer &rarr; E-Mail &rarr; E-Mail-Apps</em> aktivieren.</div>',

            'web_de' => '<div style="background: #fef3c7; border: 1px solid #fde68a; border-radius: 8px; padding: 12px;">
                <strong>Hinweis:</strong> Bei WEB.DE muss der <strong>SMTP-Zugang</strong> erst aktiviert werden:
                <em>Einstellungen &rarr; POP3/IMAP Abruf &rarr; POP3 und IMAP Zugriff erlauben</em>.</div>',

            'gmx' => '<div style="background: #fef3c7; border: 1px solid #fde68a; border-radius: 8px; padding: 12px;">
                <strong>Hinweis:</strong> Bei GMX muss der <strong>SMTP-Zugang</strong> erst aktiviert werden:
                <em>Einstellungen &rarr; POP3/IMAP Abruf &rarr; POP3 und IMAP Zugriff erlauben</em>.</div>',

            't_online' => '<div style="background: #fef3c7; border: 1px solid #fde68a; border-radius: 8px; padding: 12px;">
                <strong>Hinweis:</strong> T-Online erfordert ein separates <strong>E-Mail-Passwort</strong>.
                Erstellen Sie dieses im <em>Telekom Kundencenter &rarr; E-Mail &rarr; Passwort fuer E-Mail-Programme</em>.</div>',
        ];

        return $hints[$provider] ?? '';
    }
}
