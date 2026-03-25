<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\TenantSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * E-Mail mit Firmenkundenrechnung als PDF-Anhang.
 * Respektiert TenantSettings (BCC, Betrag anzeigen).
 */
class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    private ?string $tenantId;

    public function __construct(
        public Invoice $invoice,
    ) {
        $this->tenantId = $this->invoice->gasStation?->tenant_id;
    }

    public function envelope(): Envelope
    {
        $stationName = $this->invoice->gasStation?->name ?? 'Tankstelle';

        // BCC aus Settings
        $bcc = [];
        try {
            if (TenantSetting::enabled('bcc_enabled', $this->tenantId, 'invoice')) {
                $bccEmail = TenantSetting::get('bcc_email', null, $this->tenantId, 'invoice');
                if ($bccEmail) {
                    $bcc = [new \Illuminate\Mail\Mailables\Address($bccEmail)];
                }
            }
        } catch (\Exception $e) {
            // Settings nicht verfuegbar, ignorieren
        }

        return new Envelope(
            subject: "Rechnung {$this->invoice->invoice_number} / {$stationName}",
            bcc: $bcc,
        );
    }

    public function content(): Content
    {
        $station = $this->invoice->gasStation;
        $stationStreet = $station?->street ?? '';
        $stationCity = $station?->city ?? '';
        $stationAddress = collect([$stationStreet, "{$station?->zip} {$stationCity}"])
            ->filter()
            ->implode(', ');

        // Betrag anzeigen aus Settings (Default: true)
        $showAmount = true;
        try {
            $val = TenantSetting::get('show_amount_in_email', null, $this->tenantId, 'invoice');
            $showAmount = ($val === null) ? true : TenantSetting::enabled('show_amount_in_email', $this->tenantId, 'invoice');
        } catch (\Exception $e) {
            // Settings nicht verfuegbar
        }

        return new Content(
            view: 'emails.invoice',
            with: [
                'invoice' => $this->invoice,
                'customer' => $this->invoice->corporateCustomer,
                'stationName' => $station?->name ?? 'Tankstelle',
                'stationAddress' => $stationAddress,
                'stationCity' => $stationCity,
                'showAmount' => $showAmount,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->invoice->pdf_path || ! Storage::exists($this->invoice->pdf_path)) {
            return [];
        }

        $filename = 'Rechnung_' . $this->invoice->invoice_number . '.pdf';

        return [
            Attachment::fromStorage($this->invoice->pdf_path)
                ->as($filename)
                ->withMime('application/pdf'),
        ];
    }
}
