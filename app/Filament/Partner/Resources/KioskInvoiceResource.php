<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\KioskInvoiceResource\Pages;
use App\Models\Kiosk\Invoice;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class KioskInvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Rechnungen';

    protected static ?string $modelLabel = 'Kiosk-Rechnung';

    protected static ?string $pluralModelLabel = 'Kiosk-Rechnungen';

    protected static string|\UnitEnum|null $navigationGroup = 'Kiosk';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('rechnungsnummer')->label('Rechnungsnr.')->searchable()->sortable(),
                TextColumn::make('rechnungsdatum')->label('Datum')->date('d.m.Y')->sortable(),
                TextColumn::make('lieferdatum_von')->label('Liefer-Von')->date('d.m.Y')->toggleable(),
                TextColumn::make('lieferdatum_bis')->label('Liefer-Bis')->date('d.m.Y')->toggleable(),
                TextColumn::make('order_lines_count')->label('Positionen')->counts('orderLines')->alignCenter(),
                TextColumn::make('gesamtbetrag')->label('Betrag')->money('EUR')->sortable(),
                TextColumn::make('filename')->label('Datei')->toggleable()->limit(30),
                TextColumn::make('created_at')->label('Importiert')->dateTime('d.m.Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('rechnungsdatum', 'desc')
            ->actions([
                Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKioskInvoices::route('/'),
            'view' => Pages\ViewKioskInvoice::route('/{record}'),
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
