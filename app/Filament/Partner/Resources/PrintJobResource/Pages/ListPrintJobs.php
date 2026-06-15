<?php

namespace App\Filament\Partner\Resources\PrintJobResource\Pages;

use App\Filament\Partner\Resources\PrintJobResource;
use Filament\Resources\Pages\ListRecords;

class ListPrintJobs extends ListRecords
{
    protected static string $resource = PrintJobResource::class;
}
