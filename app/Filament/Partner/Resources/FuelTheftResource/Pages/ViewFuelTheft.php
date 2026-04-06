<?php

namespace App\Filament\Partner\Resources\FuelTheftResource\Pages;

use App\Filament\Partner\Resources\FuelTheftResource;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * Detailansicht eines Tankbetrugs.
 * Zeigt Status-Timeline, Workflow-Buttons, Notizen und Medien-Upload.
 */
class ViewFuelTheft extends ViewRecord
{
    protected static string $resource = FuelTheftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            // ── Status-Aktionen ──

            Actions\Action::make('mark_in_progress')
                ->label('In Bearbeitung')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === 'submitted')
                ->action(function () {
                    $this->record->update(['status' => 'in_progress']);
                    $this->refreshFormData(['status']);
                }),

            Actions\Action::make('mark_resolved')
                ->label('Erledigt')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => in_array($this->record->status, ['submitted', 'in_progress']))
                ->action(function () {
                    $this->record->update(['status' => 'resolved', 'resolved_at' => now()]);
                    $this->refreshFormData(['status', 'resolved_at']);
                }),

            Actions\Action::make('mark_rejected')
                ->label('Ablehnen')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => in_array($this->record->status, ['submitted', 'in_progress']))
                ->action(function () {
                    $this->record->update(['status' => 'rejected']);
                    $this->refreshFormData(['status']);
                }),

            // ── Anzeige generieren (4-Tage-Sperre) ──

            Actions\Action::make('generate_police_report')
                ->label(function () {
                    if ($this->record->police_report_at) {
                        return 'Anzeige generiert';
                    }
                    $days = $this->record->daysUntilPoliceReport();
                    if ($days > 0) {
                        return "Anzeige (in {$days} Tagen)";
                    }

                    return 'Anzeige generieren';
                })
                ->icon('heroicon-o-document-text')
                ->color(fn () => $this->record->police_report_at ? 'gray' : 'info')
                ->disabled(fn () => ! $this->record->canGeneratePoliceReport())
                ->requiresConfirmation()
                ->modalHeading('Anzeige generieren')
                ->modalDescription('Moechten Sie die Anzeige fuer diesen Tankbetrug generieren? Dies kann nicht rueckgaengig gemacht werden.')
                ->visible(fn () => in_array($this->record->status, ['submitted', 'in_progress', 'resolved']))
                ->action(function () {
                    $this->record->update(['police_report_at' => now()]);
                    $this->refreshFormData(['police_report_at']);
                    $this->sendSuccessNotification('Anzeige wurde generiert.');
                }),

            // ── Bei Gesellschaft einreichen ──

            Actions\Action::make('submit_to_company')
                ->label(fn () => $this->record->submitted_to_company_at
                    ? 'Bei Gesellschaft eingereicht'
                    : 'Bei Gesellschaft einreichen')
                ->icon('heroicon-o-building-office')
                ->color(fn () => $this->record->submitted_to_company_at ? 'gray' : 'primary')
                ->disabled(fn () => (bool) $this->record->submitted_to_company_at)
                ->requiresConfirmation()
                ->modalHeading('Bei Gesellschaft einreichen')
                ->modalDescription('Ein PDF mit allen Daten wird generiert und der Vorfall als bei der Gesellschaft eingereicht markiert.')
                ->visible(fn () => in_array($this->record->status, ['submitted', 'in_progress', 'resolved']))
                ->action(function () {
                    $this->record->update(['submitted_to_company_at' => now()]);
                    $this->refreshFormData(['submitted_to_company_at']);
                    $this->sendSuccessNotification('Vorfall wurde bei der Gesellschaft eingereicht.');
                    // TODO: PDF generieren und herunterladen
                }),

            // ── Zahlung erfassen ──

            Actions\Action::make('record_payment')
                ->label('Zahlung erfassen')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn () => in_array($this->record->payment_status, ['open', 'partial']))
                ->form([
                    TextInput::make('payment_amount')
                        ->label('Erhaltener Betrag')
                        ->numeric()
                        ->required()
                        ->prefix('EUR')
                        ->default(fn () => $this->record->open_amount)
                        ->helperText(fn () => 'Offener Betrag: ' . number_format($this->record->open_amount, 2, ',', '.') . ' EUR'),
                    TextInput::make('paid_by')
                        ->label('Bezahlt von')
                        ->required()
                        ->placeholder('z.B. Max Mustermann, Halter, Versicherung...')
                        ->maxLength(255),
                    Select::make('payment_method')
                        ->label('Zahlungsart')
                        ->options([
                            'bank_transfer' => 'Ueberweisung',
                            'cash' => 'Barzahlung',
                            'card' => 'Kartenzahlung',
                            'insurance' => 'Versicherung',
                            'collection' => 'Inkasso',
                            'other' => 'Sonstige',
                        ])
                        ->required()
                        ->default('bank_transfer'),
                    Select::make('payment_status')
                        ->label('Zahlungsstatus')
                        ->options([
                            'partial' => 'Teilzahlung',
                            'paid' => 'Vollstaendig bezahlt',
                            'written_off' => 'Abgeschrieben',
                        ])
                        ->required()
                        ->default('paid'),
                ])
                ->action(function (array $data) {
                    $newTotal = (float) $this->record->payment_amount + (float) $data['payment_amount'];
                    $this->record->update([
                        'payment_amount' => $newTotal,
                        'payment_status' => $data['payment_status'],
                        'payment_method' => $data['payment_method'],
                        'paid_by' => $data['paid_by'],
                        'payment_received_at' => now(),
                    ]);
                    $this->refreshFormData(['payment_amount', 'payment_status', 'payment_method', 'paid_by', 'payment_received_at']);
                    $this->sendSuccessNotification('Zahlung wurde erfasst.');
                }),
        ];
    }

    /**
     * Erfolgs-Notification senden.
     */
    private function sendSuccessNotification(string $message): void
    {
        \Filament\Notifications\Notification::make()
            ->title($message)
            ->success()
            ->send();
    }

    /**
     * Zusaetzliche Infoboxen unter dem Formular.
     */
    protected function getFooterWidgets(): array
    {
        return [];
    }

    /**
     * Schema fuer die View-Seite: Standard-Form + Status-Timeline + Notizen + Medien.
     */
    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            // ── Status-Timeline ──
            Section::make('Workflow-Status')
                ->icon('heroicon-o-flag')
                ->schema([
                    \Filament\Forms\Components\Placeholder::make('workflow_timeline')
                        ->label('')
                        ->content(fn () => new HtmlString($this->getTimelineHtml())),
                ]),

            // ── Vorfalldaten (aus Resource) ──
            ...FuelTheftResource::getFormSchema(),

            // ── Interne Notizen ──
            Section::make('Interne Notizen')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->description('Nur fuer Partner/Inhaber sichtbar. Nicht im Wizard enthalten.')
                ->schema([
                    Textarea::make('internal_notes')
                        ->label('Notizen / Memo')
                        ->rows(4)
                        ->maxLength(5000)
                        ->placeholder('Interne Anmerkungen zum Vorfall...'),
                ]),

            // ── Nachtraegliche Medien ──
            Section::make('Fotos & Videos (nachtraeglich)')
                ->icon('heroicon-o-camera')
                ->description('Hier koennen nachtraeglich weitere Fotos und Videos hochgeladen werden.')
                ->schema([
                    FileUpload::make('media')
                        ->label('Medien hochladen')
                        ->multiple()
                        ->disk('public')
                        ->directory('fuel-thefts/media')
                        ->acceptedFileTypes([
                            'image/jpeg', 'image/png', 'image/webp',
                            'video/mp4', 'video/quicktime', 'video/x-msvideo',
                        ])
                        ->maxSize(51200)
                        ->maxFiles(10)
                        ->imagePreviewHeight('150')
                        ->reorderable()
                        ->helperText('Fotos, Videoueberwachungs-Aufnahmen etc. (max. 10 Dateien, je max. 50 MB)'),
                ]),
        ]);
    }

    /**
     * Timeline-HTML fuer den Workflow-Status.
     */
    private function getTimelineHtml(): string
    {
        $r = $this->record;

        $steps = [
            [
                'label' => 'Gemeldet',
                'date' => $r->submitted_at,
                'icon' => '📋',
                'done' => (bool) $r->submitted_at,
            ],
            [
                'label' => 'In Bearbeitung',
                'date' => $r->status === 'in_progress' ? $r->updated_at : null,
                'icon' => '⚙️',
                'done' => in_array($r->status, ['in_progress', 'resolved']),
            ],
            [
                'label' => 'Anzeige generiert',
                'date' => $r->police_report_at,
                'icon' => '🚔',
                'done' => (bool) $r->police_report_at,
                'info' => ! $r->police_report_at && $r->daysUntilPoliceReport() > 0
                    ? "in {$r->daysUntilPoliceReport()} Tagen moeglich"
                    : null,
            ],
            [
                'label' => 'Bei Gesellschaft eingereicht',
                'date' => $r->submitted_to_company_at,
                'icon' => '🏢',
                'done' => (bool) $r->submitted_to_company_at,
            ],
            [
                'label' => 'Zahlung',
                'date' => $r->payment_received_at,
                'icon' => '💰',
                'done' => in_array($r->payment_status, ['paid', 'written_off']),
                'info' => $r->payment_status === 'partial'
                    ? 'Teilzahlung: ' . number_format((float) $r->payment_amount, 2, ',', '.') . ' EUR'
                    : ($r->payment_status === 'open' ? 'Offen: ' . number_format((float) $r->amount, 2, ',', '.') . ' EUR' : null),
            ],
            [
                'label' => 'Erledigt',
                'date' => $r->resolved_at,
                'icon' => '✅',
                'done' => $r->status === 'resolved',
            ],
        ];

        $html = '<div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-start;">';

        foreach ($steps as $i => $step) {
            $bg = $step['done'] ? '#dcfce7' : '#f1f5f9';
            $border = $step['done'] ? '#22c55e' : '#e2e8f0';
            $textColor = $step['done'] ? '#166534' : '#64748b';
            $dateStr = $step['date'] ? $step['date']->format('d.m.Y H:i') : '';
            $infoStr = $step['info'] ?? '';

            $html .= "<div style='flex: 1; min-width: 140px; background: {$bg}; border: 1px solid {$border}; border-radius: 8px; padding: 10px 12px; text-align: center;'>";
            $html .= "<div style='font-size: 20px; margin-bottom: 4px;'>{$step['icon']}</div>";
            $html .= "<div style='font-size: 13px; font-weight: 600; color: {$textColor};'>{$step['label']}</div>";

            if ($dateStr) {
                $html .= "<div style='font-size: 11px; color: #94a3b8; margin-top: 2px;'>{$dateStr}</div>";
            }

            if ($infoStr) {
                $html .= "<div style='font-size: 11px; color: #f59e0b; margin-top: 2px; font-style: italic;'>{$infoStr}</div>";
            }

            $html .= '</div>';

            // Pfeil zwischen Steps
            if ($i < count($steps) - 1) {
                $html .= "<div style='display: flex; align-items: center; color: #cbd5e1; font-size: 18px;'>→</div>";
            }
        }

        $html .= '</div>';

        // Zahlungs-Zusammenfassung
        $html .= '<div style="margin-top: 16px; padding: 12px 16px; background: #f8fafc; border-radius: 8px; display: flex; gap: 24px; font-size: 13px;">';
        $html .= '<div><span style="color: #64748b;">Betrag:</span> <strong style="color: #dc2626;">' . number_format((float) $r->amount, 2, ',', '.') . ' EUR</strong></div>';
        $html .= '<div><span style="color: #64748b;">Erhalten:</span> <strong style="color: #16a34a;">' . number_format((float) $r->payment_amount, 2, ',', '.') . ' EUR</strong></div>';
        $html .= '<div><span style="color: #64748b;">Offen:</span> <strong style="color: ' . ($r->open_amount > 0 ? '#dc2626' : '#16a34a') . ';">' . number_format($r->open_amount, 2, ',', '.') . ' EUR</strong></div>';
        $html .= '<div><span style="color: #64748b;">Status:</span> <strong>' . $r->payment_status_label . '</strong></div>';
        $html .= '</div>';

        return $html;
    }
}
