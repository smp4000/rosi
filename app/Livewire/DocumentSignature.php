<?php

namespace App\Livewire;

use App\Services\DocumentService;
use Livewire\Component;

/**
 * Oeffentliche Signatur-Seite.
 * Mitarbeiter kann Dokument lesen und digital unterschreiben — ohne Login.
 */
class DocumentSignature extends Component
{
    public string $token;

    public ?string $documentTitle = null;
    public ?string $documentContent = null;
    public ?string $documentType = null;
    public ?string $companyName = null;
    public bool $requiresSignature = false;
    public bool $alreadySigned = false;
    public bool $expired = false;
    public bool $notFound = false;
    public bool $signed = false;

    // Signatur-Daten
    public ?string $signatureData = null;
    public bool $confirmed = false;

    public function mount(string $token): void
    {
        $this->token = $token;

        $service = app(DocumentService::class);
        $document = $service->findBySignatureToken($token);

        if (! $document) {
            // Pruefen ob Token existiert aber abgelaufen
            $expired = \App\Models\Document::where('signature_token', $token)->first();
            if ($expired) {
                $this->expired = true;
            } else {
                $this->notFound = true;
            }
            return;
        }

        if ($document->isSigned()) {
            $this->alreadySigned = true;
            $this->documentTitle = $document->title;
            return;
        }

        $this->documentTitle = $document->title;
        $this->documentContent = $document->content;
        $this->documentType = $document->type;
        $this->requiresSignature = $document->requires_signature;
        $this->companyName = $document->tenant?->company_name ?? $document->tenant?->name ?? '';
    }

    public function submitSignature(): void
    {
        if (! $this->signatureData) {
            $this->addError('signatureData', 'Bitte unterschreiben Sie im Feld oben.');
            return;
        }

        if (! $this->confirmed) {
            $this->addError('confirmed', 'Bitte bestaetigen Sie, dass Sie das Dokument gelesen haben.');
            return;
        }

        $service = app(DocumentService::class);
        $document = $service->findBySignatureToken($this->token);

        if (! $document) {
            $this->notFound = true;
            return;
        }

        // Unterschreiben
        $service->signDocument(
            document: $document,
            signatureData: $this->signatureData,
            signedByName: $document->user?->name ?? 'Unbekannt',
            ip: request()->ip(),
        );

        // PDF neu generieren mit Unterschrift
        $service->generatePdf($document->fresh());

        $this->signed = true;
    }

    public function render()
    {
        return view('livewire.document-signature')
            ->layout('layouts.public', ['title' => $this->documentTitle ?? 'Dokument unterschreiben']);
    }
}
