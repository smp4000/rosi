@php
    /** @var \App\Models\ShiftSettlement $record */
    $record = $this->record;

    $statusLabels = ['active' => 'Offen', 'completed' => 'Abgeschlossen', 'cancelled' => 'Abgebrochen'];
    $statusStyle = [
        'active' => 'background:#fef3c7;color:#92400e;',
        'completed' => 'background:#d1fae5;color:#065f46;',
        'cancelled' => 'background:#f3f4f6;color:#4b5563;',
    ];

    $diff = (float) $record->cash_difference;
    $diffColor = $diff > 1.0 ? '#d97706' : ($diff < -1.0 ? '#dc2626' : '#059669');
    $diffText = ($diff >= 0 ? '+' : '') . number_format($diff, 2, ',', '.') . ' €';

    // gemeinsame Inline-Styles (Tailwind kompiliert hier nicht)
    $card = 'background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.05);padding:20px;margin-bottom:20px;';
    $h3 = 'font-size:16px;font-weight:600;color:#111827;margin:0 0 14px 0;';
    $lbl = 'font-size:12px;color:#6b7280;margin:0 0 2px 0;';
    $val = 'font-size:15px;font-weight:600;color:#111827;margin:0;';
    $th = 'text-align:left;padding:8px 6px;font-size:12px;color:#6b7280;border-bottom:1px solid #e5e7eb;';
    $td = 'padding:8px 6px;border-bottom:1px solid #f3f4f6;font-size:14px;';
@endphp

<x-filament-panels::page>

    {{-- Kopf --}}
    <div style="{{ $card }}">
        <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:18px;">
            <div>
                <h2 style="font-size:20px;font-weight:700;color:#111827;margin:0;">{{ $record->gasStation?->name ?? '—' }}</h2>
                <p style="font-size:13px;color:#6b7280;margin:4px 0 0 0;">Mitarbeiter: <span style="font-weight:600;color:#374151;">{{ $record->user?->name ?? '—' }}</span></p>
            </div>
            <span style="padding:4px 12px;border-radius:9999px;font-size:13px;font-weight:600;{{ $statusStyle[$record->status] ?? 'background:#f3f4f6;color:#4b5563;' }}">
                {{ $statusLabels[$record->status] ?? $record->status }}
            </span>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:16px;">
            <div style="flex:1;min-width:130px;">
                <p style="{{ $lbl }}">Beginn</p>
                <p style="{{ $val }}">{{ $record->started_at?->format('d.m.Y H:i') ?? '—' }}</p>
            </div>
            <div style="flex:1;min-width:130px;">
                <p style="{{ $lbl }}">Ende</p>
                <p style="{{ $val }}">{{ $record->ended_at?->format('d.m.Y H:i') ?? '— laufend —' }}</p>
            </div>
            <div style="flex:1;min-width:130px;">
                <p style="{{ $lbl }}">Tresor-Summe</p>
                <p style="{{ $val }}">{{ number_format((float) $record->safe_total, 2, ',', '.') }} €</p>
            </div>
            <div style="flex:1;min-width:130px;">
                <p style="{{ $lbl }}">Differenz</p>
                <p style="font-size:15px;font-weight:700;margin:0;color:{{ $diffColor }};">{{ $diffText }}</p>
            </div>
        </div>
    </div>

    {{-- Kassenbericht --}}
    <div style="{{ $card }}">
        <h3 style="{{ $h3 }}">Kassenbericht</h3>
        <div style="display:flex;flex-wrap:wrap;gap:16px;margin-bottom:16px;">
            <div style="flex:1;min-width:150px;">
                <p style="{{ $lbl }}">Soll (Kassensystem)</p>
                <p style="font-size:18px;font-weight:600;color:#111827;margin:0;">{{ number_format((float) $record->cash_report_soll, 2, ',', '.') }} €</p>
            </div>
            <div style="flex:1;min-width:150px;">
                <p style="{{ $lbl }}">Ist (Bargeld in Kasse)</p>
                <p style="font-size:18px;font-weight:600;color:#111827;margin:0;">{{ number_format((float) $record->cash_remaining, 2, ',', '.') }} €</p>
            </div>
            <div style="flex:1;min-width:150px;">
                <p style="{{ $lbl }}">Differenz</p>
                <p style="font-size:18px;font-weight:700;margin:0;color:{{ $diffColor }};">{{ $diffText }}</p>
            </div>
        </div>

        @if ($record->cash_report_photo)
            @php $cashUrl = route('shift.attachment', ['settlement' => $record->id, 'type' => 'cash_report']); @endphp
            <p style="{{ $lbl }}">Foto vom Kassenbericht-Zettel</p>
            <a href="{{ $cashUrl }}" target="_blank">
                <img src="{{ $cashUrl }}" style="max-width:360px;width:100%;border-radius:8px;border:1px solid #e5e7eb;" alt="Kassenbericht">
            </a>
        @endif
    </div>

    {{-- Muenzrollen-Verbrauch --}}
    @php $usedRolls = $record->coinRolls->filter(fn ($r) => $r->count_used > 0)->sortByDesc('denomination'); @endphp
    @if ($usedRolls->count())
        <div style="{{ $card }}">
            <h3 style="{{ $h3 }}">Münzrollen-Verbrauch</h3>
            <table style="width:100%;border-collapse:collapse;">
                @foreach ($usedRolls as $roll)
                    @php
                        $label = match ($roll->denomination) {
                            200 => '2,00 €', 100 => '1,00 €', 50 => '0,50 €', 20 => '0,20 €',
                            10 => '0,10 €', 5 => '0,05 €', 2 => '0,02 €', 1 => '0,01 €',
                            default => $roll->denomination . ' ct',
                        };
                    @endphp
                    <tr>
                        <td style="{{ $td }}"><span style="font-weight:600;">{{ $label }}</span> Münze · {{ $roll->count_used }} × {{ number_format((float) $roll->roll_value, 2, ',', '.') }} €</td>
                        <td style="{{ $td }}text-align:right;font-weight:600;">{{ number_format((float) $roll->value_used, 2, ',', '.') }} €</td>
                    </tr>
                @endforeach
                <tr>
                    <td style="padding:10px 6px;font-weight:700;">Summe</td>
                    <td style="padding:10px 6px;text-align:right;font-weight:700;">{{ number_format((float) $usedRolls->sum('value_used'), 2, ',', '.') }} €</td>
                </tr>
            </table>
        </div>
    @endif

    {{-- Zaehlerstaende-Verbrauch --}}
    @php $usedCounters = $record->counters->filter(fn ($c) => (float) $c->value_used != 0.0); @endphp
    @if ($usedCounters->count())
        <div style="{{ $card }}">
            <h3 style="{{ $h3 }}">Zählerstände-Verbrauch</h3>
            <table style="width:100%;border-collapse:collapse;">
                @foreach ($usedCounters as $counter)
                    @php
                        $used = (float) $counter->value_used;
                        $unit = $counter->counter_type === 'hermes' ? '€' : 'Stück';
                        $value = $unit === '€'
                            ? number_format($used, 2, ',', '.')
                            : rtrim(rtrim(number_format($used, 2, ',', '.'), '0'), ',');
                    @endphp
                    <tr>
                        <td style="{{ $td }}font-weight:500;">{{ $counter->counter_label ?: $counter->counter_type }}</td>
                        <td style="{{ $td }}text-align:right;font-weight:600;">{{ $value }} {{ $unit }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    {{-- Tresor-Einlagen --}}
    @if ($record->safeDeposits->count())
        <div style="{{ $card }}">
            <h3 style="{{ $h3 }}">Tresor-Einlagen ({{ $record->safeDeposits->count() }})</h3>
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="{{ $th }}">Zeit</th>
                        <th style="{{ $th }}">Barcode</th>
                        <th style="{{ $th }}text-align:right;">Betrag</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($record->safeDeposits->sortBy('deposited_at') as $deposit)
                        <tr>
                            <td style="{{ $td }}">{{ \Illuminate\Support\Carbon::parse($deposit->deposited_at)->format('d.m.Y H:i') }}</td>
                            <td style="{{ $td }}font-family:monospace;font-size:12px;color:#6b7280;">{{ $deposit->barcode }}</td>
                            <td style="{{ $td }}text-align:right;font-weight:600;">{{ number_format((float) $deposit->amount, 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td colspan="2" style="padding:10px 6px;text-align:right;font-weight:700;">Summe</td>
                        <td style="padding:10px 6px;text-align:right;font-weight:700;">{{ number_format((float) $record->safeDeposits->sum('amount'), 2, ',', '.') }} €</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif

    {{-- Warenruecknahmen --}}
    @if ($record->returns->count())
        <div style="{{ $card }}">
            <h3 style="{{ $h3 }}">Warenrücknahmen ({{ $record->returns->count() }})</h3>
            <div style="display:flex;flex-direction:column;gap:14px;">
                @foreach ($record->returns->sortBy('time') as $return)
                    <div style="border:1px solid #e5e7eb;border-radius:10px;padding:14px;display:flex;flex-wrap:wrap;gap:16px;">
                        @if ($return->photo)
                            @php $bonUrl = route('shift.attachment', ['settlement' => $record->id, 'type' => 'return', 'returnId' => $return->id]); @endphp
                            <a href="{{ $bonUrl }}" target="_blank" style="flex-shrink:0;">
                                <img src="{{ $bonUrl }}" style="width:120px;height:120px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb;" alt="Bon">
                            </a>
                        @endif
                        <div style="flex:1;min-width:220px;display:flex;flex-wrap:wrap;gap:12px;">
                            <div style="min-width:110px;">
                                <p style="{{ $lbl }}">Bonnummer</p>
                                <p style="{{ $val }}">{{ $return->receipt_number ?: '—' }}</p>
                            </div>
                            <div style="min-width:80px;">
                                <p style="{{ $lbl }}">Zeit</p>
                                <p style="{{ $val }}">{{ $return->time ?: '—' }}</p>
                            </div>
                            <div style="flex-basis:100%;">
                                <p style="{{ $lbl }}">Grund</p>
                                <p style="{{ $val }}">{{ $return->reason ?: '—' }}</p>
                            </div>
                            <div>
                                <p style="{{ $lbl }}">Betrag</p>
                                <p style="font-size:15px;font-weight:700;color:#d97706;margin:0;">{{ number_format((float) $return->amount, 2, ',', '.') }} €</p>
                            </div>
                        </div>
                    </div>
                @endforeach
                <div style="text-align:right;font-weight:700;">
                    Summe: {{ number_format((float) $record->returns->sum('amount'), 2, ',', '.') }} €
                </div>
            </div>
        </div>
    @endif

    {{-- Prueffragen --}}
    @if ($record->checkAnswers->count())
        <div style="{{ $card }}">
            <h3 style="{{ $h3 }}">Prüffragen</h3>
            <div style="display:flex;flex-direction:column;gap:12px;">
                @foreach ($record->checkAnswers as $answer)
                    <div style="border-bottom:1px solid #f3f4f6;padding-bottom:12px;">
                        <p style="font-weight:500;color:#111827;margin:0;">{{ $answer->question?->question_text ?? $answer->question_text }}</p>
                        <p style="margin:6px 0 0 0;">
                            @if ($answer->answer_bool === true)
                                <span style="padding:2px 8px;border-radius:6px;font-size:13px;background:#d1fae5;color:#065f46;">Ja</span>
                            @elseif ($answer->answer_bool === false)
                                <span style="padding:2px 8px;border-radius:6px;font-size:13px;background:#fee2e2;color:#991b1b;">Nein</span>
                            @endif
                            @if (!empty($answer->answer_text))
                                <span style="margin-left:8px;color:#4b5563;font-size:13px;">{{ $answer->answer_text }}</span>
                            @endif
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Bemerkungen --}}
    @if ($record->notes)
        <div style="{{ $card }}">
            <h3 style="{{ $h3 }}">Bemerkungen</h3>
            <p style="white-space:pre-line;font-size:14px;color:#374151;margin:0;">{{ $record->notes }}</p>
        </div>
    @endif

    {{-- Unterschrift --}}
    @if ($record->signature)
        <div style="{{ $card }}">
            <h3 style="{{ $h3 }}">Unterschrift</h3>
            <img src="{{ $record->signature }}" style="max-width:360px;width:100%;border:1px solid #e5e7eb;border-radius:8px;background:#fff;" alt="Unterschrift">
        </div>
    @endif

</x-filament-panels::page>
