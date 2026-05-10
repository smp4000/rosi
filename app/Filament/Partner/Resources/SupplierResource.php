<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\SupplierResource\Pages;
use App\Filament\Partner\Resources\SupplierResource\RelationManagers;
use App\Models\Supplier;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Allgemeine Lieferanten-Verwaltung (modul-uebergreifend).
 * Pro Tankstelle wird ueber Pivot supplier_stations eine Kundennummer
 * hinterlegt.
 */
class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Lieferanten';

    protected static ?string $modelLabel = 'Lieferant';

    protected static ?string $pluralModelLabel = 'Lieferanten';

    protected static string|\UnitEnum|null $navigationGroup = 'Stammdaten';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Stammdaten')
                ->icon('heroicon-o-identification')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('company_name')->label('Firma')->required()->maxLength(255),
                        TextInput::make('short_code')->label('Kuerzel')->maxLength(20)
                            ->helperText('z.B. PVG, PressData'),
                        TextInput::make('supplier_number')->label('Lieferanten-Nr')->maxLength(50),
                    ]),
                    Grid::make(3)->schema([
                        TextInput::make('vat_id')->label('USt-IdNr.')->maxLength(30),
                        Select::make('category')->label('Kategorie')->options([
                            'newspaper' => 'Zeitungen',
                            'beverages' => 'Getraenke',
                            'food' => 'Lebensmittel',
                            'fuel' => 'Kraftstoffe',
                            'cleaning' => 'Reinigung',
                            'other' => 'Sonstiges',
                        ]),
                        Toggle::make('is_active')->label('Aktiv')->default(true),
                    ]),
                ]),

            Section::make('Kontakt')
                ->icon('heroicon-o-envelope')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('contact_first_name')->label('Vorname')->maxLength(100),
                        TextInput::make('contact_last_name')->label('Nachname')->maxLength(100),
                    ]),
                    Grid::make(3)->schema([
                        TextInput::make('contact_email')->label('E-Mail')->email()->maxLength(150),
                        TextInput::make('contact_phone')->label('Telefon')->maxLength(50),
                        TextInput::make('contact_mobile')->label('Mobil')->maxLength(50),
                    ]),
                ]),

            Section::make('Adresse')
                ->icon('heroicon-o-map-pin')
                ->schema([
                    TextInput::make('street')->label('Strasse')->maxLength(255),
                    Grid::make(3)->schema([
                        TextInput::make('zip')->label('PLZ')->maxLength(10),
                        TextInput::make('city')->label('Stadt')->maxLength(100),
                        TextInput::make('country')->label('Land')->default('DE')->maxLength(5),
                    ]),
                ]),

            Section::make('Notizen')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Textarea::make('notes')->label('Notizen')->rows(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')->label('Firma')->searchable()->sortable()->weight('bold'),
                TextColumn::make('short_code')->label('Kuerzel')->badge(),
                TextColumn::make('category')->label('Kategorie')
                    ->formatStateUsing(fn (?string $s) => match ($s) {
                        'newspaper' => 'Zeitungen', 'beverages' => 'Getraenke',
                        'food' => 'Lebensmittel', 'fuel' => 'Kraftstoffe',
                        'cleaning' => 'Reinigung', 'other' => 'Sonstiges',
                        default => '—',
                    })
                    ->badge()->toggleable(),
                TextColumn::make('stations_count')->label('Tankstellen')->counts('stations')->alignCenter(),
                TextColumn::make('vat_id')->label('USt-IdNr.')->fontFamily('mono')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('contact_email')->label('E-Mail')->toggleable(),
                IconColumn::make('is_active')->label('Aktiv')->boolean(),
            ])
            ->defaultSort('company_name')
            ->filters([
                SelectFilter::make('category')->label('Kategorie')->options([
                    'newspaper' => 'Zeitungen',
                    'beverages' => 'Getraenke',
                    'food' => 'Lebensmittel',
                    'fuel' => 'Kraftstoffe',
                    'cleaning' => 'Reinigung',
                    'other' => 'Sonstiges',
                ]),
                TernaryFilter::make('is_active')->label('Aktiv'),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\StationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
            'view' => Pages\ViewSupplier::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', auth()->user()?->tenant_id);
    }
}
