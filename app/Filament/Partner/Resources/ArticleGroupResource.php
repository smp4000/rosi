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

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'article_group_04';

    // --- Getter-Overrides fuer uebersetzte Labels ---

    public static function getModelLabel(): string
    {
        return __('partner.article_group.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('partner.article_group.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('partner.article_group.nav_group');
    }

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
                ->label(__('partner.article_group.fields.business_area_id'))
                ->numeric()
                ->nullable(),
            TextInput::make('business_area')
                ->label(__('partner.article_group.fields.business_area'))
                ->maxLength(50),

            // EKW-Konto
            TextInput::make('revenue_account_id')
                ->label(__('partner.article_group.fields.ekw_account_id'))
                ->numeric()
                ->nullable(),
            TextInput::make('revenue_account')
                ->label(__('partner.article_group.fields.ekw_account'))
                ->maxLength(50),

            // Warengruppe
            TextInput::make('product_group_id')
                ->label(__('partner.article_group.fields.product_group_id'))
                ->numeric()
                ->nullable(),
            TextInput::make('product_group')
                ->label(__('partner.article_group.fields.product_group'))
                ->maxLength(50),

            // Artikelgruppe Ebene 4
            TextInput::make('article_group_04_id')
                ->label(__('partner.article_group.fields.group_04_id'))
                ->numeric()
                ->nullable(),
            TextInput::make('article_group_04')
                ->label(__('partner.article_group.fields.group_04'))
                ->maxLength(50),

            // Artikelgruppe Ebene 3
            TextInput::make('article_group_03_id')
                ->label(__('partner.article_group.fields.group_03_id'))
                ->numeric()
                ->nullable(),
            TextInput::make('article_group_03')
                ->label(__('partner.article_group.fields.group_03'))
                ->maxLength(50),

            // Artikelgruppe Ebene 2/1
            TextInput::make('article_group_02_01_id')
                ->label(__('partner.article_group.fields.group_02_01_id'))
                ->numeric()
                ->nullable(),
            TextInput::make('article_group_02_01')
                ->label(__('partner.article_group.fields.group_02_01'))
                ->maxLength(50),

            // MwSt
            Select::make('vat_category')
                ->label(__('partner.article_group.fields.vat_category'))
                ->options([
                    'A' => __('partner.article_group.vat_categories.A'),
                    'B' => __('partner.article_group.vat_categories.B'),
                    'C' => __('partner.article_group.vat_categories.C'),
                ])
                ->default('A')
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                    $rates = ['A' => 19.00, 'B' => 7.00, 'C' => 0.00];
                    $set('vat_rate', $rates[$state] ?? 19.00);
                }),
            TextInput::make('vat_rate')
                ->label(__('partner.article_group.fields.vat_rate'))
                ->numeric()
                ->default(19.00)
                ->disabled()
                ->dehydrated(),

            Select::make('youth_protection')
                ->label(__('partner.article_group.fields.youth_protection'))
                ->options([
                    '0' => __('partner.article_group.youth_protections.none'),
                    '16' => __('partner.article_group.youth_protections.16'),
                    '18' => __('partner.article_group.youth_protections.18'),
                ])
                ->default('0'),
            TextInput::make('lease_rate')
                ->label(__('partner.article_group.fields.lease_rate'))
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
                    ->label(__('partner.article_group.fields.business_area'))
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
                    ->label(__('partner.article_group.fields.ekw_account'))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('article_group_04')
                    ->label(__('partner.article_group.label'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('article_group_03')
                    ->label(__('partner.article_group.table.subgroup'))
                    ->searchable()
                    ->toggleable(),

                BadgeColumn::make('vat_category')
                    ->label(__('partner.article_group.table.vat'))
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
                    ->label(__('partner.article_group.table.type'))
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('partner.article_group.badges.system')
                        : __('partner.article_group.badges.custom'))
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'gray' : 'primary'),
            ])
            ->defaultSort('business_area_id', 'asc')
            ->filters([
                SelectFilter::make('business_area_id')
                    ->label(__('partner.article_group.fields.business_area'))
                    ->options(ArticleGroup::getBusinessAreaOptions()),
                SelectFilter::make('vat_category')
                    ->label(__('partner.article_group.fields.vat_category'))
                    ->options([
                        'A' => __('partner.article_group.filters.vat_A'),
                        'B' => __('partner.article_group.filters.vat_B'),
                        'C' => __('partner.article_group.filters.vat_C'),
                    ]),
                TernaryFilter::make('is_system')
                    ->label(__('partner.article_group.filters.type'))
                    ->trueLabel(__('partner.article_group.filters.type_system'))
                    ->falseLabel(__('partner.article_group.filters.type_custom')),
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
