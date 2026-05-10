<x-filament-panels::page>
    @php $kpis = $this->kpis; @endphp

    {{-- KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div class="rounded-xl bg-white dark:bg-gray-900 shadow p-4">
            <p class="text-xs text-gray-500">Rechnungen</p>
            <p class="text-2xl font-bold">{{ $kpis['invoices'] }}</p>
        </div>
        <div class="rounded-xl bg-white dark:bg-gray-900 shadow p-4">
            <p class="text-xs text-gray-500">Artikel</p>
            <p class="text-2xl font-bold">{{ $kpis['articles'] }}</p>
        </div>
        <div class="rounded-xl bg-white dark:bg-gray-900 shadow p-4">
            <p class="text-xs text-gray-500">Pending</p>
            <p class="text-2xl font-bold {{ $kpis['pending'] > 0 ? 'text-amber-600' : '' }}">{{ $kpis['pending'] }}</p>
        </div>
        <div class="rounded-xl bg-white dark:bg-gray-900 shadow p-4">
            <p class="text-xs text-gray-500">Bestellzeilen</p>
            <p class="text-2xl font-bold">{{ $kpis['order_lines'] }}</p>
        </div>
        <div class="rounded-xl bg-white dark:bg-gray-900 shadow p-4">
            <p class="text-xs text-gray-500">Preisaenderungen</p>
            <p class="text-2xl font-bold">{{ $kpis['price_changes'] }}</p>
        </div>
    </div>

    {{-- Upload-Formular: Nach PDF-Auswahl auf "PDF importieren" oben rechts klicken --}}
    <div class="mb-6">
        {{ $this->form }}
        <div class="flex justify-end mt-4">
            <x-filament::button
                wire:click="runImport"
                size="lg"
                color="primary"
                icon="heroicon-o-arrow-up-tray"
            >
                PDF importieren
            </x-filament::button>
        </div>
    </div>

    {{-- Letzte Importe --}}
    <div class="rounded-xl bg-white dark:bg-gray-900 shadow p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4">Letzte Importe</h3>
        @if ($this->recentImports->count())
            <table class="w-full text-sm">
                <thead class="border-b text-left text-gray-500">
                    <tr><th class="py-2">Datum</th><th class="py-2">Datei</th><th class="py-2">Status</th><th class="py-2 text-right">Eingefuegt</th><th class="py-2 text-right">Aktualisiert</th></tr>
                </thead>
                <tbody>
                    @foreach ($this->recentImports as $imp)
                        <tr class="border-b">
                            <td class="py-2">{{ $imp->created_at?->format('d.m.Y H:i') }}</td>
                            <td class="py-2 truncate max-w-xs">{{ $imp->filename ?? '—' }}</td>
                            <td class="py-2">
                                @if ($imp->status === 'success')
                                    <span class="px-2 py-0.5 rounded bg-green-100 text-green-800 text-xs">OK</span>
                                @elseif ($imp->status === 'skipped')
                                    <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-800 text-xs">Skipped</span>
                                @else
                                    <span class="px-2 py-0.5 rounded bg-red-100 text-red-800 text-xs">Fehler</span>
                                @endif
                            </td>
                            <td class="py-2 text-right">{{ $imp->articles_inserted }}</td>
                            <td class="py-2 text-right">{{ $imp->articles_updated }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="text-sm text-gray-500">Noch keine Importe vorhanden.</p>
        @endif
    </div>

    {{-- Letzte Preisaenderungen --}}
    <div class="rounded-xl bg-white dark:bg-gray-900 shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Letzte Preisaenderungen</h3>
        @if ($this->recentPriceChanges->count())
            <table class="w-full text-sm">
                <thead class="border-b text-left text-gray-500">
                    <tr><th class="py-2">Datum</th><th class="py-2">Artikel</th><th class="py-2">Typ</th><th class="py-2">Alt</th><th class="py-2">Neu</th></tr>
                </thead>
                <tbody>
                    @foreach ($this->recentPriceChanges as $pc)
                        <tr class="border-b">
                            <td class="py-2">{{ $pc->changed_at?->format('d.m.Y H:i') }}</td>
                            <td class="py-2">{{ $pc->article?->bezeichnung ?? '—' }}</td>
                            <td class="py-2">{{ $pc->change_type }}</td>
                            <td class="py-2">{{ $pc->old_preis_netto ?? $pc->old_ek ?? '—' }}</td>
                            <td class="py-2 font-semibold">{{ $pc->new_preis_netto ?? $pc->new_ek ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="text-sm text-gray-500">Noch keine Preisaenderungen vorhanden.</p>
        @endif
    </div>
</x-filament-panels::page>
