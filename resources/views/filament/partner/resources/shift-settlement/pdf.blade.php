<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Schichtabrechnung {{ $record->started_at?->format('d.m.Y H:i') }}</title>
    <style>
        @page { margin: 20mm 15mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1f2937; line-height: 1.5; }
        h1 { font-size: 16pt; margin: 0 0 4px 0; color: #111827; }
        h2 { font-size: 12pt; margin: 16px 0 6px 0; padding-bottom: 4px; border-bottom: 1px solid #d1d5db; color: #1e40af; }
        .header { border-bottom: 2px solid #1e40af; padding-bottom: 8px; margin-bottom: 12px; }
        .meta { color: #6b7280; font-size: 9pt; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        th { text-align: left; background: #f3f4f6; padding: 4px 6px; font-size: 9pt; color: #374151; border-bottom: 1px solid #d1d5db; }
        td { padding: 4px 6px; border-bottom: 1px solid #f3f4f6; }
        td.right, th.right { text-align: right; }
        td.center, th.center { text-align: center; }
        .grid-2 { width: 100%; }
        .grid-2 td { width: 50%; vertical-align: top; padding: 4px 12px 4px 0; border: none; }
        .label { color: #6b7280; font-size: 8.5pt; }
        .value { font-weight: bold; font-size: 10pt; }
        .summary-row td { font-weight: bold; background: #f9fafb; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 8px; font-size: 8pt; font-weight: bold; }
        .b-success { background: #d1fae5; color: #065f46; }
        .b-warning { background: #fef3c7; color: #92400e; }
        .b-danger { background: #fee2e2; color: #991b1b; }
        .b-gray { background: #f3f4f6; color: #4b5563; }
        .diff-pos { color: #059669; font-weight: bold; }
        .diff-neg { color: #dc2626; font-weight: bold; }
        .diff-warn { color: #d97706; font-weight: bold; }
        .signature-img { max-width: 200px; max-height: 80px; border: 1px solid #d1d5db; padding: 4px; background: white; }
        .photo { max-width: 80mm; max-height: 60mm; border: 1px solid #d1d5db; }
        .return-block { border: 1px solid #e5e7eb; border-radius: 4px; padding: 6px; margin-bottom: 6px; }
        .small { font-size: 8.5pt; color: #6b7280; }
        .footer { position: fixed; bottom: -10mm; left: 0; right: 0; text-align: center; font-size: 8pt; color: #9ca3af; }
    </style>
</head>
<body>

@php
    $diff = (float) $record->cash_difference;
    $diffClass = $diff > 1.0 ? 'diff-warn' : ($diff < -1.0 ? 'diff-neg' : 'diff-pos');
    $statusLabels = ['active' => 'Offen', 'completed' => 'Abgeschlossen', 'cancelled' => 'Abgebrochen'];
    $statusBadges = ['active' => 'b-warning', 'completed' => 'b-success', 'cancelled' => 'b-gray'];
@endphp

<div class="header">
    <h1>Schichtabrechnung</h1>
    <div class="meta">
        {{ $record->gasStation?->name ?? '—' }}
        — {{ $record->user?->name ?? '—' }}
        — <span class="badge {{ $statusBadges[$record->status] ?? 'b-gray' }}">{{ $statusLabels[$record->status] ?? $record->status }}</span>
    </div>
</div>

{{-- Kopfdaten --}}
<table class="grid-2">
    <tr>
        <td><div class="label">Beginn</div><div class="value">{{ $record->started_at?->format('d.m.Y H:i') ?? '—' }}</div></td>
        <td><div class="label">Ende</div><div class="value">{{ $record->ended_at?->format('d.m.Y H:i') ?? '— laufend —' }}</div></td>
    </tr>
    <tr>
        <td><div class="label">Tresor-Summe</div><div class="value">{{ number_format((float) $record->safe_total, 2, ',', '.') }} €</div></td>
        <td><div class="label">Differenz</div><div class="value {{ $diffClass }}">{{ ($diff >= 0 ? '+' : '') . number_format($diff, 2, ',', '.') }} €</div></td>
    </tr>
</table>

{{-- Kassenbericht --}}
<h2>Kassenbericht</h2>
<table>
    <tr>
        <td>Soll (Kassensystem)</td>
        <td class="right">{{ number_format((float) $record->cash_report_soll, 2, ',', '.') }} €</td>
    </tr>
    <tr>
        <td>Ist (Bargeld in Kasse)</td>
        <td class="right">{{ number_format((float) $record->cash_remaining, 2, ',', '.') }} €</td>
    </tr>
    <tr class="summary-row">
        <td>Differenz</td>
        <td class="right {{ $diffClass }}">{{ ($diff >= 0 ? '+' : '') . number_format($diff, 2, ',', '.') }} €</td>
    </tr>
</table>

{{-- Muenzrollen-Verbrauch --}}
@php
    $usedRolls = $record->coinRolls->filter(fn ($r) => $r->count_used > 0)->sortByDesc('denomination');
@endphp
@if ($usedRolls->count())
    <h2>Muenzrollen-Verbrauch</h2>
    <table>
        <thead>
            <tr>
                <th>Stueckelung</th>
                <th class="right">Anzahl Rollen</th>
                <th class="right">Rollenwert</th>
                <th class="right">Wert verbraucht</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($usedRolls as $roll)
                @php
                    $label = match ($roll->denomination) {
                        200 => '2,00 €', 100 => '1,00 €', 50 => '0,50 €', 20 => '0,20 €',
                        10 => '0,10 €', 5 => '0,05 €', 2 => '0,02 €', 1 => '0,01 €',
                        default => $roll->denomination . ' ct',
                    };
                @endphp
                <tr>
                    <td>{{ $label }} Muenze</td>
                    <td class="right">{{ $roll->count_used }}</td>
                    <td class="right">{{ number_format((float) $roll->roll_value, 2, ',', '.') }} €</td>
                    <td class="right">{{ number_format((float) $roll->value_used, 2, ',', '.') }} €</td>
                </tr>
            @endforeach
            <tr class="summary-row">
                <td colspan="3" class="right">Summe:</td>
                <td class="right">{{ number_format((float) $usedRolls->sum('value_used'), 2, ',', '.') }} €</td>
            </tr>
        </tbody>
    </table>
@endif

{{-- Zaehlerstaende-Verbrauch --}}
@php
    $usedCounters = $record->counters->filter(fn ($c) => (float) $c->value_used != 0.0);
@endphp
@if ($usedCounters->count())
    <h2>Zaehlerstaende-Verbrauch</h2>
    <table>
        <thead>
            <tr>
                <th>Zaehler</th>
                <th class="right">Verbrauch</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($usedCounters as $counter)
                @php
                    $used = (float) $counter->value_used;
                    $unit = $counter->counter_type === 'hermes' ? '€' : 'Stueck';
                    $value = $unit === '€'
                        ? number_format($used, 2, ',', '.')
                        : rtrim(rtrim(number_format($used, 2, ',', '.'), '0'), ',');
                @endphp
                <tr>
                    <td>{{ $counter->counter_label ?: $counter->counter_type }}</td>
                    <td class="right">{{ $value }} {{ $unit }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- Tresor-Einlagen --}}
@if ($record->safeDeposits->count())
    <h2>Tresor-Einlagen ({{ $record->safeDeposits->count() }})</h2>
    <table>
        <thead>
            <tr>
                <th>Zeit</th>
                <th>Barcode</th>
                <th class="right">Betrag</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($record->safeDeposits->sortBy('deposited_at') as $deposit)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($deposit->deposited_at)->format('d.m.Y H:i') }}</td>
                    <td class="small">{{ $deposit->barcode }}</td>
                    <td class="right">{{ number_format((float) $deposit->amount, 2, ',', '.') }} €</td>
                </tr>
            @endforeach
            <tr class="summary-row">
                <td colspan="2" class="right">Summe:</td>
                <td class="right">{{ number_format((float) $record->safeDeposits->sum('amount'), 2, ',', '.') }} €</td>
            </tr>
        </tbody>
    </table>
@endif

{{-- Warenruecknahmen --}}
@if ($record->returns->count())
    <h2>Warenruecknahmen ({{ $record->returns->count() }})</h2>
    @foreach ($record->returns->sortBy('time') as $return)
        <div class="return-block">
            <table>
                <tr>
                    <td style="width: 25%;"><span class="label">Bonnummer:</span> {{ $return->receipt_number ?: '—' }}</td>
                    <td style="width: 20%;"><span class="label">Zeit:</span> {{ $return->time ?: '—' }}</td>
                    <td style="width: 35%;"><span class="label">Grund:</span> {{ $return->reason ?: '—' }}</td>
                    <td class="right"><strong>{{ number_format((float) $return->amount, 2, ',', '.') }} €</strong></td>
                </tr>
            </table>
            @if ($return->photo && file_exists(public_path('storage/' . $return->photo)))
                <img src="{{ public_path('storage/' . $return->photo) }}" class="photo" style="margin-top: 4px;">
            @endif
        </div>
    @endforeach
    <p class="right"><strong>Summe Ruecknahmen: {{ number_format((float) $record->returns->sum('amount'), 2, ',', '.') }} €</strong></p>
@endif

{{-- Prueffragen --}}
@if ($record->checkAnswers->count())
    <h2>Prueffragen</h2>
    <table>
        <thead>
            <tr>
                <th>Frage</th>
                <th class="center" style="width: 60px;">Antwort</th>
                <th>Kommentar</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($record->checkAnswers as $answer)
                <tr>
                    <td>{{ $answer->question?->question_text ?? '—' }}</td>
                    <td class="center">
                        @if ($answer->answer_bool === true)
                            <span class="badge b-success">Ja</span>
                        @elseif ($answer->answer_bool === false)
                            <span class="badge b-danger">Nein</span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="small">{{ $answer->answer_text ?: '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- Bemerkungen --}}
@if ($record->notes)
    <h2>Bemerkungen</h2>
    <p style="white-space: pre-line;">{{ $record->notes }}</p>
@endif

{{-- Kassenbericht-Foto --}}
@if ($record->cash_report_photo && file_exists(public_path('storage/' . $record->cash_report_photo)))
    <h2>Foto vom Kassenbericht-Zettel</h2>
    <img src="{{ public_path('storage/' . $record->cash_report_photo) }}" class="photo">
@endif

{{-- Unterschrift --}}
@if ($record->signature)
    <h2>Unterschrift</h2>
    <img src="{{ $record->signature }}" class="signature-img">
    <div class="small">{{ $record->user?->name ?? '—' }}</div>
@endif

<div class="footer">
    Erstellt am {{ now()->format('d.m.Y H:i') }} — Schicht-ID: {{ $record->id }}
</div>

</body>
</html>
