<?php

namespace App\Services;

use App\Enums\BatchStatus;
use App\Enums\ImportStatus;
use App\Enums\InvoiceStatus;
use App\Models\CorporateCustomer;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Batch-Verarbeitungs-Service fuer ZUGFeRD-PDF-Rechnungen.
 * Adaptiert von TMS_Invoicing ProcessBatchService.
 * Laeuft synchron (XAMPP, kein Queue-Worker).
 */
class InvoiceBatchService
{
    private ZugferdParserService $parser;
    private InvoiceBatch $batch;
    private int $processedCount = 0;

    public function __construct(ZugferdParserService $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Verarbeitet einen kompletten Batch.
     */
    public function processBatch(InvoiceBatch $batch, array $pdfPaths): void
    {
        $this->batch = $batch;
        $this->processedCount = 0;

        ini_set('memory_limit', '2048M');
        set_time_limit(0);

        $batch->update([
            'status' => BatchStatus::PROCESSING->value,
            'started_at' => now(),
        ]);

        foreach ($pdfPaths as $pdfPath) {
            try {
                DB::transaction(function () use ($pdfPath) {
                    $this->processSinglePdf($pdfPath);
                });
            } catch (\Exception $e) {
                Log::error('Batch-Verarbeitung Fehler', [
                    'batch_id' => $batch->id,
                    'pdf' => basename($pdfPath),
                    'error' => $e->getMessage(),
                ]);

                $this->createErrorInvoice(
                    $pdfPath,
                    'Verarbeitungsfehler: ' . $e->getMessage()
                );
            }

            $this->processedCount++;
            $batch->increment('processed_count');

            // Memory-Management alle 50 PDFs
            if ($this->processedCount % 50 === 0) {
                gc_collect_cycles();
            }
        }

        $batch->update([
            'status' => BatchStatus::COMPLETED->value,
            'completed_at' => now(),
        ]);
    }

    /**
     * Verarbeitet eine einzelne PDF.
     */
    private function processSinglePdf(string $pdfPath): void
    {
        $filename = basename($pdfPath);

        // 1. ZUGFeRD extrahieren
        $zugferdData = $this->parser->parseZugferdData($pdfPath);
        $filenameData = $this->parser->extractFromFilename($filename);

        if (! $zugferdData) {
            $this->createErrorInvoice(
                $pdfPath,
                'Keine ZUGFeRD-Daten gefunden. PDF enthaelt kein eingebettetes XML.',
                null,
                $filenameData
            );

            return;
        }

        // 2. Rechnungsnummer ermitteln
        $invoiceNumber = $zugferdData['invoice_number'] ?? $filenameData['invoice_number'] ?? 'UNBEKANNT-' . uniqid();

        // 3. Duplikat-Check
        $gasStationId = $zugferdData['station']->id ?? $this->batch->gas_station_id;
        $existingInvoice = Invoice::where('invoice_number', $invoiceNumber)
            ->where('gas_station_id', $gasStationId)
            ->first();

        if ($existingInvoice) {
            $this->batch->increment('duplicate_count');

            return;
        }

        // 4. Tankstelle zuordnen
        $station = $zugferdData['station'] ?? null;
        if (! $station && $this->batch->gas_station_id) {
            $station = \App\Models\GasStation::find($this->batch->gas_station_id);
        }

        if (! $station) {
            $this->createErrorInvoice(
                $pdfPath,
                sprintf('Tankstelle nicht gefunden. Adresse: %s, %s %s',
                    $zugferdData['seller_street'] ?? '?',
                    $zugferdData['seller_postal'] ?? '?',
                    $zugferdData['seller_city'] ?? '?'
                ),
                $zugferdData,
                $filenameData
            );

            return;
        }

        // 5. Kunde zuordnen/anlegen
        $customer = $this->parser->findOrCreateCustomer(
            $zugferdData,
            $filenameData['customer_number'] ?? null,
            $station->id
        );

        if (! $customer) {
            $this->createErrorInvoice(
                $pdfPath,
                'Kunde konnte nicht zugeordnet oder angelegt werden.',
                $zugferdData,
                $filenameData
            );

            return;
        }

        $isNewCustomer = $customer->wasRecentlyCreated;

        // 6. PDF in Storage kopieren
        $storagePath = $this->copyPdfToStorage($pdfPath, $filename, $zugferdData, $customer, $station);

        // 7. Rechnung erstellen
        $invoice = Invoice::create([
            'gas_station_id' => $station->id,
            'batch_id' => $this->batch->id,
            'corporate_customer_id' => $customer->id,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => $zugferdData['invoice_date'] ?? now(),
            'amount' => $zugferdData['total_amount'] ?? 0,
            'net_amount' => $zugferdData['net_amount'] ?? null,
            'tax_amount' => $zugferdData['tax_amount'] ?? null,
            'pdf_path' => $storagePath,
            'zugferd_data' => $zugferdData,
            'status' => InvoiceStatus::PROCESSED->value,
            'import_status' => ImportStatus::SUCCESS->value,
        ]);

        // 8. Rechnungspositionen speichern
        if (! empty($zugferdData['items']) && is_array($zugferdData['items'])) {
            foreach ($zugferdData['items'] as $itemData) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'article' => $itemData['name'] ?? null,
                    'quantity' => $itemData['quantity'] ?? 1,
                    'unit_price' => $itemData['unit_price'] ?? 0,
                    'total_price' => $itemData['line_total'] ?? 0,
                    'date' => $zugferdData['invoice_date'] ?? now(),
                ]);
            }
        }

        // 9. Batch-Zaehler
        $this->batch->increment('success_count');

        if ($isNewCustomer) {
            $this->batch->increment('new_customers_count');
        }
    }

    /**
     * Erstellt Fehler-Rechnung fuer nicht zuordenbare PDFs.
     */
    private function createErrorInvoice(
        string $pdfPath,
        string $errorMessage,
        ?array $zugferdData = null,
        ?array $filenameData = null
    ): void {
        $filename = basename($pdfPath);
        $storagePath = $this->copyPdfToStorage($pdfPath, $filename);
        $invoiceNumber = $filenameData['invoice_number']
            ?? $zugferdData['invoice_number']
            ?? 'ERROR-' . now()->format('YmdHis') . '-' . substr(md5($filename), 0, 6);

        Invoice::create([
            'gas_station_id' => $this->batch->gas_station_id,
            'batch_id' => $this->batch->id,
            'corporate_customer_id' => null,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => $zugferdData['invoice_date'] ?? now(),
            'amount' => $zugferdData['total_amount'] ?? 0,
            'net_amount' => $zugferdData['net_amount'] ?? null,
            'tax_amount' => $zugferdData['tax_amount'] ?? null,
            'pdf_path' => $storagePath,
            'zugferd_data' => $zugferdData,
            'status' => InvoiceStatus::UPLOADED->value,
            'import_status' => ImportStatus::ERROR->value,
            'import_error_message' => $errorMessage,
        ]);

        $this->batch->increment('error_count');
    }

    /**
     * Kopiert PDF in Storage mit strukturierter Ordnerstruktur.
     */
    private function copyPdfToStorage(
        string $pdfPath,
        string $filename,
        ?array $zugferdData = null,
        ?CorporateCustomer $customer = null,
        $station = null
    ): string {
        if ($zugferdData && $customer) {
            $invoiceNumber = $zugferdData['invoice_number'] ?? 'unknown';
            $customerNumber = $customer->customer_number ?? '0';
            $customerName = $customer->name ?? 'Kunde';
            $stationNumber = $station?->station_number ?? $station?->station_number_shop ?? $station?->name ?? 'Station';
            $reconstructedFilename = $invoiceNumber . '_' . $customerNumber . '_' . $customerName . '.pdf';

            $storagePath = ZugferdParserService::buildStoragePath(
                $zugferdData['invoice_date'] ?? null,
                $stationNumber,
                (string) $customerNumber,
                $customerName,
                $reconstructedFilename
            );
        } else {
            $storagePath = 'invoices/' . now()->format('Y/m') . '/' . basename($pdfPath);
        }

        if (! file_exists($pdfPath)) {
            \Illuminate\Support\Facades\Log::error('InvoiceBatchService: PDF-Datei nicht gefunden', [
                'pfad' => $pdfPath,
            ]);

            return 'invoices/fehler/' . basename($pdfPath);
        }

        if (! Storage::exists($storagePath)) {
            Storage::put($storagePath, file_get_contents($pdfPath));
        }

        return $storagePath;
    }
}
