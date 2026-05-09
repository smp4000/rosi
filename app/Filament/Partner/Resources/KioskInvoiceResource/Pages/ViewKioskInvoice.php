<?php

namespace App\Filament\Partner\Resources\KioskInvoiceResource\Pages;

use App\Filament\Partner\Resources\KioskInvoiceResource;
use Filament\Resources\Pages\ViewRecord;

class ViewKioskInvoice extends ViewRecord
{
    protected static string $resource = KioskInvoiceResource::class;

    protected string $view = 'filament.partner.resources.kiosk-invoice.view';
}
