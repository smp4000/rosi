<?php

namespace App\Filament\Partner\Resources\InvoiceBatchResource\Pages;

use App\Filament\Partner\Resources\InvoiceBatchResource;
use App\Enums\EmailStatus;
use App\Enums\ImportStatus;
use App\Enums\PrintStatus;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\InvoiceLog;
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

    // Fortschrittsbalken E-Mail-Versand
    public bool $isSending = false;
    public int $sendingCurrent = 0;
    public int $sendingTotal = 0;
    public string $sendingCurrentName = '';
    public int $sendingSent = 0;
    public int $sendingFailed = 0;

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
            ->title(__('partner.invoice_batch.messages.customer_saved', ['name' => $customer->name]))
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
                ->label(__('partner.invoice_batch.actions.complete_send'))
                ->icon('heroicon-o-rocket-launch')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('partner.invoice_batch.actions.complete_send') . '?')
                ->modalDescription(__('partner.invoice_batch.confirm.print_pdf_desc'))
                ->hidden(fn () => $this->isSending)
                ->action(function () {
                    $this->doCreatePrintPdf();
                    $this->startSendingEmails();
                }),

            // Alle E-Mails versenden
            Actions\Action::make('send_all_emails')
                ->label(__('partner.invoice_batch.actions.send_all_emails'))
                ->icon('heroicon-o-envelope')
                ->color('info')
                ->requiresConfirmation()
                ->hidden(fn () => $this->isSending)
                ->action(fn () => $this->startSendingEmails()),

            // Sammel-PDF erzeugen + downloaden
            Actions\Action::make('create_print_pdf')
                ->label(__('partner.invoice_batch.actions.create_print_pdf'))
                ->icon('heroicon-o-printer')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('partner.invoice_batch.confirm.print_pdf'))
                ->modalDescription(__('partner.invoice_batch.confirm.print_pdf_desc'))
                ->action(function () {
                    $this->doCreatePrintPdf();

                    if ($this->lastPrintPdfPath) {
                        $filename = basename($this->lastPrintPdfPath);
                        $this->redirect(route('print-pdf.download', ['filename' => $filename]));
                    }
                }),

            // Als gedruckt markieren
            Actions\Action::make('mark_printed')
                ->label(__('partner.invoice_batch.actions.mark_printed'))
                ->icon('heroicon-o-check')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    $invoices = Invoice::where('batch_id', $this->record->id)
                        ->where('import_status', ImportStatus::SUCCESS->value)
                        ->where('print_status', '!=', PrintStatus::PRINTED->value)
                        ->whereHas('corporateCustomer', fn ($q) => $q->where('send_via_print', true))
                        ->with('gasStation')
                        ->get();

                    foreach ($invoices as $invoice) {
                        $invoice->update(['print_status' => PrintStatus::PRINTED->value, 'printed_at' => now()]);
                        InvoiceLog::logPrint($invoice, $this->record->id);
                    }

                    Notification::make()
                        ->title(__('partner.invoice_batch.messages.marked_printed', ['count' => $invoices->count()]))
                        ->success()
                        ->send();
                }),

            // Neuer Import
            Actions\Action::make('neuer_import')
                ->label(__('partner.invoice_batch.actions.new_import'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->url(route('filament.partner.pages.invoice-import')),

            // Versand-Bericht
            Actions\Action::make('versand_bericht')
                ->label(__('partner.invoice_batch.actions.report'))
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->action(fn () => $this->generateVersandBericht()),
        ];
    }

    // ── E-Mail Versand (iterativ mit Fortschritt) ──

    public function startSendingEmails(): void
    {
        $batch = $this->record;

        $count = Invoice::where('batch_id', $batch->id)
            ->where('import_status', ImportStatus::SUCCESS->value)
            ->where('email_status', '!=', EmailStatus::SENT->value)
            ->whereHas('corporateCustomer', function ($q) {
                $q->where('send_via_email', true)
                  ->where('is_active', true)
                  ->whereNotNull('email')
                  ->where('email', '!=', '');
            })
            ->count();

        if ($count === 0) {
            Notification::make()->title(__('partner.invoice_batch.messages.no_emails'))->warning()->send();

            return;
        }

        $this->isSending = true;
        $this->sendingCurrent = 0;
        $this->sendingTotal = $count;
        $this->sendingCurrentName = '';
        $this->sendingSent = 0;
        $this->sendingFailed = 0;
    }

    public function sendNextEmail(): void
    {
        if (! $this->isSending) {
            return;
        }

        // Sicherheitscheck: Abbruch wenn Current > Total (sollte nie passieren)
        if ($this->sendingCurrent >= $this->sendingTotal) {
            $this->finishSending();

            return;
        }

        $batch = $this->record;

        // Naechste nicht-gesendete Rechnung holen (pending = noch nicht versucht)
        $invoice = Invoice::where('batch_id', $batch->id)
            ->where('import_status', ImportStatus::SUCCESS->value)
            ->where('email_status', EmailStatus::PENDING->value)
            ->whereHas('corporateCustomer', function ($q) {
                $q->where('send_via_email', true)
                  ->where('is_active', true)
                  ->whereNotNull('email')
                  ->where('email', '!=', '');
            })
            ->with(['corporateCustomer', 'gasStation'])
            ->first();

        if (! $invoice) {
            $this->finishSending();

            return;
        }

        $this->sendingCurrent++;
        $this->sendingCurrentName = $invoice->corporateCustomer?->name ?? 'Unbekannt';

        $email = $invoice->corporateCustomer->email;
        $hasPdf = $invoice->pdf_path && Storage::exists($invoice->pdf_path);

        if (! $hasPdf) {
            \Log::warning("E-Mail-Versand: Kein PDF fuer Rechnung {$invoice->invoice_number}");
            $invoice->update([
                'email_status' => EmailStatus::FAILED->value,
                'email_error' => 'Kein PDF vorhanden',
            ]);
            InvoiceLog::logEmail($invoice, EmailStatus::FAILED->value, $email ?? '', 'Kein PDF vorhanden', $batch->id);
            $this->sendingFailed++;

            // Pruefen ob fertig
            if ($this->sendingCurrent >= $this->sendingTotal) {
                $this->finishSending();
            }

            return;
        }

        try {
            Mail::to($email)->send(new InvoiceMail($invoice));

            $invoice->update([
                'email_status' => EmailStatus::SENT->value,
                'sent_at' => now(),
            ]);
            InvoiceLog::logEmail($invoice, EmailStatus::SENT->value, $email, null, $batch->id);
            $this->sendingSent++;

            \Log::info("E-Mail versendet: Rechnung {$invoice->invoice_number} an {$email}");
        } catch (\Exception $e) {
            \Log::error('E-Mail-Versand fehlgeschlagen', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            $invoice->update([
                'email_status' => EmailStatus::FAILED->value,
                'email_error' => substr($e->getMessage(), 0, 500),
            ]);
            InvoiceLog::logEmail($invoice, EmailStatus::FAILED->value, $email, $e->getMessage(), $batch->id);
            $this->sendingFailed++;
        }

        // Pruefen ob fertig
        if ($this->sendingCurrent >= $this->sendingTotal) {
            $this->finishSending();
        }
    }

    private function finishSending(): void
    {
        $this->isSending = false;

        if ($this->sendingFailed > 0) {
            Notification::make()
                ->title(__('partner.invoice_batch.messages.sending_partial', ['sent' => $this->sendingSent, 'failed' => $this->sendingFailed]))
                ->warning()
                ->duration(10000)
                ->send();
        } else {
            Notification::make()
                ->title(__('partner.invoice_batch.messages.sending_finished', ['count' => $this->sendingSent]))
                ->success()
                ->send();
        }
    }

    public function cancelSending(): void
    {
        $this->isSending = false;
        Notification::make()
            ->title(__('partner.invoice_batch.messages.sending_cancelled', ['sent' => $this->sendingSent, 'failed' => $this->sendingFailed]))
            ->warning()
            ->send();
    }

    // ── Druck-PDF ──

    public string $lastPrintPdfPath = '';

    public string $lastReportPath = '';

    private function doCreatePrintPdf(): void
    {
        $batch = $this->record;

        $invoices = Invoice::where('batch_id', $batch->id)
            ->where('import_status', ImportStatus::SUCCESS->value)
            ->whereHas('corporateCustomer', function ($q) {
                $q->where('send_via_print', true)
                  ->where('is_active', true);
            })
            ->get();

        if ($invoices->isEmpty()) {
            Notification::make()->title(__('partner.invoice_batch.messages.no_print_invoices'))->info()->send();

            return;
        }

        try {
            $merger = new PdfMergerService();
            $mergedPath = $merger->mergePdfs($invoices);

            $this->lastPrintPdfPath = $mergedPath;

            Notification::make()
                ->title(__('partner.invoice_batch.messages.print_pdf_created', ['count' => $invoices->count()]))
                ->body(__('partner.invoice_batch.messages.print_pdf_download'))
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

    private function generateVersandBericht(): mixed
    {
        $batch = $this->record;
        $invoices = Invoice::where('batch_id', $batch->id)
            ->where('import_status', ImportStatus::SUCCESS->value)
            ->with(['corporateCustomer', 'gasStation'])
            ->orderBy('invoice_number')
            ->get();

        if ($invoices->isEmpty()) {
            Notification::make()->title(__('partner.invoice_batch.messages.no_invoices_for_report'))->warning()->send();

            return null;
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
            $emailSent = $emailInvoices->where('email_status', 'sent')->count();
            $emailFailed = $emailInvoices->where('email_status', 'failed')->count();
            $emailPending = $emailInvoices->count() - $emailSent - $emailFailed;

            if ($emailInvoices->isNotEmpty()) {
                $statusText = "{$emailInvoices->count()} Rechnungen";
                if ($emailSent > 0) $statusText .= " | {$emailSent} versendet";
                if ($emailFailed > 0) $statusText .= " | {$emailFailed} fehlgeschlagen";
                if ($emailPending > 0) $statusText .= " | {$emailPending} ausstehend";
                $this->pdfSection($pdf, "E-Mail-Versand ({$statusText})");

                // Tabellenkopf
                $pdf->SetFont('Helvetica', 'B', 8);
                $pdf->SetFillColor(240, 240, 240);
                $pdf->Cell(22, 7, $this->pdfText('Rech.Nr.'), 1, 0, 'L', true);
                $pdf->Cell(20, 7, $this->pdfText('Kd.Nr.'), 1, 0, 'L', true);
                $pdf->Cell(45, 7, $this->pdfText('Name'), 1, 0, 'L', true);
                $pdf->Cell(48, 7, $this->pdfText('E-Mail'), 1, 0, 'L', true);
                $pdf->Cell(25, 7, $this->pdfText('Betrag'), 1, 0, 'R', true);
                $pdf->Cell(25, 7, $this->pdfText('Status'), 1, 1, 'C', true);

                $pdf->SetFont('Helvetica', '', 8);
                foreach ($emailInvoices as $inv) {
                    if ($pdf->GetY() > 265) {
                        $pdf->AddPage();
                    }
                    $pdf->Cell(22, 6, $inv->invoice_number, 1);
                    $pdf->Cell(20, 6, $inv->corporateCustomer?->customer_number ?? '-', 1);
                    $pdf->Cell(45, 6, $this->pdfText(substr($inv->corporateCustomer?->name ?? '-', 0, 25)), 1);
                    $pdf->Cell(48, 6, $this->pdfText(substr($inv->corporateCustomer?->email ?? '-', 0, 30)), 1);
                    $pdf->Cell(25, 6, number_format($inv->amount, 2, ',', '.') . ' EUR', 1, 0, 'R');

                    // Status mit Farbe
                    $status = $inv->email_status ?? 'ausstehend';
                    $statusLabel = match ($status) {
                        'sent' => 'Versendet',
                        'failed' => 'FEHLER',
                        default => 'Ausstehend',
                    };
                    if ($status === 'sent') {
                        $pdf->SetTextColor(22, 163, 74);
                    } elseif ($status === 'failed') {
                        $pdf->SetTextColor(220, 38, 38);
                    } else {
                        $pdf->SetTextColor(161, 161, 170);
                    }
                    $pdf->Cell(25, 6, $this->pdfText($statusLabel), 1, 1, 'C');
                    $pdf->SetTextColor(0, 0, 0);
                }
                $pdf->Ln(5);
            }

            // Druck-Versand Sektion
            $printInvoices = $invoices->filter(fn ($i) => $i->corporateCustomer?->send_via_print);
            $printDone = $printInvoices->where('print_status', 'printed')->count();
            $printPending = $printInvoices->count() - $printDone;

            if ($printInvoices->isNotEmpty()) {
                $printStatusText = "{$printInvoices->count()} Rechnungen";
                if ($printDone > 0) $printStatusText .= " | {$printDone} gedruckt";
                if ($printPending > 0) $printStatusText .= " | {$printPending} ausstehend";
                $this->pdfSection($pdf, "Druck-Versand ({$printStatusText})");

                $pdf->SetFont('Helvetica', 'B', 8);
                $pdf->SetFillColor(240, 240, 240);
                $pdf->Cell(22, 7, $this->pdfText('Rech.Nr.'), 1, 0, 'L', true);
                $pdf->Cell(20, 7, $this->pdfText('Kd.Nr.'), 1, 0, 'L', true);
                $pdf->Cell(45, 7, $this->pdfText('Name'), 1, 0, 'L', true);
                $pdf->Cell(53, 7, $this->pdfText('Adresse'), 1, 0, 'L', true);
                $pdf->Cell(22, 7, $this->pdfText('Betrag'), 1, 0, 'R', true);
                $pdf->Cell(23, 7, $this->pdfText('Status'), 1, 1, 'C', true);

                $pdf->SetFont('Helvetica', '', 8);
                foreach ($printInvoices as $inv) {
                    if ($pdf->GetY() > 265) {
                        $pdf->AddPage();
                    }
                    $c = $inv->corporateCustomer;
                    $address = $c ? "{$c->street}, {$c->zip} {$c->city}" : '-';
                    $pdf->Cell(22, 6, $inv->invoice_number, 1);
                    $pdf->Cell(20, 6, $c?->customer_number ?? '-', 1);
                    $pdf->Cell(45, 6, $this->pdfText(substr($c?->name ?? '-', 0, 25)), 1);
                    $pdf->Cell(53, 6, $this->pdfText(substr($address, 0, 32)), 1);
                    $pdf->Cell(22, 6, number_format($inv->amount, 2, ',', '.') . ' EUR', 1, 0, 'R');

                    $pStatus = $inv->print_status === 'printed' ? 'Gedruckt' : 'Ausstehend';
                    if ($inv->print_status === 'printed') {
                        $pdf->SetTextColor(22, 163, 74);
                    } else {
                        $pdf->SetTextColor(161, 161, 170);
                    }
                    $pdf->Cell(23, 6, $this->pdfText($pStatus), 1, 1, 'C');
                    $pdf->SetTextColor(0, 0, 0);
                }
                $pdf->Ln(5);
            }

            // Zusammenfassung
            $this->pdfSection($pdf, 'Zusammenfassung');
            $pdf->SetFont('Helvetica', '', 10);
            $totalAmount = $invoices->sum('amount');
            $emailAmount = $emailInvoices->sum('amount');
            $printAmount = $printInvoices->sum('amount');

            $pdf->Cell(0, 7, $this->pdfText("Gesamt: {$invoices->count()} Rechnungen | " . number_format($totalAmount, 2, ',', '.') . " EUR"), 0, 1);
            $pdf->Ln(2);

            // Detail-Box E-Mail
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->Cell(0, 6, $this->pdfText('E-Mail-Versand:'), 0, 1);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->Cell(0, 5, $this->pdfText("  Anzahl: {$emailInvoices->count()} | Betrag: " . number_format($emailAmount, 2, ',', '.') . " EUR"), 0, 1);
            if ($emailSent > 0) {
                $pdf->SetTextColor(22, 163, 74);
                $pdf->Cell(0, 5, $this->pdfText("  Versendet: {$emailSent}"), 0, 1);
                $pdf->SetTextColor(0, 0, 0);
            }
            if ($emailFailed > 0) {
                $pdf->SetTextColor(220, 38, 38);
                $pdf->Cell(0, 5, $this->pdfText("  Fehlgeschlagen: {$emailFailed}"), 0, 1);
                $pdf->SetTextColor(0, 0, 0);
            }
            if ($emailPending > 0) {
                $pdf->Cell(0, 5, $this->pdfText("  Ausstehend: {$emailPending}"), 0, 1);
            }
            $pdf->Ln(2);

            // Detail-Box Druck
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->Cell(0, 6, $this->pdfText('Druck-Versand:'), 0, 1);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->Cell(0, 5, $this->pdfText("  Anzahl: {$printInvoices->count()} | Betrag: " . number_format($printAmount, 2, ',', '.') . " EUR"), 0, 1);
            if ($printDone > 0) {
                $pdf->SetTextColor(22, 163, 74);
                $pdf->Cell(0, 5, $this->pdfText("  Gedruckt: {$printDone}"), 0, 1);
                $pdf->SetTextColor(0, 0, 0);
            }
            if ($printPending > 0) {
                $pdf->Cell(0, 5, $this->pdfText("  Ausstehend: {$printPending}"), 0, 1);
            }

            $pdf->Ln(5);
            $pdf->SetFont('Helvetica', 'I', 8);
            $pdf->SetTextColor(128, 128, 128);
            $pdf->Cell(0, 5, $this->pdfText('Erstellt am ' . now()->format('d.m.Y') . ' um ' . now()->format('H:i') . ' Uhr'), 0, 1, 'R');
            $pdf->SetTextColor(0, 0, 0);

            // Speichern und Download
            $filename = 'reports/Versandbericht_' . now()->format('Y-m-d_His') . '.pdf';
            $outputPath = Storage::path($filename);
            $dir = dirname($outputPath);
            if (! file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
            $pdf->Output($outputPath, 'F');

            $this->lastReportPath = $filename;

            Notification::make()
                ->title(__('partner.invoice_batch.messages.report_created'))
                ->success()
                ->send();

            $reportFilename = basename($filename);
            $this->redirect(route('report.download', ['filename' => $reportFilename]));
        } catch (\Exception $e) {
            Notification::make()
                ->title('Fehler: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function pdfText(string $text): string
    {
        // Haeufige UTF-8 Sonderzeichen die iconv TRANSLIT nicht korrekt mappt
        $replacements = [
            "\xE2\x80\x93" => '-',   // EN DASH
            "\xE2\x80\x94" => '-',   // EM DASH
            "\xE2\x80\x9E" => '"',   // DOUBLE LOW-9 QUOTE (deutsch oeffnend)
            "\xE2\x80\x9C" => '"',   // LEFT DOUBLE QUOTE
            "\xE2\x80\x9D" => '"',   // RIGHT DOUBLE QUOTE
            "\xE2\x80\x98" => "'",   // LEFT SINGLE QUOTE
            "\xE2\x80\x99" => "'",   // RIGHT SINGLE QUOTE
            "\xE2\x80\xA6" => '...', // ELLIPSIS
            "\xC3\x9F" => "\xDF",    // ß → ISO-8859-1 ß
        ];
        $text = str_replace(array_keys($replacements), array_values($replacements), $text);

        // UTF-8 → ISO-8859-1 (Umlaute aeoeue bleiben erhalten)
        $result = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);

        return $result !== false ? $result : (@iconv('UTF-8', 'ISO-8859-1//IGNORE', $text) ?: $text);
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

    // ── Tab-Daten (paginiert) ──

    public int $emailPage = 1;
    public int $printPage = 1;
    public int $errorPage = 1;
    public int $duplicatePage = 1;
    public int $perPage = 25;

    private function emailQuery()
    {
        return Invoice::where('batch_id', $this->record->id)
            ->where('import_status', ImportStatus::SUCCESS->value)
            ->whereHas('corporateCustomer', fn ($q) => $q->where('send_via_email', true))
            ->with(['corporateCustomer', 'gasStation'])
            ->orderBy('invoice_number');
    }

    private function printQuery()
    {
        return Invoice::where('batch_id', $this->record->id)
            ->where('import_status', ImportStatus::SUCCESS->value)
            ->whereHas('corporateCustomer', fn ($q) => $q->where('send_via_print', true))
            ->with(['corporateCustomer', 'gasStation'])
            ->orderBy('invoice_number');
    }

    private function errorQuery()
    {
        return Invoice::where('batch_id', $this->record->id)
            ->where('import_status', ImportStatus::ERROR->value)
            ->with(['corporateCustomer', 'gasStation'])
            ->orderBy('invoice_number');
    }

    private function duplicateQuery()
    {
        return Invoice::where('batch_id', $this->record->id)
            ->where('import_status', ImportStatus::DUPLICATE->value)
            ->with(['corporateCustomer', 'gasStation'])
            ->orderBy('invoice_number');
    }

    /** Fuer Tab-Badges (nur zaehlen, kein Laden) */
    public function getEmailInvoicesCount(): int
    {
        return $this->emailQuery()->count();
    }

    public function getPrintInvoicesCount(): int
    {
        return $this->printQuery()->count();
    }

    public function getErrorInvoicesCount(): int
    {
        return $this->errorQuery()->count();
    }

    public function getDuplicateInvoicesCount(): int
    {
        return $this->duplicateQuery()->count();
    }

    /** Fuer Tab-Inhalte (paginiert) */
    public function getEmailInvoices()
    {
        return $this->emailQuery()->paginate($this->perPage, ['*'], 'emailPage', $this->emailPage);
    }

    public function getPrintInvoices()
    {
        return $this->printQuery()->paginate($this->perPage, ['*'], 'printPage', $this->printPage);
    }

    public function getErrorInvoices()
    {
        return $this->errorQuery()->paginate($this->perPage, ['*'], 'errorPage', $this->errorPage);
    }

    public function getDuplicateInvoices()
    {
        return $this->duplicateQuery()->paginate($this->perPage, ['*'], 'duplicatePage', $this->duplicatePage);
    }

    /** Pagination-Navigation */
    public function goToEmailPage(int $page): void { $this->emailPage = $page; }
    public function goToPrintPage(int $page): void { $this->printPage = $page; }
    public function goToErrorPage(int $page): void { $this->errorPage = $page; }
    public function goToDuplicatePage(int $page): void { $this->duplicatePage = $page; }

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
            Notification::make()->title(__('partner.invoice_batch.messages.no_email_address'))->danger()->send();

            return;
        }

        try {
            Mail::to($invoice->corporateCustomer->email)
                ->send(new InvoiceMail($invoice));

            $invoice->update(['email_status' => EmailStatus::SENT->value, 'sent_at' => now()]);
            InvoiceLog::logEmail($invoice, EmailStatus::SENT->value, $invoice->corporateCustomer->email, null, $invoice->batch_id);

            Notification::make()->title(__('partner.invoice_batch.messages.email_sent_to', ['email' => $invoice->corporateCustomer->email]))->success()->send();
        } catch (\Exception $e) {
            $invoice->update([
                'email_status' => EmailStatus::FAILED->value,
                'email_error' => substr($e->getMessage(), 0, 500),
            ]);
            InvoiceLog::logEmail($invoice, EmailStatus::FAILED->value, $invoice->corporateCustomer->email, $e->getMessage(), $invoice->batch_id);
            Notification::make()->title('Fehler: ' . $e->getMessage())->danger()->send();
        }
    }
}
