<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\KioskArticleResource\Pages;
use App\Models\Kiosk\Article;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class KioskArticleResource extends Resource
{
    use \App\Filament\Concerns\HasCatalogPermissions;

    /** Katalog-Schluessel fuer die Rechte-Pruefung (Rollen-Matrix) */
    protected static ?string $permissionKey = 'partner.newspapers';

    protected static ?string $model = Article::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationLabel = 'Artikel';

    protected static ?string $modelLabel = 'Zeitung';

    protected static ?string $pluralModelLabel = 'Zeitungen';

    protected static string|\UnitEnum|null $navigationGroup = 'Zeitungen';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bezeichnung')
                    ->label('Bezeichnung')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->limit(40),
                TextColumn::make('objekt')
                    ->label('Objekt')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ean')
                    ->label('EAN')
                    ->searchable()
                    ->fontFamily('mono')
                    ->size('xs'),
                TextColumn::make('weekday')
                    ->label('Tag')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do',
                        5 => 'Fr', 6 => 'Sa', 7 => 'So',
                        default => '—',
                    })
                    ->toggleable(),
                TextColumn::make('aktueller_preis_brutto')
                    ->label('VKP')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('ek')
                    ->label('EK')
                    ->money('EUR')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('mwst_satz')
                    ->label('MwSt')
                    ->suffix(' %')
                    ->toggleable(),
                IconColumn::make('is_pending')
                    ->label('Pending')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('last_seen_at')
                    ->label('Zuletzt')
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('bezeichnung')
            ->filters([
                TernaryFilter::make('is_pending')
                    ->label('Pending'),
                SelectFilter::make('weekday')
                    ->label('Wochentag')
                    ->options([
                        1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch',
                        4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag',
                    ]),
            ])
            ->actions([
                Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKioskArticles::route('/'),
            'view' => Pages\ViewKioskArticle::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', auth()->user()?->tenant_id);
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
