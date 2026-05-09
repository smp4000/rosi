@php
    /** @var \App\Models\Kiosk\Article $record */
    $record = $this->record;
    $record->load('issues', 'priceChangeLog');
    $marge = $record->ek !== null ? (float) $record->aktueller_preis_netto - (float) $record->ek : null;
@endphp

<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Stammdaten --}}
        <div class="rounded-xl bg-white dark:bg-gray-900 shadow p-6">
            <h2 class="text-xl font-bold mb-4">{{ $record->bezeichnung }}</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div><p class="text-gray-500">Objekt</p><p class="font-semibold">{{ $record->objekt }}</p></div>
                <div><p class="text-gray-500">EAN</p><p class="font-mono">{{ $record->ean ?? '—' }}</p></div>
                <div><p class="text-gray-500">Lieferant</p><p class="font-semibold">{{ $record->supplier }}</p></div>
                <div><p class="text-gray-500">Wochentag</p><p class="font-semibold">
                    {{ match ($record->weekday) { 1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So', default => '—' } }}
                </p></div>
            </div>
        </div>

        {{-- Preise --}}
        <div class="rounded-xl bg-white dark:bg-gray-900 shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Preise</h3>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
                <div><p class="text-gray-500">VKP brutto</p><p class="text-lg font-bold">{{ number_format((float) $record->aktueller_preis_brutto, 2, ',', '.') }} €</p></div>
                <div><p class="text-gray-500">VKP netto</p><p class="text-lg font-semibold">{{ number_format((float) $record->aktueller_preis_netto, 2, ',', '.') }} €</p></div>
                <div><p class="text-gray-500">EK netto</p><p class="text-lg font-semibold">{{ $record->ek !== null ? number_format((float) $record->ek, 2, ',', '.') . ' €' : '—' }}</p></div>
                <div><p class="text-gray-500">Marge</p><p class="text-lg font-bold {{ $marge !== null && $marge < 0 ? 'text-red-600' : 'text-green-600' }}">{{ $marge !== null ? number_format($marge, 2, ',', '.') . ' €' : '—' }}</p></div>
                <div><p class="text-gray-500">MwSt</p><p class="text-lg font-semibold">{{ number_format((float) $record->mwst_satz, 1, ',', '.') }} %</p></div>
            </div>
        </div>

        {{-- Ausgaben --}}
        @if ($record->issues->count())
            <div class="rounded-xl bg-white dark:bg-gray-900 shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Ausgaben ({{ $record->issues->count() }})</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach ($record->issues->sortByDesc('ausgabe') as $issue)
                        <span class="px-3 py-1 rounded-full bg-blue-100 text-blue-800 text-sm font-medium">KW {{ $issue->ausgabe }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Preisaenderungen --}}
        @if ($record->priceChangeLog->count())
            <div class="rounded-xl bg-white dark:bg-gray-900 shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Preisaenderungen</h3>
                <table class="w-full text-sm">
                    <thead class="border-b text-left text-gray-500">
                        <tr><th class="py-2">Datum</th><th class="py-2">Typ</th><th class="py-2">Alt</th><th class="py-2">Neu</th><th class="py-2">Quelle</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($record->priceChangeLog->sortByDesc('changed_at')->take(20) as $log)
                            <tr class="border-b">
                                <td class="py-2">{{ $log->changed_at?->format('d.m.Y H:i') }}</td>
                                <td class="py-2">{{ $log->change_type }}</td>
                                <td class="py-2">{{ $log->old_preis_netto ?? $log->old_ek ?? '—' }}</td>
                                <td class="py-2">{{ $log->new_preis_netto ?? $log->new_ek ?? '—' }}</td>
                                <td class="py-2">{{ $log->source }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
