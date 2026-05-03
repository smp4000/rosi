@php
    /** @var \App\Models\ShiftSettlement $record */
    $record = $this->record;
    $statusLabels = ['active' => 'Offen', 'completed' => 'Abgeschlossen', 'cancelled' => 'Abgebrochen'];
    $statusColors = ['active' => 'bg-amber-100 text-amber-800', 'completed' => 'bg-green-100 text-green-800', 'cancelled' => 'bg-gray-100 text-gray-800'];
    $diff = (float) $record->cash_difference;
    $diffColor = $diff > 1.0 ? 'text-amber-600' : ($diff < -1.0 ? 'text-red-600' : 'text-green-600');
@endphp

<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Kopf --}}
        <div class="rounded-xl bg-white dark:bg-gray-900 shadow p-6">
            <div class="flex flex-wrap justify-between gap-4 mb-4">
                <div>
                    <h2 class="text-xl font-bold">{{ $record->gasStation?->name ?? '—' }}</h2>
                    <p class="text-sm text-gray-500">Mitarbeiter: <span class="font-medium">{{ $record->user?->name ?? '—' }}</span></p>
                </div>
                <span class="px-3 py-1 rounded-full text-sm font-medium {{ $statusColors[$record->status] ?? 'bg-gray-100' }}">
                    {{ $statusLabels[$record->status] ?? $record->status }}
                </span>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <p class="text-gray-500">Beginn</p>
                    <p class="font-semibold">{{ $record->started_at?->format('d.m.Y H:i') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-gray-500">Ende</p>
                    <p class="font-semibold">{{ $record->ended_at?->format('d.m.Y H:i') ?? '— laufend —' }}</p>
                </div>
                <div>
                    <p class="text-gray-500">Tresor-Summe</p>
                    <p class="font-semibold">{{ number_format((float) $record->safe_total, 2, ',', '.') }} €</p>
                </div>
                <div>
                    <p class="text-gray-500">Differenz</p>
                    <p class="font-bold {{ $diffColor }}">{{ ($diff >= 0 ? '+' : '') . number_format($diff, 2, ',', '.') }} €</p>
                </div>
            </div>
        </div>

        {{-- Kassenbericht --}}
        <div class="rounded-xl bg-white dark:bg-gray-900 shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Kassenbericht</h3>
            <div class="grid grid-cols-3 gap-4 mb-4">
                <div>
                    <p class="text-gray-500 text-sm">Soll (Kassensystem)</p>
                    <p class="text-lg font-semibold">{{ number_format((float) $record->cash_report_soll, 2, ',', '.') }} €</p>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Ist (Bargeld in Kasse)</p>
                    <p class="text-lg font-semibold">{{ number_format((float) $record->cash_remaining, 2, ',', '.') }} €</p>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Differenz</p>
                    <p class="text-lg font-bold {{ $diffColor }}">{{ ($diff >= 0 ? '+' : '') . number_format($diff, 2, ',', '.') }} €</p>
                </div>
            </div>

            @if ($record->cash_report_photo)
                <div>
                    <p class="text-sm text-gray-500 mb-2">Foto vom Kassenbericht-Zettel</p>
                    <a href="{{ asset('storage/' . $record->cash_report_photo) }}" target="_blank">
                        <img src="{{ asset('storage/' . $record->cash_report_photo) }}" class="max-w-md rounded-lg border" alt="Kassenbericht">
                    </a>
                </div>
            @endif
        </div>

        {{-- Muenzrollen --}}
        @if ($record->coinRolls->count())
            <div class="rounded-xl bg-white dark:bg-gray-900 shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Muenzrollen</h3>
                <table class="w-full text-sm">
                    <thead class="border-b text-left text-gray-500">
                        <tr>
                            <th class="py-2">Stueckelung</th>
                            <th class="py-2">Anfang</th>
                            <th class="py-2">Ende</th>
                            <th class="py-2">Verbraucht</th>
                            <th class="py-2 text-right">Wert verbraucht</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($record->coinRolls->sortByDesc('denomination') as $roll)
                            <tr class="border-b">
                                <td class="py-2 font-medium">
                                    @php
                                        $label = match ($roll->denomination) {
                                            200 => '2,00 €', 100 => '1,00 €', 50 => '0,50 €', 20 => '0,20 €',
                                            10 => '0,10 €', 5 => '0,05 €', 2 => '0,02 €', 1 => '0,01 €',
                                            default => $roll->denomination . ' ct',
                                        };
                                    @endphp
                                    {{ $label }}
                                </td>
                                <td class="py-2">{{ $roll->count_start }}</td>
                                <td class="py-2">{{ $roll->count_end }}</td>
                                <td class="py-2">{{ $roll->count_used }}</td>
                                <td class="py-2 text-right">{{ number_format((float) $roll->value_used, 2, ',', '.') }} €</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Zaehlerstaende --}}
        @if ($record->counters->count())
            <div class="rounded-xl bg-white dark:bg-gray-900 shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Zaehlerstaende</h3>
                <table class="w-full text-sm">
                    <thead class="border-b text-left text-gray-500">
                        <tr>
                            <th class="py-2">Zaehler</th>
                            <th class="py-2">Anfang</th>
                            <th class="py-2">Ende</th>
                            <th class="py-2 text-right">Verbraucht</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($record->counters as $counter)
                            <tr class="border-b">
                                <td class="py-2 font-medium">{{ $counter->counter_label ?: $counter->counter_type }}</td>
                                <td class="py-2">{{ rtrim(rtrim(number_format((float) $counter->value_start, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="py-2">{{ rtrim(rtrim(number_format((float) $counter->value_end, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="py-2 text-right">{{ rtrim(rtrim(number_format((float) $counter->value_used, 2, ',', '.'), '0'), ',') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Tresor-Einlagen --}}
        @if ($record->safeDeposits->count())
            <div class="rounded-xl bg-white dark:bg-gray-900 shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Tresor-Einlagen ({{ $record->safeDeposits->count() }})</h3>
                <table class="w-full text-sm">
                    <thead class="border-b text-left text-gray-500">
                        <tr>
                            <th class="py-2">Zeit</th>
                            <th class="py-2">Barcode</th>
                            <th class="py-2 text-right">Betrag</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($record->safeDeposits->sortBy('deposited_at') as $deposit)
                            <tr class="border-b">
                                <td class="py-2">{{ \Illuminate\Support\Carbon::parse($deposit->deposited_at)->format('d.m.Y H:i') }}</td>
                                <td class="py-2 font-mono text-xs">{{ $deposit->barcode }}</td>
                                <td class="py-2 text-right font-semibold">{{ number_format((float) $deposit->amount, 2, ',', '.') }} €</td>
                            </tr>
                        @endforeach
                        <tr class="font-bold">
                            <td colspan="2" class="py-2 text-right">Summe:</td>
                            <td class="py-2 text-right">{{ number_format((float) $record->safeDeposits->sum('amount'), 2, ',', '.') }} €</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Warenruecknahmen --}}
        @if ($record->returns->count())
            <div class="rounded-xl bg-white dark:bg-gray-900 shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Warenruecknahmen ({{ $record->returns->count() }})</h3>
                <div class="space-y-4">
                    @foreach ($record->returns->sortBy('time') as $return)
                        <div class="border rounded-lg p-4 flex flex-wrap gap-4">
                            @if ($return->photo)
                                <a href="{{ asset('storage/' . $return->photo) }}" target="_blank" class="shrink-0">
                                    <img src="{{ asset('storage/' . $return->photo) }}" class="w-32 h-32 object-cover rounded-lg border" alt="Bon">
                                </a>
                            @endif
                            <div class="flex-1 min-w-[200px]">
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <div>
                                        <p class="text-gray-500">Bonnummer</p>
                                        <p class="font-medium">{{ $return->receipt_number ?: '—' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">Zeit</p>
                                        <p class="font-medium">{{ $return->time ?: '—' }}</p>
                                    </div>
                                    <div class="col-span-2">
                                        <p class="text-gray-500">Grund</p>
                                        <p class="font-medium">{{ $return->reason ?: '—' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">Betrag</p>
                                        <p class="font-bold text-amber-600">{{ number_format((float) $return->amount, 2, ',', '.') }} €</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                    <div class="text-right font-bold">
                        Summe: {{ number_format((float) $record->returns->sum('amount'), 2, ',', '.') }} €
                    </div>
                </div>
            </div>
        @endif

        {{-- Prueffragen --}}
        @if ($record->checkAnswers->count())
            <div class="rounded-xl bg-white dark:bg-gray-900 shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Prueffragen</h3>
                <div class="space-y-3">
                    @foreach ($record->checkAnswers as $answer)
                        <div class="border-b pb-3">
                            <p class="font-medium">{{ $answer->question?->question_text ?? $answer->question_text }}</p>
                            <p class="mt-1 text-sm">
                                @if ($answer->answer_bool === true)
                                    <span class="px-2 py-0.5 rounded bg-green-100 text-green-800">Ja</span>
                                @elseif ($answer->answer_bool === false)
                                    <span class="px-2 py-0.5 rounded bg-red-100 text-red-800">Nein</span>
                                @endif
                                @if (!empty($answer->answer_text))
                                    <span class="ml-2 text-gray-600">{{ $answer->answer_text }}</span>
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Bemerkungen --}}
        @if ($record->notes)
            <div class="rounded-xl bg-white dark:bg-gray-900 shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Bemerkungen</h3>
                <p class="whitespace-pre-line text-sm">{{ $record->notes }}</p>
            </div>
        @endif

        {{-- Unterschrift --}}
        @if ($record->signature)
            <div class="rounded-xl bg-white dark:bg-gray-900 shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Unterschrift</h3>
                <img src="{{ $record->signature }}" class="max-w-md border rounded-lg bg-white" alt="Unterschrift">
            </div>
        @endif

    </div>
</x-filament-panels::page>
