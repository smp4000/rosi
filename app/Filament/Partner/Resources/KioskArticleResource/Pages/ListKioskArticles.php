<?php

namespace App\Filament\Partner\Resources\KioskArticleResource\Pages;

use App\Filament\Partner\Resources\KioskArticleResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListKioskArticles extends ListRecords
{
    protected static string $resource = KioskArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_csv')
                ->label('CSV Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function (): StreamedResponse {
                    $filename = 'kiosk-artikel-' . now()->format('Y-m-d') . '.csv';

                    return response()->streamDownload(function () {
                        $tenantId = auth()->user()?->tenant_id;
                        $out = fopen('php://output', 'w');
                        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM
                        fputcsv($out, [
                            'Bezeichnung', 'Objekt', 'EAN', 'Wochentag',
                            'VKP brutto', 'VKP netto', 'EK', 'Marge',
                            'MwSt %', 'Ausgaben', 'Pending', 'Zuletzt',
                        ], ';');

                        \App\Models\Kiosk\Article::where('tenant_id', $tenantId)
                            ->with('issues')
                            ->orderBy('bezeichnung')
                            ->chunk(200, function ($articles) use ($out) {
                                foreach ($articles as $a) {
                                    fputcsv($out, [
                                        $a->bezeichnung,
                                        $a->objekt,
                                        $a->ean ?? '',
                                        $a->weekday !== null ? $a->weekday : '',
                                        number_format((float) $a->aktueller_preis_brutto, 4, ',', ''),
                                        number_format((float) $a->aktueller_preis_netto, 4, ',', ''),
                                        $a->ek !== null ? number_format((float) $a->ek, 4, ',', '') : '',
                                        $a->ek !== null
                                            ? number_format((float) $a->aktueller_preis_netto - (float) $a->ek, 4, ',', '')
                                            : '',
                                        number_format((float) $a->mwst_satz, 2, ',', ''),
                                        $a->issues->pluck('ausgabe')->implode(','),
                                        $a->is_pending ? '1' : '0',
                                        $a->last_seen_at?->format('Y-m-d') ?? '',
                                    ], ';');
                                }
                            });

                        fclose($out);
                    }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
        ];
    }
}
