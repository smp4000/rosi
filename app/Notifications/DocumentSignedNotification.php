<?php

namespace App\Notifications;

use App\Channels\TelegramChannel;
use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Benachrichtigung wenn ein Mitarbeiter ein Dokument unterschrieben hat.
 * Wird an den Partner / Ersteller gesendet.
 */
class DocumentSignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Document $document,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        // Telegram wenn verknuepft + aktiviert
        if (
            $notifiable->hasTelegram()
            && $notifiable->tenant?->isTelegramEnabled()
        ) {
            $channels[] = TelegramChannel::class;
        }

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        $url = '/dashboard/documents/' . $this->document->id . '/edit';

        return \Filament\Notifications\Notification::make()
            ->title('Dokument unterschrieben')
            ->body("\"{$this->document->title}\" wurde von {$this->document->signed_by_name} unterschrieben.")
            ->icon('heroicon-o-pencil')
            ->success()
            ->actions([
                \Filament\Actions\Action::make('countersign')
                    ->label('Jetzt gegenzeichnen')
                    ->url($url)
                    ->button()
                    ->color('success'),
                \Filament\Actions\Action::make('view')
                    ->label('Ansehen')
                    ->url($url)
                    ->color('gray'),
            ])
            ->getDatabaseMessage();
    }

    public function toTelegram(object $notifiable): array
    {
        return [
            'text' => "✍️ <b>{$this->document->signed_by_name}</b> hat das Dokument <b>\"{$this->document->title}\"</b> unterschrieben.\n\nBitte pruefen und ggf. gegenzeichnen.",
        ];
    }
}
