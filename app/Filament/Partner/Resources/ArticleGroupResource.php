<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\ArticleGroupResource\Pages;
use App\Models\ArticleGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Partner-Resource fuer Artikelgruppen.
 * Sieht System-Eintraege (readonly) + eigene Tenant-Eintraege (editierbar).
 */
class ArticleGroupResource extends Resource
{
    protected static ?string $model = ArticleGroup::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Einstellungen';

    protected static ?string $modelLabel = 'Artikelgruppe';

    protected static ?string $pluralModelLabel = 'Artikelgruppen';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'article_group_04';

    // --- Autorisierung ---

    protected static function ensureTeamContext(): void
    {
        $user = auth()->user();
        if ($user?->tenant_id) {
            $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
            $registrar->setPermissionsTeamId($user->tenant_id);
        }
    }

    public static function canAccess(): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        return true;
    }

    public static function canEdit(Model $record): bool
    {
        // Partner kann nur eigene Eintraege bearbeiten
        return $record->tenant_id !== null && $record->tenant_id === auth()->user()?->tenant_id;
    }

    public static function canDelete(Model $record): bool
    {
        // Partner kann nur eigene Eintraege loeschen
        return $record->tenant_id !== null && $record->tenant_id === auth()->user()?->tenant_id;
    }

    // --- Query: System + eigene Tenant-Eintraege ---

    public static function getEloquentQuery(): Builder
    {
        $tenantId = auth()->user()?->tenant_id;

        return parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->where(function (Builder $query) use ($tenantId) {
                $query->whereNull('tenant_id');
                if ($tenantId) {
                    $query->orWhere('tenant_id', $tenantId);
                }
            });
    }

    // --- Formular ---

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

            // MwSt
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
        ]);
    }

    // --- Tabelle ---

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
                        default => 'gray',
                    }),

                TextColumn::make('revenue_account')
                    ->label('EKW-Konto')
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

                TextColumn::make('is_system')
                    ->label('Typ')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'System' : 'Eigene')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'gray' : 'primary'),
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
                    ->label('Typ')
                    ->trueLabel('Nur System')
                    ->falseLabel('Nur eigene'),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->visible(fn (Model $record): bool => static::canEdit($record)),
                Actions\DeleteAction::make()
                    ->visible(fn (Model $record): bool => static::canDelete($record)),
            ])
            ->bulkActions([]);
    }

    // --- Seiten ---

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticleGroups::route('/'),
            'create' => Pages\CreateArticleGroup::route('/create'),
            'edit' => Pages\EditArticleGroup::route('/{record}/edit'),
        ];
    }
}
