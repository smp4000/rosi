<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\VoucherReprintResource\Pages;
use App\Models\GasStation;
use App\Models\VoucherReprint;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Nachdruck-Protokoll fuer Gutschein-Etiketten (revisionssicher, read-only):
 * wer hat welche Nummer wann wohin nachgedruckt.
 */
class VoucherReprintResource extends Resource
{
    use \App\Filament\Concerns\HasCatalogPermissions;

    protected static ?string $permissionKey = 'partner.print';

    protected static ?string $model = VoucherReprint::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static string|\UnitEnum|null $navigationGroup = 'Drucken';

    protected static ?string $modelLabel = 'Nachdruck';

    protected static ?string $pluralModelLabel = 'Nachdruck-Protokoll';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', session('tenant_id'));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Zeitpunkt')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('voucher_number')
                    ->label('Gutscheinnummer')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('reprint_count')
                    ->label('Insgesamt nachgedruckt')
                    ->alignCenter()
                    ->getStateUsing(fn (VoucherReprint $r) => VoucherReprint::where('tenant_id', $r->tenant_id)
                        ->where('voucher_number', $r->voucher_number)
                        ->count() . '×')
                    ->badge()
                    ->color(fn (VoucherReprint $r) => VoucherReprint::where('tenant_id', $r->tenant_id)
                        ->where('voucher_number', $r->voucher_number)
                        ->count() >= 2 ? 'warning' : 'gray'),

                TextColumn::make('user.name')
                    ->label('Nachgedruckt von')
                    ->placeholder('—'),

                TextColumn::make('targetAgent.name')
                    ->label('Drucker/Standort')
                    ->placeholder('Standard')
                    ->toggleable(),

                TextColumn::make('station.name')
                    ->label('Station')
                    ->toggleable()
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('station_id')
                    ->label('Station')
                    ->options(fn () => GasStation::where('tenant_id', session('tenant_id'))->pluck('name', 'id')),

                SelectFilter::make('user_id')
                    ->label('Mitarbeiter')
                    ->options(fn () => \App\Models\User::where('tenant_id', session('tenant_id'))
                        ->get()
                        ->pluck('name', 'id')
                        ->toArray()),
            ])
            ->emptyStateHeading('Keine Nachdrucke')
            ->emptyStateDescription('Hier erscheint jeder nachgedruckte Gutschein (wer, wann, welche Nummer).')
            ->emptyStateIcon('heroicon-o-document-magnifying-glass');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVoucherReprints::route('/'),
        ];
    }
}
