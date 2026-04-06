<?php

namespace App\Notifications;

use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/**
 * Bell-Notification fuer neue Tankbetrug-Meldungen im Partner-Dashboard.
 */
class FuelTheftNotification extends Notification
{
    public function __construct(
        private string $title,
        private string $body,
        private string $level = 'danger',
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $notification = FilamentNotification::make()
            ->title($this->title)
            ->body($this->body)
            ->icon('heroicon-o-fire');

        if ($this->level === 'danger') {
            $notification->danger();
        } else {
            $notification->warning();
        }

        return $notification->getDatabaseMessage();
    }
}
