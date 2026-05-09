<?php

namespace App\Filament\Partner\Resources\KioskArticleResource\Pages;

use App\Filament\Partner\Resources\KioskArticleResource;
use Filament\Resources\Pages\ViewRecord;

class ViewKioskArticle extends ViewRecord
{
    protected static string $resource = KioskArticleResource::class;

    protected string $view = 'filament.partner.resources.kiosk-article.view';
}
