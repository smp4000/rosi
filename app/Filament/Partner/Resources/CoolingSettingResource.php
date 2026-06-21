<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\CoolingSettingResource\Pages;
use App\Models\CoolingSetting;
use App\Models\GasStation;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Mobile-Alerts-Einstellungen (phoneid) pro Mandant / Station.
 */
class CoolingSettingResource extends Resource
{
    use \App\Filament\Concerns\HasCatalogPermissions;

    protected static ?string $permissionKey = 'partner.temperature';

    protected static ?string $model = CoolingSetting::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Temperatur';

    protected static ?string $modelLabel = 'Temperatur-Einstellung';

    protected static ?string $pluralModelLabel = 'Temperatur-Einstellungen';

    protected static ?int $navigationSort = 9;

    protected static function formFields(): array
    {
        return [
            Select::make('station_id')
                ->label('Tankstelle')
                ->options(fn () => GasStation::where('tenant_id', session('tenant_id'))->pluck('name', 'id'))
                ->placeholder('— Standard für alle Stationen —')
                ->helperText('Leer = gilt als Standard für den ganzen Betrieb.')
                ->searchable(),
            TextInput::make('phoneid')
                ->label('phoneid (Mobile Alerts)')
                ->helperText('Aus der Mobile-Alerts-App. Wird für Alarm-Flags genutzt.')
                ->maxLength(40),
            TextInput::make('base_url')
                ->label('Basis-URL (optional)')
                ->placeholder(CoolingSetting::DEFAULT_BASE_URL)
                ->maxLength(255),
            TextInput::make('poll_interval_min')
                ->label('Abruf-Intervall (Min)')
                ->numeric()
                ->default(5),
            Toggle::make('enabled')->label('Aktiv')->default(true),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('station.name')->label('Tankstelle')->placeholder('Standard (alle)'),
                TextColumn::make('phoneid')->label('phoneid')->placeholder('—'),
                TextColumn::make('poll_interval_min')->label('Intervall')->suffix(' Min'),
                IconColumn::make('enabled')->label('Aktiv')->boolean(),
            ])
            ->actions([
                EditAction::make()->form(static::formFields()),
                DeleteAction::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Einstellung hinzufügen')
                    ->form(static::formFields())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = session('tenant_id');
                        return $data;
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoolingSettings::route('/'),
        ];
    }
}
