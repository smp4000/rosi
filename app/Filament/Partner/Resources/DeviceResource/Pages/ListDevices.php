<?php

namespace App\Filament\Partner\Resources\DeviceResource\Pages;

use App\Filament\Partner\Resources\DeviceResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;

class ListDevices extends ListRecords
{
    protected static string $resource = DeviceResource::class;

    public function getHeader(): ?View
    {
        return view('filament.partner.pages.list-devices-header');
    }
}
