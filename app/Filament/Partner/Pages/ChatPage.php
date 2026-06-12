<?php

namespace App\Filament\Partner\Pages;

use Filament\Pages\Page;

/**
 * Chat-Seite im Partner-Panel.
 * Einbettung der ChatPanel Livewire-Komponente.
 */
class ChatPage extends Page
{
    use \App\Filament\Concerns\HasPageCatalogPermission;

    /** Permission fuer den Seitenzugriff (Rollen-Matrix) */
    protected static string $accessPermission = 'partner.chat.use';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Chat';

    protected static ?string $title = 'Chat';

    protected static ?string $slug = 'chat';

    protected static string|\UnitEnum|null $navigationGroup = 'Kommunikation';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.partner.pages.chat-page';

    /**
     * Ungelesene Nachrichten als Badge anzeigen.
     */
    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        $count = \App\Models\ChatMessage::whereHas('conversation', function ($q) use ($user) {
            $q->where('participant_one_id', $user->id)
              ->orWhere('participant_two_id', $user->id);
        })
        ->where('sender_id', '!=', $user->id)
        ->whereNull('read_at')
        ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
