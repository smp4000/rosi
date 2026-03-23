<?php

namespace App\Filament\Partner\Pages;

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
            'default_customer_email' => InvoiceSetting::get('default_customer_email', 'smp4000@me.com'),
        ];

        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('E-Mail-Versand')
                ->description('Einstellungen fuer den automatischen E-Mail-Versand')
                ->icon('heroicon-o-envelope')
                ->schema([
                    Toggle::make('bcc_enabled')
                        ->label('BCC-Kopie aktivieren')
                        ->helperText('Eine unsichtbare Kopie jeder E-Mail an die BCC-Adresse senden (Kunde sieht es nicht)')
                        ->live(),

                    TextInput::make('bcc_email')
                        ->label('BCC-Adresse')
                        ->email()
                        ->required(fn ($get) => $get('bcc_enabled'))
                        ->visible(fn ($get) => $get('bcc_enabled'))
                        ->helperText('Diese Adresse erhaelt eine unsichtbare Kopie jeder versendeten Rechnung')
                        ->prefixIcon('heroicon-o-envelope'),

                    TextInput::make('email_delay_seconds')
                        ->label('Verzoegerung zwischen E-Mails (Sekunden)')
                        ->numeric()
                        ->default(3)
                        ->minValue(1)
                        ->maxValue(30)
                        ->suffix('Sek.')
                        ->helperText('Strato erlaubt ca. 1 E-Mail alle 3 Sekunden. Standard: 3')
                        ->prefixIcon('heroicon-o-clock'),

                    Toggle::make('show_amount_in_email')
                        ->label('Betrag in E-Mail anzeigen')
                        ->helperText('Gesamtbetrag der Rechnung in der E-Mail anzeigen')
                        ->default(true),
                ]),

            Section::make('Kunden-Defaults')
                ->description('Standardwerte fuer neue Kunden')
                ->icon('heroicon-o-user-plus')
                ->schema([
                    TextInput::make('default_customer_email')
                        ->label('Standard-E-Mail fuer neue Kunden')
                        ->email()
                        ->helperText('Neue Kunden erhalten diese Platzhalter-Adresse bis eine echte eingetragen wird')
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
        InvoiceSetting::set('default_customer_email', $data['default_customer_email'] ?? 'smp4000@me.com');

        Notification::make()
            ->title('Einstellungen gespeichert')
            ->success()
            ->send();
    }
}
