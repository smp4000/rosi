<?php

namespace App\Filament\Partner\Pages;

use App\Models\TenantSetting;
use App\Services\TenantMailerService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * SMTP / Mail-Einstellungen pro Tenant.
 * Provider-Schnellauswahl befuellt SMTP-Felder automatisch.
 */
class MailSettingsPage extends Page implements HasForms
{
    use \App\Filament\Concerns\HasPageCatalogPermission;

    /** Permission fuer den Seitenzugriff (Rollen-Matrix) */
    protected static string $accessPermission = 'partner.settings.manage';

    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $slug = 'mail-settings';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.partner.pages.mail-settings';

    public array $data = [];

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

    public static function getNavigationLabel(): string
    {
        return __('partner.mail_settings.nav_label');
    }

    public function getTitle(): string
    {
        return __('partner.mail_settings.title');
    }

    public function mount(): void
    {
        $mailSettings = TenantSetting::getGroup('mail');

        $smtpHost = $mailSettings['smtp_host'] ?? '';

        $hasPassword = ! empty($mailSettings['smtp_password'] ?? '');
        $this->hasStoredPassword = $hasPassword;

        $this->data = [
            'provider' => $this->detectProvider($smtpHost),
            'smtp_host' => $smtpHost,
            'smtp_port' => $mailSettings['smtp_port'] ?? '587',
            'smtp_username' => $mailSettings['smtp_username'] ?? '',
            'smtp_password' => '',
            'smtp_encryption' => $mailSettings['smtp_encryption'] ?? 'tls',
            'mail_from_address' => $mailSettings['mail_from_address'] ?? '',
            'mail_from_name' => $mailSettings['mail_from_name'] ?? '',
        ];

        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
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
                        ->maxLength(255)
                        ->required(),

                    TextInput::make('smtp_port')
                        ->label(__('partner.mail_settings.smtp_port'))
                        ->numeric()
                        ->default(587)
                        ->placeholder('587')
                        ->prefixIcon('heroicon-o-hashtag')
                        ->required(),

                    Select::make('smtp_encryption')
                        ->label(__('partner.mail_settings.smtp_encryption'))
                        ->options([
                            'tls' => 'STARTTLS (587)',
                            'ssl' => 'SSL/TLS (465)',
                            'none' => __('partner.mail_settings.no_encryption'),
                        ])
                        ->default('tls')
                        ->required(),

                    TextInput::make('smtp_username')
                        ->label(__('partner.mail_settings.smtp_username'))
                        ->placeholder('user@example.com')
                        ->prefixIcon('heroicon-o-user')
                        ->maxLength(255)
                        ->required(),

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
                        ->prefixIcon('heroicon-o-envelope')
                        ->required(),

                    TextInput::make('mail_from_name')
                        ->label(__('partner.mail_settings.from_name'))
                        ->placeholder('Aral Tankstelle Musterstadt')
                        ->prefixIcon('heroicon-o-building-storefront')
                        ->maxLength(255),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->data;

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
            ->title(__('partner.mail_settings.saved'))
            ->success()
            ->send();
    }

    /**
     * SMTP-Verbindung testen.
     */
    public function testConnection(): void
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

            Action::make('test_connection')
                ->label(__('partner.mail_settings.test_button'))
                ->icon('heroicon-o-signal')
                ->color('info')
                ->action('testConnection'),
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
