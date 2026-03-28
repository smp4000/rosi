<?php

namespace App\Filament\Partner\Resources\DeviceInvitationResource\Pages;

use App\Filament\Partner\Resources\DeviceInvitationResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;

class ListDeviceInvitations extends ListRecords
{
    protected static string $resource = DeviceInvitationResource::class;

    public function getHeader(): ?View
    {
        return view('filament.partner.pages.list-devices-header');
    }
}
