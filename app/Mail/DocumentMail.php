<?php

namespace App\Mail;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * E-Mail mit Dokument als PDF-Anhang.
 */
class DocumentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Document $document,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Dokument: ' . $this->document->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.document',
            with: [
                'document' => $this->document,
                'employeeName' => $this->document->user?->name ?? '',
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->document->file_path) {
            return [];
        }

        $path = Storage::disk('local')->path($this->document->file_path);

        return [
            Attachment::fromPath($path)
                ->as($this->document->title . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
