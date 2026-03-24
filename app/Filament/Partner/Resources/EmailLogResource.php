<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\EmailLogResource\Pages;
use App\Mail\InvoiceMail;
use App\Models\GasStation;
use App\Models\Invoice;
use App\Models\InvoiceLog;
use App\Models\InvoiceSetting;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * E-Mail-Versand-Protokoll.
 * Zeigt alle versendeten und fehlgeschlagenen E-Mails.
 * Read-only mit Retry-Moeglichkeit fuer fehlgeschlagene.
 */
class EmailLogResource extends Resource
{
    protected static ?string $model = InvoiceLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|\UnitEnum|null $navigationGroup = 'Rechnungen';

    protected static ?string $navigationLabel = 'E-Mails';

    protected static ?string $modelLabel = 'E-Mail';

    protected static ?string $pluralModelLabel = 'E-Mails';

    protected static ?int $navigationSort = 24;

    protected static ?string $slug = 'email-logs';

    public static function getNavigationLabel(): string
    {
        return __('partner.email_log.plural');
    }

    public static function getModelLabel(): string
    {
        return __('partner.email_log.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('partner.email_log.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('partner.invoice.nav_group');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = InvoiceLog::where('channel', 'email')
            ->where('status', 'failed')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('channel', 'email')
            ->with(['invoice.corporateCustomer', 'invoice.gasStation']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('partner.invoice.fields.datum'))
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),

                TextColumn::make('invoice.invoice_number')
                    ->label(__('partner.invoice.fields.invoice_number'))
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),

                TextColumn::make('invoice.corporateCustomer.name')
                    ->label(__('partner.invoice.fields.customer'))
                    ->searchable()
                    ->limit(30),

                TextColumn::make('recipient')
                    ->label(__('partner.invoice.fields.empfaenger'))
                    ->searchable()
                    ->icon('heroicon-o-envelope')
                    ->limit(35),

                TextColumn::make('invoice.gasStation.name')
                    ->label(__('partner.invoice.fields.gas_station'))
                    ->limit(20),

                TextColumn::make('invoice.amount')
                    ->label(__('partner.invoice.fields.betrag'))
                    ->money('EUR')
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('partner.invoice.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'sent' => __('partner.invoice.email_statuses.sent'),
                        'failed' => __('partner.invoice.email_statuses.failed'),
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('error_message')
                    ->label(__('partner.invoice.fields.fehler'))
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('danger'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('partner.invoice.fields.status'))
                    ->options([
                        'sent' => __('partner.invoice.email_statuses.sent'),
                        'failed' => __('partner.invoice.email_statuses.failed'),
                    ]),

                SelectFilter::make('gas_station')
                    ->label(__('partner.invoice.fields.gas_station'))
                    ->options(function () {
                        $tenantId = auth()->user()?->tenant_id;

                        return $tenantId
                            ? GasStation::where('tenant_id', $tenantId)->pluck('name', 'id')
                            : [];
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value']) {
                            return $query->whereHas('invoice', fn ($q) => $q->where('gas_station_id', $data['value']));
                        }

                        return $query;
                    }),
            ])
            ->actions([
                Actions\Action::make('retry')
                    ->label(__('partner.invoice_batch.actions.retry'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(__('partner.invoice_batch.confirm.retry_single'))
                    ->modalDescription(fn (InvoiceLog $record) => __('partner.email_log.messages.resend_confirm', [
                        'number' => $record->invoice?->invoice_number,
                        'email' => $record->recipient,
                    ]))
                    ->visible(fn (InvoiceLog $record): bool => $record->status === 'failed')
                    ->action(function (InvoiceLog $record) {
                        $invoice = Invoice::with(['corporateCustomer', 'gasStation'])->find($record->invoice_id);

                        if (! $invoice || ! $record->recipient) {
                            Notification::make()->title(__('partner.email_log.messages.invoice_not_found'))->danger()->send();

                            return;
                        }

                        if (! $invoice->pdf_path || ! Storage::exists($invoice->pdf_path)) {
                            InvoiceLog::logEmail($invoice, 'failed', $record->recipient, __('partner.invoice.messages.no_pdf'), $invoice->batch_id);
                            Notification::make()->title(__('partner.invoice.messages.no_pdf'))->danger()->send();

                            return;
                        }

                        try {
                            Mail::to($record->recipient)->send(new InvoiceMail($invoice));

                            $invoice->update(['email_status' => 'sent', 'sent_at' => now()]);
                            InvoiceLog::logEmail($invoice, 'sent', $record->recipient, null, $invoice->batch_id);

                            Notification::make()
                                ->title(__('partner.email_log.messages.email_resent', ['email' => $record->recipient]))
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            $invoice->update([
                                'email_status' => 'failed',
                                'email_error' => substr($e->getMessage(), 0, 500),
                            ]);
                            InvoiceLog::logEmail($invoice, 'failed', $record->recipient, $e->getMessage(), $invoice->batch_id);

                            Notification::make()
                                ->title(__('partner.common.error') . ': ' . substr($e->getMessage(), 0, 100))
                                ->danger()
                                ->send();
                        }
                    }),

                Actions\Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->url(fn (InvoiceLog $record) => $record->invoice?->pdf_path
                        ? route('invoice.pdf.download', ['invoice' => $record->invoice_id])
                        : null)
                    ->visible(fn (InvoiceLog $record): bool => (bool) $record->invoice?->pdf_path),
            ])
            ->headerActions([
                Actions\Action::make('retry_all_failed')
                    ->label(__('partner.invoice_batch.actions.retry_all'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('partner.invoice_batch.confirm.retry_all'))
                    ->modalDescription(__('partner.invoice_batch.confirm.retry_all_desc'))
                    ->action(function () {
                        // Neueste fehlgeschlagene Logs pro Invoice (keine Duplikate)
                        $failedLogs = InvoiceLog::where('channel', 'email')
                            ->where('status', 'failed')
                            ->with(['invoice.corporateCustomer', 'invoice.gasStation'])
                            ->orderByDesc('created_at')
                            ->get()
                            ->unique('invoice_id');

                        if ($failedLogs->isEmpty()) {
                            Notification::make()->title(__('partner.email_log.messages.no_failed'))->info()->send();

                            return;
                        }

                        $delay = max(5, (int) InvoiceSetting::get('email_delay_seconds', 3));
                        $sent = 0;
                        $failed = 0;

                        foreach ($failedLogs as $log) {
                            $invoice = $log->invoice;
                            if (! $invoice || ! $log->recipient) {
                                continue;
                            }

                            if (! $invoice->pdf_path || ! Storage::exists($invoice->pdf_path)) {
                                InvoiceLog::logEmail($invoice, 'failed', $log->recipient, __('partner.invoice.messages.no_pdf'), $invoice->batch_id);
                                $failed++;

                                continue;
                            }

                            try {
                                Mail::to($log->recipient)->send(new InvoiceMail($invoice));
                                $invoice->update(['email_status' => 'sent', 'sent_at' => now()]);
                                InvoiceLog::logEmail($invoice, 'sent', $log->recipient, null, $invoice->batch_id);
                                $sent++;
                            } catch (\Exception $e) {
                                $invoice->update([
                                    'email_status' => 'failed',
                                    'email_error' => substr($e->getMessage(), 0, 500),
                                ]);
                                InvoiceLog::logEmail($invoice, 'failed', $log->recipient, $e->getMessage(), $invoice->batch_id);
                                $failed++;
                            }

                            // Rate-Limit einhalten
                            if ($sent + $failed < $failedLogs->count()) {
                                usleep($delay * 1000000);
                            }
                        }

                        $message = "{$sent} " . __('partner.invoice.email_statuses.sent');
                        if ($failed > 0) {
                            $message .= ", {$failed} " . __('partner.invoice.email_statuses.failed');
                        }

                        Notification::make()
                            ->title(__('partner.email_log.messages.retry_complete'))
                            ->body($message)
                            ->color($failed > 0 ? 'warning' : 'success')
                            ->send();
                    }),
            ])
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailLogs::route('/'),
        ];
    }
}
