<?php

namespace App\Notifications;

use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/**
 * Bell-Notification fuer MHD-Warnungen im Partner-Dashboard.
 */
class MhdAlertNotification extends Notification
{
    public function __construct(
        private string $title,
        private string $body,
        private string $level = 'warning',
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
            ->icon($this->level === 'danger' ? 'heroicon-o-exclamation-circle' : 'heroicon-o-exclamation-triangle');

        if ($this->level === 'danger') {
            $notification->danger();
        } else {
            $notification->warning();
        }

        return $notification->getDatabaseMessage();
    }
}
