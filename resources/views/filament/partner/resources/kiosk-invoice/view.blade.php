@php
    /** @var \App\Models\Kiosk\Invoice $record */
    $record = $this->record;
    $record->load('orderLines.article');
    $lieferungen = $record->orderLines->where('typ', 'lieferung');
    $remissionen = $record->orderLines->where('typ', 'remission');
@endphp

<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 16px;">

        {{-- Kopf --}}
        <div style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 24px;">
            <h2 style="margin:0 0 16px; font-size:22px; font-weight:700;">Rechnung {{ $record->rechnungsnummer }}</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px;">
                <div>
                    <p style="margin:0;font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">Datum</p>
                    <p style="margin:2px 0 0;font-size:16px;font-weight:600;">{{ $record->rechnungsdatum?->format('d.m.Y') ?? '—' }}</p>
                </div>
                <div>
                    <p style="margin:0;font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">Lieferzeitraum</p>
                    <p style="margin:2px 0 0;font-size:14px;font-weight:600;">
                        {{ $record->lieferdatum_von?->format('d.m.Y') ?? '—' }}
                        bis {{ $record->lieferdatum_bis?->format('d.m.Y') ?? '—' }}
                    </p>
                </div>
                <div>
                    <p style="margin:0;font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">Kunden-Nr</p>
                    <p style="margin:2px 0 0;font-size:16px;font-weight:600;">{{ $record->kundennummer ?? '—' }}</p>
                </div>
                <div>
                    <p style="margin:0;font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">Gesamtbetrag</p>
                    <p style="margin:2px 0 0;font-size:18px;font-weight:700;">{{ $record->gesamtbetrag ? number_format((float) $record->gesamtbetrag, 2, ',', '.') . ' €' : '—' }}</p>
                </div>
            </div>
        </div>

        {{-- Lieferungen --}}
        <div style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 24px;">
            <h3 style="margin:0 0 16px;font-size:16px;font-weight:600;">Lieferungen ({{ $lieferungen->count() }})</h3>
            @if ($lieferungen->count())
                <div style="overflow-x:auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead>
                            <tr style="text-align: left; color: #6b7280; border-bottom: 1px solid #e5e7eb;">
                                <th style="padding: 8px 6px;">Datum</th>
                                <th style="padding: 8px 6px;">LS-Nr</th>
                                <th style="padding: 8px 6px;">Artikel</th>
                                <th style="padding: 8px 6px;">KW</th>
                                <th style="padding: 8px 6px; text-align: right;">Menge</th>
                                <th style="padding: 8px 6px; text-align: right;">EP netto</th>
                                <th style="padding: 8px 6px; text-align: right;">Gesamt</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lieferungen->sortBy('lieferschein_datum') as $line)
                                <tr style="border-bottom: 1px solid #f3f4f6;">
                                    <td style="padding: 8px 6px;">{{ $line->lieferschein_datum?->format('d.m.Y') }}</td>
                                    <td style="padding: 8px 6px; font-family: monospace;">{{ $line->lieferschein_nr }}</td>
                                    <td style="padding: 8px 6px;">{{ $line->article?->bezeichnung ?? '—' }}</td>
                                    <td style="padding: 8px 6px;">{{ $line->ausgabe }}</td>
                                    <td style="padding: 8px 6px; text-align: right;">{{ $line->menge }}</td>
                                    <td style="padding: 8px 6px; text-align: right;">{{ number_format((float) $line->einzelpreis_netto, 4, ',', '.') }} €</td>
                                    <td style="padding: 8px 6px; text-align: right; font-weight: 600;">{{ number_format((float) $line->gesamt_netto, 2, ',', '.') }} €</td>
                                </tr>
                            @endforeach
                            <tr style="background:#f9fafb; font-weight:700;">
                                <td colspan="6" style="padding: 8px 6px; text-align: right;">Summe Lieferungen:</td>
                                <td style="padding: 8px 6px; text-align: right;">{{ number_format((float) $lieferungen->sum('gesamt_netto'), 2, ',', '.') }} €</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @else
                <p style="margin:0;color:#6b7280;font-size:13px;">Keine Lieferungen.</p>
            @endif
        </div>

        {{-- Remissionen --}}
        @if ($remissionen->count())
            <div style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 24px;">
                <h3 style="margin:0 0 16px;font-size:16px;font-weight:600;">Remissionen ({{ $remissionen->count() }})</h3>
                <div style="overflow-x:auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead>
                            <tr style="text-align: left; color: #6b7280; border-bottom: 1px solid #e5e7eb;">
                                <th style="padding: 8px 6px;">Datum</th>
                                <th style="padding: 8px 6px;">Paket</th>
                                <th style="padding: 8px 6px;">Artikel</th>
                                <th style="padding: 8px 6px;">KW</th>
                                <th style="padding: 8px 6px; text-align: right;">Menge</th>
                                <th style="padding: 8px 6px; text-align: right;">Gesamt</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($remissionen->sortBy('lieferschein_datum') as $line)
                                <tr style="border-bottom: 1px solid #f3f4f6;">
                                    <td style="padding: 8px 6px;">{{ $line->lieferschein_datum?->format('d.m.Y') }}</td>
                                    <td style="padding: 8px 6px; font-family: monospace;">{{ $line->paket }}</td>
                                    <td style="padding: 8px 6px;">{{ $line->article?->bezeichnung ?? '—' }}</td>
                                    <td style="padding: 8px 6px;">{{ $line->ausgabe }}</td>
                                    <td style="padding: 8px 6px; text-align: right; color:#dc2626;">{{ $line->menge }}</td>
                                    <td style="padding: 8px 6px; text-align: right; color:#dc2626; font-weight: 600;">{{ number_format((float) $line->gesamt_netto, 2, ',', '.') }} €</td>
                                </tr>
                            @endforeach
                            <tr style="background:#f9fafb; font-weight:700;">
                                <td colspan="5" style="padding: 8px 6px; text-align: right;">Summe Remissionen:</td>
                                <td style="padding: 8px 6px; text-align: right; color:#dc2626;">{{ number_format((float) $remissionen->sum('gesamt_netto'), 2, ',', '.') }} €</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
