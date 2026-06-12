<?php

namespace App\Filament\Partner\Pages;

use App\Models\TenantSetting;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Schicht-Einstellungen: Verhalten der Schichtabrechnung in der App.
 */
class ShiftSettings extends Page implements HasForms
{
    use \App\Filament\Concerns\HasPageCatalogPermission;

    /** Permission fuer den Seitenzugriff (Rollen-Matrix) */
    protected static string $accessPermission = 'partner.shift-settlements.edit';

    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Schicht';

    protected static ?string $title = 'Schicht-Einstellungen';

    protected static ?string $slug = 'shift-settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Einstellungen';

    protected static ?int $navigationSort = 88;

    protected string $view = 'filament.partner.pages.shift-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = TenantSetting::getGroup('shift');

        $this->form->fill([
            'auto_print_safe_label' => filter_var(
                $settings['auto_print_safe_label'] ?? '1',
                FILTER_VALIDATE_BOOLEAN,
            ),
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Tresor-Einlagen')
                    ->description('Verhalten beim Erfassen einer Tresor-Einlage in der App.')
                    ->icon('heroicon-o-lock-closed')
                    ->schema([
                        Toggle::make('auto_print_safe_label')
                            ->label('DYMO-Etikett automatisch drucken')
                            ->helperText('Wenn aktiviert, wird bei jeder Tresor-Einlage automatisch ein DYMO-Etikett gedruckt. Wenn deaktiviert, wird kein Etikett gedruckt.')
                            ->default(true),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        TenantSetting::set(
            'auto_print_safe_label',
            $data['auto_print_safe_label'] ? '1' : '0',
            null,
            'shift',
        );

        Notification::make()
            ->title('Einstellungen gespeichert')
            ->success()
            ->send();
    }
}
