<?php

namespace App\Notifications;

use App\Channels\TelegramChannel;
use App\Models\Document;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Benachrichtigung: Neues Dokument bereit zur Unterschrift/Ansicht.
 * Kanaele: database, mail, telegram (falls verknuepft).
 */
class DocumentReadyNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Document $document,
    ) {}

    /**
     * Kanaele bestimmen.
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'mail'];

        // Telegram hinzufuegen, wenn verknuepft + aktiviert
        if (
            $notifiable->hasTelegram()
            && $notifiable->tenant?->isTelegramEnabled()
        ) {
            $channels[] = TelegramChannel::class;
        }

        return $channels;
    }

    /**
     * E-Mail-Benachrichtigung.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage())
            ->subject("Neues Dokument: {$this->document->title}")
            ->greeting("Hallo {$notifiable->name},")
            ->line("Ihnen wurde ein neues Dokument zugestellt:")
            ->line("**{$this->document->title}**");

        if ($this->document->requires_signature && $this->document->signature_token) {
            $signUrl = route('document.sign', $this->document->signature_token);
            $mail->action('Jetzt unterschreiben', $signUrl)
                 ->line('Bitte unterschreiben Sie das Dokument online.');

            if ($this->document->signature_token_expires_at) {
                $mail->line("Der Link ist gueltig bis: {$this->document->signature_token_expires_at->format('d.m.Y H:i')} Uhr.");
            }
        } else {
            $mail->line('Sie koennen das Dokument in Ihrem ROSI-Portal einsehen.');
        }

        return $mail;
    }

    /**
     * Telegram-Benachrichtigung.
     */
    public function toTelegram(object $notifiable): array
    {
        $messages = [];

        // PDF als Datei senden (falls vorhanden)
        if ($this->document->file_path) {
            $filePath = Storage::disk('local')->path($this->document->file_path);

            if (file_exists($filePath)) {
                $messages['document'] = $filePath;
                $messages['caption'] = "📄 <b>{$this->document->title}</b>";
            }
        }

        // Text + Unterschreiben-Button
        if ($this->document->requires_signature && $this->document->signature_token) {
            $signUrl = route('document.sign', $this->document->signature_token);

            $messages['text'] = "✍️ Bitte unterschreiben Sie das Dokument <b>{$this->document->title}</b> online.";
            $messages['reply_markup'] = TelegramService::inlineKeyboard([
                TelegramService::urlButton('✍️ Jetzt unterschreiben', $signUrl),
            ]);
        } else {
            $messages['text'] = "📄 Neues Dokument: <b>{$this->document->title}</b>\n\nSie koennen es in Ihrem ROSI-Portal einsehen.";
        }

        return $messages;
    }

    /**
     * Datenbank-Benachrichtigung.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'document_ready',
            'document_id' => $this->document->id,
            'document_title' => $this->document->title,
            'requires_signature' => $this->document->requires_signature,
            'message' => $this->document->requires_signature
                ? "Neues Dokument \"{$this->document->title}\" wartet auf Ihre Unterschrift."
                : "Neues Dokument \"{$this->document->title}\" bereitgestellt.",
        ];
    }
}
