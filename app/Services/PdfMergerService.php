<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

/**
 * PDF-Merger-Service: Mehrere Rechnungs-PDFs zu einem Sammel-PDF zusammenfuehren.
 * Adaptiert von TMS_Invoicing.
 */
class PdfMergerService
{
    /**
     * Merged mehrere Rechnungs-PDFs zu einem Sammel-PDF.
     *
     * @return string Relativer Storage-Pfad zum Merge-PDF
     */
    public function mergePdfs(Collection $invoices): string
    {
        $pdf = new Fpdi();

        foreach ($invoices as $invoice) {
            if (! $invoice->pdf_path || ! Storage::exists($invoice->pdf_path)) {
                continue;
            }

            $pdfPath = Storage::path($invoice->pdf_path);

            try {
                $pageCount = $pdf->setSourceFile($pdfPath);

                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $templateId = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($templateId);

                    $pdf->AddPage(
                        $size['orientation'],
                        [$size['width'], $size['height']]
                    );

                    $pdf->useTemplate($templateId);
                }
            } catch (\Exception $e) {
                \Log::error('PDF-Merge-Fehler fuer Invoice ' . $invoice->id, [
                    'error' => $e->getMessage(),
                    'pdf_path' => $pdfPath,
                ]);

                continue;
            }
        }

        // W-1 (Sicherheit): Datei im MANDANTEN-Unterordner ablegen.
        // Die Download-Route erlaubt nur Zugriff auf den Ordner des eigenen
        // Mandanten — so kann Partner A nie die Sammel-PDFs von Partner B laden.
        $tenantFolder = session('tenant_id') ?: 'global';
        $outputFilename = 'print_jobs/' . $tenantFolder . '/Sammel_Druck_' . now()->format('Y-m-d_His') . '.pdf';
        $outputPath = Storage::path($outputFilename);

        $directory = dirname($outputPath);
        if (! file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $pdf->Output($outputPath, 'F');

        return $outputFilename;
    }
}
