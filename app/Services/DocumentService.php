<?php

namespace App\Services;

use App\Mail\DocumentMail;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\GasStation;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Service fuer Dokumenten-Verwaltung.
 * Template-Rendering, PDF-Generierung, Signatur und Versand.
 */
class DocumentService
{
    /**
     * Dokument aus Vorlage erstellen.
     */
    public function createFromTemplate(
        DocumentTemplate $template,
        User $employee,
        ?GasStation $station,
        User $createdBy,
    ): Document {
        $variables = $this->buildVariableMap($employee, $station);
        $content = $this->replacePlaceholders($template->content, $variables);
        $headerHtml = $template->header_html
            ? $this->replacePlaceholders($template->header_html, $variables)
            : null;
        $footerHtml = $template->footer_html
            ? $this->replacePlaceholders($template->footer_html, $variables)
            : null;

        return Document::create([
            'tenant_id' => $createdBy->tenant_id,
            'user_id' => $employee->id,
            'gas_station_id' => $station?->id,
            'template_id' => $template->id,
            'type' => $template->type,
            'category' => $template->category ?? 'hr',
            'title' => $this->replacePlaceholders($template->name, $variables),
            'content' => $content,
            'status' => 'draft',
            'version' => 1,
            'requires_signature' => true,
            'created_by' => $createdBy->id,
        ]);
    }

    /**
     * Platzhalter im Template-Inhalt ersetzen.
     */
    public function replacePlaceholders(string $html, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $html = str_replace('{{' . $key . '}}', (string) ($value ?? ''), $html);
        }

        return $html;
    }

    /**
     * Variablen-Map aus Mitarbeiter-, Mandanten- und Stationsdaten aufbauen.
     */
    public function buildVariableMap(User $employee, ?GasStation $station = null): array
    {
        $profile = $employee->employeeProfile;
        $tenant = $employee->tenant;

        $map = [
            // Mitarbeiter
            'mitarbeiter_name' => $employee->name,
            'mitarbeiter_vorname' => $employee->first_name ?? '',
            'mitarbeiter_nachname' => $employee->last_name,
            'mitarbeiter_email' => $employee->email,
            'mitarbeiter_strasse' => $profile?->street ?? '',
            'mitarbeiter_plz' => $profile?->zip ?? '',
            'mitarbeiter_stadt' => $profile?->city ?? '',
            'mitarbeiter_adresse' => $profile?->full_address ?? '',
            'mitarbeiter_geburtsdatum' => $profile?->date_of_birth ? $profile->date_of_birth : '',
            'mitarbeiter_geburtsort' => $profile?->place_of_birth ?? '',
            'mitarbeiter_sv_nummer' => $profile?->social_security_number ?? '',
            'mitarbeiter_steuer_id' => $profile?->tax_id ?? '',
            'mitarbeiter_steuerklasse' => $profile?->tax_class ?? '',
            'mitarbeiter_krankenkasse' => $profile?->health_insurance_name ?? '',

            // Beschaeftigung
            'beschaeftigungsart' => $profile?->employment_type ?? '',
            'wochenstunden' => $profile?->weekly_hours ?? '',
            'eintrittsdatum' => $profile?->employment_start?->format('d.m.Y') ?? '',
            'austrittsdatum' => $profile?->employment_end?->format('d.m.Y') ?? '',
            'probezeit_ende' => $profile?->probation_end?->format('d.m.Y') ?? '',
            'urlaubstage' => $profile?->vacation_days ?? '',
            'gehalt' => $profile?->salary ?? '',
            'gehaltsart' => $profile?->salary_type ?? '',

            // Mandant / Arbeitgeber
            'arbeitgeber_name' => $tenant?->company_name ?? $tenant?->name ?? '',
            'arbeitgeber_strasse' => $tenant?->street ?? '',
            'arbeitgeber_plz' => $tenant?->zip ?? '',
            'arbeitgeber_stadt' => $tenant?->city ?? '',
            'arbeitgeber_adresse' => trim(($tenant?->street ?? '') . ', ' . ($tenant?->zip ?? '') . ' ' . ($tenant?->city ?? '')),

            // Tankstelle
            'tankstelle_name' => $station?->name ?? '',
            'tankstelle_adresse' => $station ? trim(($station->street ?? '') . ' ' . ($station->house_number ?? '') . ', ' . ($station->zip ?? '') . ' ' . ($station->city ?? '')) : '',

            // Datum
            'datum_heute' => now()->format('d.m.Y'),
            'datum_jahr' => now()->format('Y'),
            'ort_datum' => ($station?->city ?? $tenant?->city ?? '') . ', ' . now()->format('d.m.Y'),
        ];

        // Benutzerdefinierte Platzhalter aus PlaceholderSettings laden
        $customPlaceholders = \App\Models\PlaceholderSetting::getVariableMap($employee->tenant_id);
        $map = array_merge($map, $customPlaceholders);

        return $map;
    }

    /**
     * PDF aus Dokument generieren und speichern.
     */
    public function generatePdf(Document $document): string
    {
        $html = view('pdf.document', [
            'document' => $document,
            'content' => $document->content,
        ])->render();

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('A4');

        $directory = "documents/{$document->tenant_id}";
        $filename = "{$document->id}.pdf";
        $path = "{$directory}/{$filename}";

        Storage::disk('local')->makeDirectory($directory);
        Storage::disk('local')->put($path, $pdf->output());

        $document->update([
            'file_path' => $path,
            'file_size' => Storage::disk('local')->size($path),
        ]);

        return $path;
    }

    /**
     * Dokument vom Mitarbeiter unterschreiben.
     */
    public function signDocument(Document $document, string $signatureData, string $signedByName, string $ip): void
    {
        $document->update([
            'signature_data' => $signatureData,
            'signed_by_name' => $signedByName,
            'signed_by_ip' => $ip,
            'signed_at' => now(),
            'status' => 'signed',
        ]);
    }

    /**
     * Dokument vom Partner gegenzeichnen.
     */
    public function counterSignDocument(Document $document, string $signatureData, User $partner): void
    {
        $document->update([
            'counter_signature_data' => $signatureData,
            'counter_signed_at' => now(),
            'status' => 'counter_signed',
        ]);
    }

    /**
     * Signatur-Token generieren fuer oeffentliche Unterschrift-Seite.
     */
    public function generateSignatureToken(Document $document, int $expiryDays = 30): string
    {
        $token = bin2hex(random_bytes(32));

        $document->update([
            'signature_token' => $token,
            'signature_token_expires_at' => now()->addDays($expiryDays),
            'status' => $document->status === 'draft' ? 'pending_signature' : $document->status,
        ]);

        return $token;
    }

    /**
     * Dokument ueber Token finden (fuer oeffentliche Signatur-Seite).
     */
    public function findBySignatureToken(string $token): ?Document
    {
        $document = Document::where('signature_token', $token)
            ->where('signature_token_expires_at', '>', now())
            ->first();

        return $document;
    }

    /**
     * Signatur-URL generieren.
     */
    public function getSignatureUrl(Document $document): ?string
    {
        if (! $document->signature_token) {
            return null;
        }

        return url("/dokument/unterschreiben/{$document->signature_token}");
    }

    /**
     * Dokument per E-Mail versenden — mit Signatur-Link falls Unterschrift noetig.
     * Zusaetzlich ueber Telegram + In-App Chat (wenn aktiviert).
     */
    public function sendDocument(Document $document, string $email, ?User $sender = null): void
    {
        // PDF generieren falls noch nicht vorhanden
        if (! $document->file_path) {
            $this->generatePdf($document);
            $document->refresh();
        }

        // Signatur-Token generieren falls Unterschrift noetig
        if ($document->requires_signature && ! $document->signature_token) {
            $this->generateSignatureToken($document);
            $document->refresh();
        }

        // 1. E-Mail senden (Hauptkanal — immer)
        Mail::to($email)->send(new DocumentMail($document));

        // 2. Multi-Channel Versand (Telegram + In-App Chat)
        $recipient = $document->user;
        if ($recipient) {
            try {
                $dispatcher = app(MessagingDispatcher::class);
                $dispatcher->dispatchDocument($recipient, $document, $sender ?? auth()->user());
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Multi-Channel Dokument-Versand fehlgeschlagen', [
                    'document_id' => $document->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $document->update([
            'sent_at' => now(),
            'sent_to_email' => $email,
            'status' => $document->requires_signature ? 'pending_signature' : 'sent',
        ]);
    }

    /**
     * Dokument widerrufen.
     */
    public function revokeDocument(Document $document): void
    {
        $document->update(['status' => 'revoked']);
    }

    /**
     * Status-Uebergang validieren.
     */
    public function canTransitionTo(Document $document, string $newStatus): bool
    {
        $allowed = match ($document->status) {
            'draft' => ['pending_signature', 'sent'],
            'pending_signature' => ['signed', 'revoked'],
            'sent' => ['signed', 'revoked'],
            'signed' => ['counter_signed', 'archived', 'revoked'],
            'counter_signed' => ['archived', 'revoked'],
            default => [],
        };

        return in_array($newStatus, $allowed);
    }
}
