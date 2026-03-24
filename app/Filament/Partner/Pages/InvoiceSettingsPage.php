<?php

namespace App\Filament\Partner\Pages;

use App\Models\CorporateCustomer;
use App\Models\InvoiceSetting;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Einstellungen fuer den Rechnungsversand.
 * BCC, Verzoegerung, Betrag anzeigen, Standard-E-Mail.
 */
class InvoiceSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Rechnungsversand';

    protected static ?string $title = 'Rechnungsversand-Einstellungen';

    public function getTitle(): string
    {
        return __('partner.invoice_settings.title');
    }

    protected static ?string $slug = 'invoice-settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Einstellungen';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.partner.pages.invoice-settings';

    public array $data = [];

    public function mount(): void
    {
        $this->data = [
            'bcc_enabled' => InvoiceSetting::enabled('bcc_enabled'),
            'bcc_email' => InvoiceSetting::get('bcc_email', ''),
            'email_delay_seconds' => (int) InvoiceSetting::get('email_delay_seconds', 3),
            'show_amount_in_email' => InvoiceSetting::enabled('show_amount_in_email') || InvoiceSetting::get('show_amount_in_email') === null,
            'default_customer_email' => InvoiceSetting::get('default_customer_email', CorporateCustomer::PLACEHOLDER_EMAIL),
        ];

        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make(__('partner.invoice_settings.email_section'))
                ->description(__('partner.invoice_settings.email_section_desc'))
                ->icon('heroicon-o-envelope')
                ->schema([
                    Toggle::make('bcc_enabled')
                        ->label(__('partner.invoice_settings.bcc_enabled'))
                        ->helperText(__('partner.invoice_settings.bcc_hint'))
                        ->live(),

                    TextInput::make('bcc_email')
                        ->label(__('partner.invoice_settings.bcc_email'))
                        ->email()
                        ->required(fn ($get) => $get('bcc_enabled'))
                        ->visible(fn ($get) => $get('bcc_enabled'))
                        ->helperText(__('partner.invoice_settings.bcc_email_hint'))
                        ->prefixIcon('heroicon-o-envelope'),

                    TextInput::make('email_delay_seconds')
                        ->label(__('partner.invoice_settings.delay_label'))
                        ->numeric()
                        ->default(3)
                        ->minValue(1)
                        ->maxValue(30)
                        ->suffix(__('partner.common.sek'))
                        ->helperText(__('partner.invoice_settings.delay_hint'))
                        ->prefixIcon('heroicon-o-clock'),

                    Toggle::make('show_amount_in_email')
                        ->label(__('partner.invoice_settings.show_amount'))
                        ->helperText(__('partner.invoice_settings.show_amount_hint'))
                        ->default(true),
                ]),

            Section::make(__('partner.invoice_settings.customer_defaults'))
                ->description(__('partner.invoice_settings.customer_defaults_desc'))
                ->icon('heroicon-o-user-plus')
                ->schema([
                    TextInput::make('default_customer_email')
                        ->label(__('partner.invoice_settings.default_email'))
                        ->email()
                        ->helperText(__('partner.invoice_settings.default_email_hint'))
                        ->prefixIcon('heroicon-o-envelope'),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->data;

        InvoiceSetting::set('bcc_enabled', $data['bcc_enabled'] ? 'true' : 'false');
        InvoiceSetting::set('bcc_email', $data['bcc_email'] ?? '');
        InvoiceSetting::set('email_delay_seconds', (string) ($data['email_delay_seconds'] ?? 3));
        InvoiceSetting::set('show_amount_in_email', ($data['show_amount_in_email'] ?? true) ? 'true' : 'false');
        InvoiceSetting::set('default_customer_email', $data['default_customer_email'] ?? CorporateCustomer::PLACEHOLDER_EMAIL);

        Notification::make()
            ->title(__('partner.invoice_settings.saved'))
            ->success()
            ->send();
    }
}
