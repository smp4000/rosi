<?php

namespace App\Filament\Partner\Resources\PlaceholderSettings;

use App\Filament\Partner\Resources\PlaceholderSettings\Pages\ManagePlaceholderSettings;
use App\Models\PlaceholderSetting;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class PlaceholderSettingResource extends Resource
{
    protected static ?string $model = PlaceholderSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $recordTitleAttribute = 'label';

    protected static string|\UnitEnum|null $navigationGroup = 'Einstellungen';

    protected static ?string $navigationLabel = 'Platzhalter';

    protected static ?string $modelLabel = 'Platzhalter';

    protected static ?string $pluralModelLabel = 'Platzhalter';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->label('Anzeigename')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('z.B. Name des Geschaeftsfuehrers')
                    ->columnSpan(2),
                TextInput::make('key')
                    ->label('Platzhalter-Schluessel')
                    ->required()
                    ->maxLength(100)
                    ->alphaDash()
                    ->prefix('{{')
                    ->suffix('}}')
                    ->placeholder('z.B. geschaeftsfuehrer_name')
                    ->helperText('Dieser Schluessel wird in Vorlagen als {{schluessel}} verwendet.')
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('tenant_id', session('tenant_id')))
                    ->disabled(fn ($record) => $record?->is_system),
                TextInput::make('value')
                    ->label('Wert')
                    ->maxLength(1000)
                    ->placeholder('Den Wert hier eintragen...')
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => ($get('type') ?? 'text') === 'text'),
                Textarea::make('value')
                    ->label('Wert')
                    ->rows(3)
                    ->placeholder('Den Wert hier eintragen...')
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => ($get('type') ?? 'text') === 'textarea'),
                Select::make('group')
                    ->label('Gruppe')
                    ->options(PlaceholderSetting::getGroupOptions())
                    ->default('custom')
                    ->required()
                    ->disabled(fn ($record) => $record?->is_system),
                Select::make('type')
                    ->label('Feldtyp')
                    ->options(PlaceholderSetting::getTypeOptions())
                    ->default('text')
                    ->required()
                    ->live(),
                Textarea::make('description')
                    ->label('Beschreibung / Hilfetext')
                    ->rows(2)
                    ->placeholder('Optionaler Hilfetext fuer andere Benutzer')
                    ->columnSpan(2),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->defaultGroup(
                Group::make('group')
                    ->label('Gruppe')
                    ->getTitleFromRecordUsing(fn ($record) => PlaceholderSetting::getGroupOptions()[$record->group] ?? $record->group)
                    ->collapsible()
            )
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('label')
                    ->label('Bezeichnung')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('key')
                    ->label('Platzhalter')
                    ->formatStateUsing(fn ($state) => '{{' . $state . '}}')
                    ->fontFamily('mono')
                    ->size('sm')
                    ->color('gray')
                    ->copyable()
                    ->copyableState(fn ($state) => '{{' . $state . '}}'),
                TextColumn::make('value')
                    ->label('Wert')
                    ->limit(50)
                    ->placeholder('— nicht gesetzt —')
                    ->color(fn ($state) => $state ? null : 'danger'),
                IconColumn::make('is_system')
                    ->label('System')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('gray')
                    ->falseColor('success'),
            ])
            ->filters([
                SelectFilter::make('group')
                    ->label('Gruppe')
                    ->options(PlaceholderSetting::getGroupOptions()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn ($record) => ! $record->is_system),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Neuer Platzhalter')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = session('tenant_id');

                        return $data;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Keine Platzhalter vorhanden')
            ->emptyStateDescription('Platzhalter werden automatisch beim ersten Aufruf erstellt.')
            ->emptyStateIcon('heroicon-o-adjustments-horizontal');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePlaceholderSettings::route('/'),
        ];
    }
}
