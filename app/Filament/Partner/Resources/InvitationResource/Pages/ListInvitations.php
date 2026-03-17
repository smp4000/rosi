<?php

namespace App\Filament\Partner\Resources\InvitationResource\Pages;

use App\Filament\Partner\Resources\InvitationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInvitations extends ListRecords
{
    protected static string $resource = InvitationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('partner.invitation.actions.create')),
        ];
    }
}
