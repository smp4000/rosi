<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\NewspaperSupplierResource\Pages;
use App\Filament\Partner\Resources\NewspaperSupplierResource\RelationManagers;
use App\Models\Kiosk\Supplier;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NewspaperSupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Lieferanten';

    protected static ?string $modelLabel = 'Lieferant';

    protected static ?string $pluralModelLabel = 'Lieferanten';

    protected static string|\UnitEnum|null $navigationGroup = 'Zeitungen';

    protected static ?int $navigationSort = 25;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Stammdaten')
                ->icon('heroicon-o-identification')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name')->label('Name')->required()->maxLength(100),
                        TextInput::make('short_code')->label('Kuerzel')->maxLength(20)
                            ->helperText('z.B. PVG, PressData. Wird bei Artikeln + Rechnungen genutzt.'),
                    ]),
                    Grid::make(3)->schema([
                        TextInput::make('vat_id')->label('USt-IdNr.')->maxLength(30),
                        TextInput::make('email')->label('E-Mail')->email()->maxLength(150),
                        TextInput::make('phone')->label('Telefon')->maxLength(50),
                    ]),
                    Textarea::make('address')->label('Adresse')->rows(2)->columnSpanFull(),
                    Toggle::make('is_active')->label('Aktiv')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()->sortable()->weight('bold'),
                TextColumn::make('short_code')->label('Kuerzel')->badge(),
                TextColumn::make('stations_count')->label('Tankstellen')->counts('stations')->alignCenter(),
                TextColumn::make('vat_id')->label('USt-IdNr.')->toggleable()->fontFamily('mono'),
                TextColumn::make('email')->label('E-Mail')->toggleable(),
                IconColumn::make('is_active')->label('Aktiv')->boolean(),
            ])
            ->defaultSort('name')
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
            'index' => Pages\ListNewspaperSuppliers::route('/'),
            'create' => Pages\CreateNewspaperSupplier::route('/create'),
            'edit' => Pages\EditNewspaperSupplier::route('/{record}/edit'),
            'view' => Pages\ViewNewspaperSupplier::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', auth()->user()?->tenant_id);
    }
}
