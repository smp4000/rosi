<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Schichtabrechnung {{ $record->started_at?->format('d.m.Y H:i') }}</title>
    <style>
        @page { margin: 12mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #1f2937; line-height: 1.25; }
        h1 { font-size: 13pt; margin: 0 0 2px 0; color: #111827; }
        h2 { font-size: 9.5pt; margin: 8px 0 3px 0; padding-bottom: 2px; border-bottom: 1px solid #d1d5db; color: #1e40af; }
        .header { border-bottom: 2px solid #1e40af; padding-bottom: 4px; margin-bottom: 6px; }
        .meta { color: #6b7280; font-size: 8pt; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 2px; }
        th { text-align: left; background: #f3f4f6; padding: 2px 4px; font-size: 8pt; color: #374151; border-bottom: 1px solid #d1d5db; }
        td { padding: 2px 4px; border-bottom: 1px solid #f3f4f6; }
        td.right, th.right { text-align: right; }
        td.center, th.center { text-align: center; }
        .grid-2 { width: 100%; margin-bottom: 4px; }
        .grid-2 td { width: 50%; vertical-align: top; padding: 1px 8px 1px 0; border: none; }
        .label { color: #6b7280; font-size: 7.5pt; }
        .value { font-weight: bold; font-size: 9pt; }
        .summary-row td { font-weight: bold; background: #f9fafb; }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 6px; font-size: 7.5pt; font-weight: bold; }
        .b-success { background: #d1fae5; color: #065f46; }
        .b-warning { background: #fef3c7; color: #92400e; }
        .b-danger { background: #fee2e2; color: #991b1b; }
        .b-gray { background: #f3f4f6; color: #4b5563; }
        .diff-pos { color: #059669; font-weight: bold; }
        .diff-neg { color: #dc2626; font-weight: bold; }
        .diff-warn { color: #d97706; font-weight: bold; }
        .signature-img { max-width: 140px; max-height: 50px; border: 1px solid #d1d5db; padding: 2px; background: white; }
        .photo { max-width: 50mm; max-height: 35mm; border: 1px solid #d1d5db; }
        .return-block { border: 1px solid #e5e7eb; border-radius: 3px; padding: 4px; margin-bottom: 3px; }
        .small { font-size: 7.5pt; color: #6b7280; }
        .footer { position: fixed; bottom: -8mm; left: 0; right: 0; text-align: center; font-size: 7pt; color: #9ca3af; }
    </style>
</head>
<body>

@php
    $diff = (float) $record->cash_difference;
    $diffClass = $diff > 1.0 ? 'diff-warn' : ($diff < -1.0 ? 'diff-neg' : 'diff-pos');
    $statusLabels = ['active' => 'Offen', 'completed' => 'Abgeschlossen', 'cancelled' => 'Abgebrochen'];
    $statusBadges = ['active' => 'b-warning', 'completed' => 'b-success', 'cancelled' => 'b-gray'];

    // Bilder als Base64 einbetten — robust gegen Pfad-Probleme auf Produktiv
    $embedImage = function ($relativePath) {
        if (empty($relativePath)) return null;
        try {
            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            if (! $disk->exists($relativePath)) return null;
            $contents = $disk->get($relativePath);
            $mime = $disk->mimeType($relativePath) ?: 'image/jpeg';
            return 'data:' . $mime . ';base64,' . base64_encode($contents);
        } catch (\Throwable $e) {
            return null;
        }
    };
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
    <table>
        <thead>
            <tr>
                <th style="width: 18%;">Bonnr.</th>
                <th style="width: 12%;">Zeit</th>
                <th>Grund</th>
                <th class="right" style="width: 18%;">Betrag</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($record->returns->sortBy('time') as $return)
                <tr>
                    <td>{{ $return->receipt_number ?: '—' }}</td>
                    <td>{{ $return->time ?: '—' }}</td>
                    <td>{{ $return->reason ?: '—' }}</td>
                    <td class="right"><strong>{{ number_format((float) $return->amount, 2, ',', '.') }} €</strong></td>
                </tr>
            @endforeach
            <tr class="summary-row">
                <td colspan="3" class="right">Summe Ruecknahmen:</td>
                <td class="right">{{ number_format((float) $record->returns->sum('amount'), 2, ',', '.') }} €</td>
            </tr>
        </tbody>
    </table>
    <p class="small" style="margin-top: 4px;">Bon-Fotos: siehe Anlagen am Ende.</p>
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

{{-- Unterschrift (klein, gross dann in den Anlagen) --}}
@if ($record->signature)
    <h2>Unterschrift</h2>
    <table style="border: none;">
        <tr>
            <td style="border: none; width: 150px;"><img src="{{ $record->signature }}" class="signature-img"></td>
            <td style="border: none; vertical-align: middle;" class="small">{{ $record->user?->name ?? '—' }}</td>
        </tr>
    </table>
@endif

{{-- ANHANG: Alle Anlagen in voller Groesse --}}
@php
    $cashPhoto = $embedImage($record->cash_report_photo);
    $returnPhotos = $record->returns
        ->filter(fn ($r) => !empty($r->photo))
        ->map(fn ($r) => ['return' => $r, 'data' => $embedImage($r->photo)])
        ->filter(fn ($e) => !empty($e['data']));
    $hasAttachments = $cashPhoto || $returnPhotos->count() || !empty($record->signature);
@endphp

@if ($hasAttachments)
    <div style="page-break-before: always;"></div>
    <h1 style="font-size: 14pt; color: #1e40af; margin-bottom: 8px;">Anlagen</h1>
    <p class="small" style="margin-bottom: 12px;">Alle Anhaenge zur Schichtabrechnung in voller Groesse.</p>

    @if ($cashPhoto)
        <h2>Kassenbericht-Zettel</h2>
        <img src="{{ $cashPhoto }}" style="max-width: 100%; max-height: 240mm; border: 1px solid #d1d5db;">
    @endif

    @foreach ($returnPhotos as $entry)
        @php $r = $entry['return']; @endphp
        <h2 style="page-break-before: {{ $loop->first && !$cashPhoto ? 'auto' : 'always' }};">
            Bon Ruecknahme: {{ $r->receipt_number ?: '—' }}
            ({{ number_format((float) $r->amount, 2, ',', '.') }} €)
        </h2>
        <p class="small">Grund: {{ $r->reason ?: '—' }} — Zeit: {{ $r->time ?: '—' }}</p>
        <img src="{{ $entry['data'] }}" style="max-width: 100%; max-height: 220mm; border: 1px solid #d1d5db;">
    @endforeach

    @if ($record->signature)
        <h2 style="page-break-before: {{ ($cashPhoto || $returnPhotos->count()) ? 'always' : 'auto' }};">Unterschrift (gross)</h2>
        <img src="{{ $record->signature }}" style="max-width: 100%; max-height: 100mm; border: 1px solid #d1d5db; background: white; padding: 8px;">
        <div class="small">{{ $record->user?->name ?? '—' }}</div>
    @endif
@endif

<div class="footer">
    Erstellt am {{ now()->format('d.m.Y H:i') }} — Schicht-ID: {{ $record->id }}
</div>

</body>
</html>
