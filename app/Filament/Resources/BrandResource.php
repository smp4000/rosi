<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BrandResource\Pages;
use App\Models\Brand;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Actions;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Admin-Resource fuer Tankstellen-Marken (nur Super-Admin).
 * Ermoeglicht die Verwaltung aller Marken inkl. Logo-Upload.
 */
class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Stammdaten';

    protected static ?string $modelLabel = 'Marke';

    protected static ?string $pluralModelLabel = 'Tankstellen-Marken';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    // --- Formular ---

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Marke')
                ->tabs([
                    Tab::make(__('admin.brand.tabs.stammdaten'))
                        ->icon('heroicon-o-tag')
                        ->schema([
                            TextInput::make('name')
                                ->label(__('admin.brand.fields.name'))
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true),
                            TextInput::make('slug')
                                ->label(__('admin.brand.fields.slug'))
                                ->maxLength(255)
                                ->unique(ignoreRecord: true)
                                ->helperText('Wird automatisch aus dem Namen generiert, wenn leer.'),
                            TextInput::make('sort_order')
                                ->label(__('admin.brand.fields.sort_order'))
                                ->numeric()
                                ->default(0)
                                ->minValue(0),
                            Toggle::make('is_active')
                                ->label(__('admin.brand.fields.is_active'))
                                ->default(true),
                        ])
                        ->columns(2),

                    Tab::make(__('admin.brand.tabs.logo'))
                        ->icon('heroicon-o-photo')
                        ->schema([
                            FileUpload::make('logo')
                                ->label(__('admin.brand.fields.logo'))
                                ->image()
                                ->disk('public')
                                ->directory('brands')
                                ->imagePreviewHeight('150')
                                ->maxSize(1024)
                                ->helperText('Max. 1 MB (PNG/SVG/JPG)')
                                ->columnSpanFull(),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    // --- Tabelle ---

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')
                    ->label('')
                    ->disk('public')
                    ->height(32)
                    ->width(32)
                    ->circular(),

                TextColumn::make('name')
                    ->label(__('admin.brand.fields.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('slug')
                    ->label(__('admin.brand.fields.slug'))
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('gas_stations_count')
                    ->label(__('admin.brand.fields.gas_stations_count'))
                    ->counts('gasStations')
                    ->sortable(),

                BooleanColumn::make('is_active')
                    ->label(__('admin.brand.fields.is_active'))
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label(__('admin.brand.fields.sort_order'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label(__('admin.brand.fields.created_at'))
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order', 'asc')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('admin.brand.fields.is_active'))
                    ->trueLabel('Nur aktive')
                    ->falseLabel('Nur inaktive'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    // --- Seiten ---

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBrands::route('/'),
            'create' => Pages\CreateBrand::route('/create'),
            'edit' => Pages\EditBrand::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }
}
