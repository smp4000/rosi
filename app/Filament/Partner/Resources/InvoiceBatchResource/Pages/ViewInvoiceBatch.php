<?php

namespace App\Filament\Partner\Resources\InvoiceBatchResource\Pages;

use App\Filament\Partner\Resources\InvoiceBatchResource;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Services\PdfMergerService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

/**
 * Batch-Detail-Seite mit Versand-Funktionen.
 * E-Mail-Versand, Druck-PDF, Versand-Bericht.
 */
class ViewInvoiceBatch extends ViewRecord
{
    protected static string $resource = InvoiceBatchResource::class;

    protected string $view = 'filament.partner.pages.view-invoice-batch';

    public string $activeTab = 'email';

    // Editierbare Kunden-Daten (Neue Kunden Tab)
    public array $customerEmails = [];
    public array $customerSendEmail = [];
    public array $customerSendPrint = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Kunden-Daten fuer Inline-Editing laden
        $customers = $this->getNewCustomers();
        foreach ($customers as $cust) {
            $this->customerEmails[$cust->id] = $cust->email ?? '';
            $this->customerSendEmail[$cust->id] = (bool) $cust->send_via_email;
            $this->customerSendPrint[$cust->id] = (bool) $cust->send_via_print;
        }
    }

    /**
     * Einzelnen Kunden speichern (aus Neue Kunden Tab).
     */
    public function saveCustomer(string $customerId): void
    {
        $customer = \App\Models\CorporateCustomer::find($customerId);
        if (! $customer) {
            return;
        }

        $customer->update([
            'email' => $this->customerEmails[$customerId] ?? $customer->email,
            'send_via_email' => $this->customerSendEmail[$customerId] ?? false,
            'send_via_print' => $this->customerSendPrint[$customerId] ?? true,
        ]);

        Notification::make()
            ->title("Kunde {$customer->name} gespeichert")
            ->success()
            ->duration(3000)
            ->send();
    }

    // ── Header Actions ──

    protected function getHeaderActions(): array
    {
        return [
            // Komplett-Versand
            Actions\Action::make('komplett_versand')
                ->label('Komplett-Versand')
                ->icon('heroicon-o-rocket-launch')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Komplett-Versand starten?')
                ->modalDescription('E-Mails werden versendet und Druck-PDF wird erstellt.')
                ->action(function () {
                    $this->doSendAllEmails();
                    $this->doCreatePrintPdf();
                    $this->generateVersandBericht();
                }),

            // Alle E-Mails versenden
            Actions\Action::make('send_all_emails')
                ->label('Alle E-Mails versenden')
                ->icon('heroicon-o-envelope')
                ->color('info')
                ->requiresConfirmation()
                ->action(fn () => $this->doSendAllEmails()),

            // Sammel-PDF erzeugen + downloaden
            Actions\Action::make('create_print_pdf')
                ->label('Sammel-PDF erzeugen')
                ->icon('heroicon-o-printer')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Sammel-PDF fuer Druck erstellen?')
                ->modalDescription('Alle Druck-Rechnungen werden in einem PDF zusammengefasst und heruntergeladen.')
                ->action(function () {
                    $this->doCreatePrintPdf();

                    if ($this->lastPrintPdfPath && Storage::exists($this->lastPrintPdfPath)) {
                        return response()->streamDownload(function () {
                            echo Storage::get($this->lastPrintPdfPath);
                        }, 'Sammel_Druck_' . now()->format('Y-m-d_His') . '.pdf', [
                            'Content-Type' => 'application/pdf',
                        ]);
                    }
                }),

            // Als gedruckt markieren
            Actions\Action::make('mark_printed')
                ->label('Als gedruckt markieren')
                ->icon('heroicon-o-check')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    $count = Invoice::where('batch_id', $this->record->id)
                        ->where('import_status', 'success')
                        ->where('status', '!=', 'printed')
                        ->whereHas('corporateCustomer', fn ($q) => $q->where('send_via_print', true))
                        ->update(['status' => 'printed', 'printed_at' => now()]);

                    Notification::make()
                        ->title("{$count} Rechnungen als gedruckt markiert")
                        ->success()
                        ->send();
                }),

            // Neuer Import
            Actions\Action::make('neuer_import')
                ->label('Neuer Import')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->url(route('filament.partner.pages.invoice-import')),

            // Versand-Bericht
            Actions\Action::make('versand_bericht')
                ->label('Versand-Bericht')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->action(fn () => $this->generateVersandBericht()),
        ];
    }

    // ── E-Mail Versand ──

    private function doSendAllEmails(): void
    {
        $batch = $this->record;

        $invoices = Invoice::where('batch_id', $batch->id)
            ->where('import_status', 'success')
            ->whereNotIn('status', ['sent', 'failed'])
            ->whereHas('corporateCustomer', function ($q) {
                $q->where('send_via_email', true)
                  ->where('is_active', true)
                  ->whereNotNull('email')
                  ->where('email', '!=', '');
            })
            ->with(['corporateCustomer', 'gasStation'])
            ->get();

        if ($invoices->isEmpty()) {
            Notification::make()->title('Keine E-Mails zu versenden')->warning()->send();

            return;
        }

        $sent = 0;
        $failed = 0;
        $errors = [];
        try {
            $delay = (int) InvoiceSetting::get('email_delay_seconds', 3);
        } catch (\Exception $e) {
            $delay = 3;
        }

        foreach ($invoices as $invoice) {
            // Verzoegerung zwischen E-Mails (Rate-Limiting Strato)
            if ($sent > 0 && $delay > 0) {
                sleep($delay);
            }

            $email = $invoice->corporateCustomer->email;
            $hasPdf = $invoice->pdf_path && Storage::exists($invoice->pdf_path);

            if (! $hasPdf) {
                \Log::warning("E-Mail-Versand: Kein PDF fuer Rechnung {$invoice->invoice_number}");
                $errors[] = "#{$invoice->invoice_number}: Kein PDF";
                $failed++;
                continue;
            }

            try {
                Mail::to($email)->send(new InvoiceMail($invoice));

                $invoice->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
                $sent++;

                \Log::info("E-Mail versendet: Rechnung {$invoice->invoice_number} an {$email}");
            } catch (\Exception $e) {
                \Log::error('E-Mail-Versand fehlgeschlagen', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
                $invoice->update(['status' => 'failed']);
                $errors[] = "#{$invoice->invoice_number}: {$e->getMessage()}";
                $failed++;
            }
        }

        if ($failed > 0) {
            $errorDetail = implode("\n", array_slice($errors, 0, 5));
            Notification::make()
                ->title("E-Mail-Versand: {$sent} gesendet, {$failed} fehlgeschlagen")
                ->body($errorDetail)
                ->warning()
                ->duration(10000)
                ->send();
        } else {
            Notification::make()
                ->title("Alle {$sent} E-Mails erfolgreich versendet!")
                ->success()
                ->send();
        }
    }

    // ── Druck-PDF ──

    public string $lastPrintPdfPath = '';

    private function doCreatePrintPdf(): void
    {
        $batch = $this->record;

        $invoices = Invoice::where('batch_id', $batch->id)
            ->where('import_status', 'success')
            ->whereHas('corporateCustomer', function ($q) {
                $q->where('send_via_print', true)
                  ->where('is_active', true);
            })
            ->get();

        if ($invoices->isEmpty()) {
            Notification::make()->title('Keine Druck-Rechnungen vorhanden')->info()->send();

            return;
        }

        try {
            $merger = new PdfMergerService();
            $mergedPath = $merger->mergePdfs($invoices);

            $this->lastPrintPdfPath = $mergedPath;

            Notification::make()
                ->title("{$invoices->count()} Rechnungen als Sammel-PDF erstellt")
                ->body('Das PDF kann unter Druck-Versand heruntergeladen werden.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Fehler beim Erstellen des Druck-PDFs: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    // ── Versand-Bericht ──

    private function generateVersandBericht(): void
    {
        $batch = $this->record;
        $invoices = Invoice::where('batch_id', $batch->id)
            ->where('import_status', 'success')
            ->with(['corporateCustomer', 'gasStation'])
            ->orderBy('invoice_number')
            ->get();

        if ($invoices->isEmpty()) {
            Notification::make()->title('Keine Rechnungen fuer Bericht vorhanden')->warning()->send();

            return;
        }

        try {
            $pdf = new Fpdi();
            $pdf->SetAutoPageBreak(true, 15);
            $pdf->AddPage();

            // Titel
            $pdf->SetFont('Helvetica', 'B', 16);
            $pdf->Cell(0, 10, $this->pdfText('Versand-Bericht'), 0, 1, 'C');
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->Cell(0, 6, $this->pdfText($batch->name), 0, 1, 'C');
            $pdf->Cell(0, 6, $this->pdfText('Erstellt: ' . now()->format('d.m.Y H:i')), 0, 1, 'C');
            $pdf->Ln(10);

            // E-Mail-Versand Sektion
            $emailInvoices = $invoices->filter(fn ($i) => $i->corporateCustomer?->send_via_email);
            if ($emailInvoices->isNotEmpty()) {
                $this->pdfSection($pdf, 'E-Mail-Versand (' . $emailInvoices->count() . ' Rechnungen)');

                // Tabellenkopf
                $pdf->SetFont('Helvetica', 'B', 8);
                $pdf->SetFillColor(240, 240, 240);
                $pdf->Cell(25, 7, $this->pdfText('Rech.Nr.'), 1, 0, 'L', true);
                $pdf->Cell(25, 7, $this->pdfText('Kd.Nr.'), 1, 0, 'L', true);
                $pdf->Cell(55, 7, $this->pdfText('Name'), 1, 0, 'L', true);
                $pdf->Cell(55, 7, $this->pdfText('E-Mail'), 1, 0, 'L', true);
                $pdf->Cell(25, 7, $this->pdfText('Betrag'), 1, 1, 'R', true);

                $pdf->SetFont('Helvetica', '', 8);
                foreach ($emailInvoices as $inv) {
                    if ($pdf->GetY() > 265) {
                        $pdf->AddPage();
                    }
                    $pdf->Cell(25, 6, $inv->invoice_number, 1);
                    $pdf->Cell(25, 6, $inv->corporateCustomer?->customer_number ?? '-', 1);
                    $pdf->Cell(55, 6, $this->pdfText(substr($inv->corporateCustomer?->name ?? '-', 0, 30)), 1);
                    $pdf->Cell(55, 6, $this->pdfText(substr($inv->corporateCustomer?->email ?? '-', 0, 35)), 1);
                    $pdf->Cell(25, 6, number_format($inv->amount, 2, ',', '.') . ' EUR', 1, 1, 'R');
                }
                $pdf->Ln(5);
            }

            // Druck-Versand Sektion
            $printInvoices = $invoices->filter(fn ($i) => $i->corporateCustomer?->send_via_print);
            if ($printInvoices->isNotEmpty()) {
                $this->pdfSection($pdf, 'Druck-Versand (' . $printInvoices->count() . ' Rechnungen)');

                $pdf->SetFont('Helvetica', 'B', 8);
                $pdf->SetFillColor(240, 240, 240);
                $pdf->Cell(25, 7, $this->pdfText('Rech.Nr.'), 1, 0, 'L', true);
                $pdf->Cell(25, 7, $this->pdfText('Kd.Nr.'), 1, 0, 'L', true);
                $pdf->Cell(60, 7, $this->pdfText('Name'), 1, 0, 'L', true);
                $pdf->Cell(75, 7, $this->pdfText('Adresse'), 1, 1, 'L', true);

                $pdf->SetFont('Helvetica', '', 8);
                foreach ($printInvoices as $inv) {
                    if ($pdf->GetY() > 265) {
                        $pdf->AddPage();
                    }
                    $c = $inv->corporateCustomer;
                    $address = $c ? "{$c->street}, {$c->zip} {$c->city}" : '-';
                    $pdf->Cell(25, 6, $inv->invoice_number, 1);
                    $pdf->Cell(25, 6, $c?->customer_number ?? '-', 1);
                    $pdf->Cell(60, 6, $this->pdfText(substr($c?->name ?? '-', 0, 35)), 1);
                    $pdf->Cell(75, 6, $this->pdfText(substr($address, 0, 45)), 1, 1);
                }
                $pdf->Ln(5);
            }

            // Zusammenfassung
            $this->pdfSection($pdf, 'Zusammenfassung');
            $pdf->SetFont('Helvetica', '', 10);
            $totalAmount = $invoices->sum('amount');
            $pdf->Cell(0, 7, $this->pdfText("Gesamt: {$invoices->count()} Rechnungen | " . number_format($totalAmount, 2, ',', '.') . " EUR"), 0, 1);
            $pdf->Cell(0, 7, $this->pdfText("E-Mail: {$emailInvoices->count()} | Druck: {$printInvoices->count()}"), 0, 1);

            // Speichern
            $filename = 'reports/Versandbericht_' . now()->format('Y-m-d_His') . '.pdf';
            $outputPath = Storage::path($filename);
            $dir = dirname($outputPath);
            if (! file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
            $pdf->Output($outputPath, 'F');

            Notification::make()
                ->title('Versand-Bericht erstellt')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Fehler: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function pdfText(string $text): string
    {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text) ?: $text;
    }

    private function pdfSection(Fpdi $pdf, string $title): void
    {
        if ($pdf->GetY() > 250) {
            $pdf->AddPage();
        }
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetFillColor(41, 128, 185);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(0, 8, '  ' . $this->pdfText($title), 0, 1, 'L', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(3);
    }

    // ── Tab-Daten ──

    public function getEmailInvoices()
    {
        return Invoice::where('batch_id', $this->record->id)
            ->where('import_status', 'success')
            ->whereHas('corporateCustomer', fn ($q) => $q->where('send_via_email', true))
            ->with(['corporateCustomer', 'gasStation'])
            ->orderBy('invoice_number')
            ->get();
    }

    public function getPrintInvoices()
    {
        return Invoice::where('batch_id', $this->record->id)
            ->where('import_status', 'success')
            ->whereHas('corporateCustomer', fn ($q) => $q->where('send_via_print', true))
            ->with(['corporateCustomer', 'gasStation'])
            ->orderBy('invoice_number')
            ->get();
    }

    public function getErrorInvoices()
    {
        return Invoice::where('batch_id', $this->record->id)
            ->where('import_status', 'error')
            ->with(['corporateCustomer', 'gasStation'])
            ->orderBy('invoice_number')
            ->get();
    }

    public function getDuplicateInvoices()
    {
        return Invoice::where('batch_id', $this->record->id)
            ->where('import_status', 'duplicate')
            ->with(['corporateCustomer', 'gasStation'])
            ->orderBy('invoice_number')
            ->get();
    }

    public function getNewCustomers()
    {
        // Kunden die durch diesen Batch angelegt wurden
        return \App\Models\CorporateCustomer::where('gas_station_id', $this->record->gas_station_id)
            ->whereHas('invoices', fn ($q) => $q->where('batch_id', $this->record->id))
            ->where('created_at', '>=', $this->record->created_at)
            ->where('created_at', '<=', ($this->record->completed_at ?? now())->addMinute())
            ->get();
    }

    // ── Einzel-Aktionen ──

    public function sendSingleEmail(string $invoiceId): void
    {
        $invoice = Invoice::with(['corporateCustomer', 'gasStation'])->find($invoiceId);

        if (! $invoice || ! $invoice->corporateCustomer?->email) {
            Notification::make()->title('Keine E-Mail-Adresse vorhanden')->danger()->send();

            return;
        }

        try {
            Mail::to($invoice->corporateCustomer->email)
                ->send(new InvoiceMail($invoice));

            $invoice->update(['status' => 'sent', 'sent_at' => now()]);

            Notification::make()->title('E-Mail gesendet an ' . $invoice->corporateCustomer->email)->success()->send();
        } catch (\Exception $e) {
            $invoice->update(['status' => 'failed']);
            Notification::make()->title('Fehler: ' . $e->getMessage())->danger()->send();
        }
    }
}
