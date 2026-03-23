<?php

namespace App\Filament\Partner\Resources\CorporateCustomerResource\RelationManagers;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Rechnungshistorie im Firmenkunden-Kontext.
 * Zeigt alle Rechnungen eines Firmenkunden mit Versand-Aktionen.
 */
class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Rechnungen';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-document-text';

    protected static ?string $modelLabel = 'Rechnung';

    protected static ?string $pluralModelLabel = 'Rechnungen';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Rechnungsnr.')
                    ->sortable()
                    ->searchable()
                    ->fontFamily('mono'),

                TextColumn::make('invoice_date')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Betrag')
                    ->money('EUR')
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'uploaded' => 'Hochgeladen',
                        'processed' => 'Verarbeitet',
                        'sent' => 'Versendet',
                        'printed' => 'Gedruckt',
                        'failed' => 'Fehlgeschlagen',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'uploaded' => 'gray',
                        'processed' => 'warning',
                        'sent' => 'success',
                        'printed' => 'info',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('invoice_date', 'desc')
            ->actions([
                Actions\Action::make('send_email')
                    ->label('E-Mail')
                    ->icon('heroicon-o-envelope')
                    ->color('success')
                    ->size('sm')
                    ->requiresConfirmation()
                    ->modalHeading('Rechnung per E-Mail senden?')
                    ->visible(fn (Invoice $record): bool => $record->pdf_path && Storage::exists($record->pdf_path))
                    ->action(function (Invoice $record) {
                        $customer = $this->getOwnerRecord();
                        if (! $customer->email) {
                            Notification::make()->title('Keine E-Mail-Adresse')->danger()->send();

                            return;
                        }

                        try {
                            Mail::to($customer->email)->send(new InvoiceMail($record));
                            $record->update(['status' => 'sent', 'sent_at' => now()]);
                            Notification::make()->title('E-Mail versendet!')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Fehler: ' . $e->getMessage())->danger()->send();
                        }
                    }),

                Actions\Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->size('sm')
                    ->visible(fn (Invoice $record): bool => $record->pdf_path && Storage::exists($record->pdf_path))
                    ->url(fn (Invoice $record): string => '/rechnung/' . $record->id . '/pdf')
                    ->openUrlInNewTab(),
            ]);
    }
}
