<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleGroupResource\Pages;
use App\Models\ArticleGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Admin-Resource fuer Artikelgruppen.
 * Sieht ALLE Gruppen (System + Partner-eigene).
 */
class ArticleGroupResource extends Resource
{
    protected static ?string $model = ArticleGroup::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Stammdaten';

    protected static ?string $modelLabel = 'Artikelgruppe';

    protected static ?string $pluralModelLabel = 'Artikelgruppen';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'article_group_04';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // Geschaeftsbereich
            TextInput::make('business_area_id')
                ->label('Geschäftsbereich-ID')
                ->numeric()
                ->nullable(),
            TextInput::make('business_area')
                ->label('Geschäftsbereich')
                ->maxLength(50),

            // EKW-Konto
            TextInput::make('revenue_account_id')
                ->label('EKW-Konto-ID')
                ->numeric()
                ->nullable(),
            TextInput::make('revenue_account')
                ->label('EKW-Konto')
                ->maxLength(50),

            // Warengruppe
            TextInput::make('product_group_id')
                ->label('Warengruppe-ID')
                ->numeric()
                ->nullable(),
            TextInput::make('product_group')
                ->label('Warengruppe')
                ->maxLength(50),

            // Artikelgruppe Ebene 4
            TextInput::make('article_group_04_id')
                ->label('Artikelgruppe 04 ID')
                ->numeric()
                ->nullable(),
            TextInput::make('article_group_04')
                ->label('Artikelgruppe 04')
                ->maxLength(50),

            // Artikelgruppe Ebene 3
            TextInput::make('article_group_03_id')
                ->label('Artikelgruppe 03 ID')
                ->numeric()
                ->nullable(),
            TextInput::make('article_group_03')
                ->label('Artikelgruppe 03')
                ->maxLength(50),

            // Artikelgruppe Ebene 2/1
            TextInput::make('article_group_02_01_id')
                ->label('Artikelgruppe 02/01 ID')
                ->numeric()
                ->nullable(),
            TextInput::make('article_group_02_01')
                ->label('Artikelgruppe 02/01')
                ->maxLength(50),

            // MwSt und Steuer
            Select::make('vat_category')
                ->label('MwSt-Kategorie')
                ->options([
                    'A' => 'A - 19% (volle MwSt)',
                    'B' => 'B - 7% (ermäßigte MwSt)',
                    'C' => 'C - 0% (ohne MwSt)',
                ])
                ->default('A')
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                    $rates = ['A' => 19.00, 'B' => 7.00, 'C' => 0.00];
                    $set('vat_rate', $rates[$state] ?? 19.00);
                }),
            TextInput::make('vat_rate')
                ->label('MwSt-Satz (%)')
                ->numeric()
                ->default(19.00)
                ->disabled()
                ->dehydrated(),

            Select::make('youth_protection')
                ->label('Jugendschutz')
                ->options([
                    '0' => 'Kein Jugendschutz',
                    '16' => 'Ab 16 Jahren',
                    '18' => 'Ab 18 Jahren',
                ])
                ->default('0'),
            TextInput::make('lease_rate')
                ->label('Pachtsatz')
                ->numeric()
                ->default(0.00)
                ->step(0.01),

            Toggle::make('is_system')
                ->label('System-Eintrag')
                ->helperText('System-Einträge können von Partnern nicht bearbeitet werden')
                ->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('business_area')
                    ->label('Geschäftsbereich')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Kraftstoffe' => 'danger',
                        'Oele/sonst' => 'warning',
                        'Kfz-Service' => 'info',
                        'Shop' => 'success',
                        'Waschen' => 'primary',
                        'Sonstiges' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('revenue_account')
                    ->label('EKW-Konto')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('product_group')
                    ->label('Warengruppe')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('article_group_04')
                    ->label('Artikelgruppe')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('article_group_03')
                    ->label('Untergruppe')
                    ->searchable()
                    ->toggleable(),

                BadgeColumn::make('vat_category')
                    ->label('MwSt')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'A' => '19%',
                        'B' => '7%',
                        'C' => '0%',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'A' => 'danger',
                        'B' => 'warning',
                        'C' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('youth_protection')
                    ->label('JuSchu')
                    ->formatStateUsing(fn (string $state): string => $state === '0' ? '-' : "ab {$state}")
                    ->toggleable(isToggledHiddenByDefault: true),

                BooleanColumn::make('is_system')
                    ->label('System')
                    ->sortable(),

                TextColumn::make('tenant.name')
                    ->label('Partner')
                    ->placeholder('System')
                    ->toggleable(),
            ])
            ->defaultSort('business_area_id', 'asc')
            ->filters([
                SelectFilter::make('business_area_id')
                    ->label('Geschäftsbereich')
                    ->options(ArticleGroup::getBusinessAreaOptions()),
                SelectFilter::make('vat_category')
                    ->label('MwSt-Kategorie')
                    ->options([
                        'A' => '19% (volle MwSt)',
                        'B' => '7% (ermäßigte MwSt)',
                        'C' => '0% (ohne MwSt)',
                    ]),
                TernaryFilter::make('is_system')
                    ->label('System-Einträge')
                    ->trueLabel('Nur System')
                    ->falseLabel('Nur Partner'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticleGroups::route('/'),
            'create' => Pages\CreateArticleGroup::route('/create'),
            'edit' => Pages\EditArticleGroup::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['business_area', 'revenue_account', 'product_group', 'article_group_04'];
    }
}
