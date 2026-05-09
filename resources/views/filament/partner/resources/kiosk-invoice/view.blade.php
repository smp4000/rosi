@php
    /** @var \App\Models\Kiosk\Invoice $record */
    $record = $this->record;
    $record->load('orderLines.article');
    $lieferungen = $record->orderLines->where('typ', 'lieferung');
    $remissionen = $record->orderLines->where('typ', 'remission');
@endphp

<x-filament-panels::page>
    <div class="space-y-6">

        <div class="rounded-xl bg-white dark:bg-gray-900 shadow p-6">
            <h2 class="text-xl font-bold mb-4">Rechnung {{ $record->rechnungsnummer }}</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div><p class="text-gray-500">Datum</p><p class="font-semibold">{{ $record->rechnungsdatum?->format('d.m.Y') }}</p></div>
                <div><p class="text-gray-500">Lieferzeitraum</p><p class="font-semibold">{{ $record->lieferdatum_von?->format('d.m.Y') }} – {{ $record->lieferdatum_bis?->format('d.m.Y') }}</p></div>
                <div><p class="text-gray-500">Kunden-Nr.</p><p class="font-semibold">{{ $record->kundennummer ?? '—' }}</p></div>
                <div><p class="text-gray-500">Gesamtbetrag</p><p class="font-bold">{{ $record->gesamtbetrag ? number_format((float) $record->gesamtbetrag, 2, ',', '.') . ' €' : '—' }}</p></div>
            </div>
        </div>

        <div class="rounded-xl bg-white dark:bg-gray-900 shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Lieferungen ({{ $lieferungen->count() }})</h3>
            <table class="w-full text-sm">
                <thead class="border-b text-left text-gray-500">
                    <tr><th class="py-2">Datum</th><th class="py-2">LS-Nr</th><th class="py-2">Artikel</th><th class="py-2">KW</th><th class="py-2 text-right">Menge</th><th class="py-2 text-right">EP netto</th><th class="py-2 text-right">Gesamt</th></tr>
                </thead>
                <tbody>
                    @foreach ($lieferungen->sortBy('lieferschein_datum') as $line)
                        <tr class="border-b">
                            <td class="py-2">{{ $line->lieferschein_datum?->format('d.m.Y') }}</td>
                            <td class="py-2">{{ $line->lieferschein_nr }}</td>
                            <td class="py-2">{{ $line->article?->bezeichnung ?? '—' }}</td>
                            <td class="py-2">{{ $line->ausgabe }}</td>
                            <td class="py-2 text-right">{{ $line->menge }}</td>
                            <td class="py-2 text-right">{{ number_format((float) $line->einzelpreis_netto, 4, ',', '.') }} €</td>
                            <td class="py-2 text-right font-semibold">{{ number_format((float) $line->gesamt_netto, 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($remissionen->count())
            <div class="rounded-xl bg-white dark:bg-gray-900 shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Remissionen ({{ $remissionen->count() }})</h3>
                <table class="w-full text-sm">
                    <thead class="border-b text-left text-gray-500">
                        <tr><th class="py-2">Datum</th><th class="py-2">Paket</th><th class="py-2">Artikel</th><th class="py-2">KW</th><th class="py-2 text-right">Menge</th><th class="py-2 text-right">Gesamt</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($remissionen->sortBy('lieferschein_datum') as $line)
                            <tr class="border-b">
                                <td class="py-2">{{ $line->lieferschein_datum?->format('d.m.Y') }}</td>
                                <td class="py-2">{{ $line->paket }}</td>
                                <td class="py-2">{{ $line->article?->bezeichnung ?? '—' }}</td>
                                <td class="py-2">{{ $line->ausgabe }}</td>
                                <td class="py-2 text-right text-red-600">{{ $line->menge }}</td>
                                <td class="py-2 text-right text-red-600 font-semibold">{{ number_format((float) $line->gesamt_netto, 2, ',', '.') }} €</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
