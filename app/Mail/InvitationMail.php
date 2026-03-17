<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Einladungs-E-Mail fuer neue Mitarbeiter.
 * Enthaelt den Onboarding-Link mit Token.
 */
class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invitation $invitation,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Einladung zur Mitarbeiter-Registrierung',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invitation',
            with: [
                'invitation' => $this->invitation,
                'onboardingUrl' => route('onboarding', ['token' => $this->invitation->token]),
                'inviterName' => $this->invitation->invitedByUser?->name ?? 'Ihr Arbeitgeber',
                'companyName' => $this->invitation->invitedByUser?->tenant?->company_name
                    ?? $this->invitation->invitedByUser?->tenant?->name
                    ?? 'Ihr neuer Arbeitgeber',
                'personalMessage' => $this->invitation->message,
                'expiresAt' => $this->invitation->expires_at->format('d.m.Y H:i'),
            ],
        );
    }
}
