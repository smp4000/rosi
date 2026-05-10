@php
    /** @var \App\Models\Kiosk\Article $record */
    $record = $this->record;
    $record->load('issues', 'priceChangeLog');
    $marge = $record->ek !== null ? (float) $record->aktueller_preis_netto - (float) $record->ek : null;
    $weekdayLabel = match ($record->weekday) {
        1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag',
        5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag', default => '—',
    };
@endphp

<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 16px;">

        {{-- Kopf-Karte --}}
        <div style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 24px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
                <div>
                    <h2 style="margin:0; font-size:22px; font-weight:700;">{{ $record->bezeichnung }}</h2>
                    <p style="margin:4px 0 0; color:#6b7280; font-size:13px;">{{ $record->supplier }} · Objekt {{ $record->objekt }}</p>
                </div>
                @if ($record->is_pending)
                    <span style="padding:4px 10px; border-radius:8px; background:#fef3c7; color:#92400e; font-size:12px; font-weight:600;">Pending</span>
                @endif
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px;">
                <div>
                    <p style="margin:0;font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">Objekt</p>
                    <p style="margin:2px 0 0;font-size:16px;font-weight:600;">{{ $record->objekt }}</p>
                </div>
                <div>
                    <p style="margin:0;font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">EAN</p>
                    <p style="margin:2px 0 0;font-size:14px;font-weight:600;font-family:monospace;">{{ $record->ean ?? '—' }}</p>
                </div>
                <div>
                    <p style="margin:0;font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">Wochentag</p>
                    <p style="margin:2px 0 0;font-size:16px;font-weight:600;">{{ $weekdayLabel }}</p>
                </div>
                <div>
                    <p style="margin:0;font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">Zuletzt gesehen</p>
                    <p style="margin:2px 0 0;font-size:16px;font-weight:600;">{{ $record->last_seen_at?->format('d.m.Y') ?? '—' }}</p>
                </div>
            </div>
        </div>

        {{-- Preise --}}
        <div style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 24px;">
            <h3 style="margin:0 0 16px;font-size:16px;font-weight:600;">Preise</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 16px;">
                <div style="background:#f3f4f6;border-radius:8px;padding:12px;">
                    <p style="margin:0;font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">VKP brutto</p>
                    <p style="margin:4px 0 0;font-size:22px;font-weight:700;">{{ number_format((float) $record->aktueller_preis_brutto, 2, ',', '.') }} €</p>
                </div>
                <div style="background:#f3f4f6;border-radius:8px;padding:12px;">
                    <p style="margin:0;font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">VKP netto</p>
                    <p style="margin:4px 0 0;font-size:18px;font-weight:600;">{{ number_format((float) $record->aktueller_preis_netto, 2, ',', '.') }} €</p>
                </div>
                <div style="background:#f3f4f6;border-radius:8px;padding:12px;">
                    <p style="margin:0;font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">EK netto</p>
                    <p style="margin:4px 0 0;font-size:18px;font-weight:600;">{{ $record->ek !== null ? number_format((float) $record->ek, 2, ',', '.') . ' €' : '—' }}</p>
                </div>
                <div style="background:{{ $marge !== null && $marge < 0 ? '#fee2e2' : '#d1fae5' }};border-radius:8px;padding:12px;">
                    <p style="margin:0;font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">Marge</p>
                    <p style="margin:4px 0 0;font-size:22px;font-weight:700;color:{{ $marge !== null && $marge < 0 ? '#991b1b' : '#065f46' }};">
                        {{ $marge !== null ? number_format($marge, 2, ',', '.') . ' €' : '—' }}
                    </p>
                </div>
                <div style="background:#f3f4f6;border-radius:8px;padding:12px;">
                    <p style="margin:0;font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">MwSt</p>
                    <p style="margin:4px 0 0;font-size:18px;font-weight:600;">{{ number_format((float) $record->mwst_satz, 1, ',', '.') }} %</p>
                </div>
            </div>
        </div>

        {{-- Ausgaben --}}
        @if ($record->issues->count())
            <div style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 24px;">
                <h3 style="margin:0 0 16px;font-size:16px;font-weight:600;">Ausgaben ({{ $record->issues->count() }})</h3>
                <div style="display:flex; flex-wrap:wrap; gap:8px;">
                    @foreach ($record->issues->sortByDesc('ausgabe') as $issue)
                        <span style="padding:6px 12px; border-radius:999px; background:#dbeafe; color:#1e40af; font-size:13px; font-weight:600;">KW {{ $issue->ausgabe }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Preisaenderungen --}}
        @if ($record->priceChangeLog->count())
            <div style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 24px;">
                <h3 style="margin:0 0 16px;font-size:16px;font-weight:600;">Preisaenderungen</h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="text-align: left; color: #6b7280; border-bottom: 1px solid #e5e7eb;">
                            <th style="padding: 8px 6px;">Datum</th>
                            <th style="padding: 8px 6px;">Typ</th>
                            <th style="padding: 8px 6px; text-align: right;">Alt</th>
                            <th style="padding: 8px 6px; text-align: right;">Neu</th>
                            <th style="padding: 8px 6px;">Quelle</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($record->priceChangeLog->sortByDesc('changed_at')->take(20) as $log)
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 8px 6px;">{{ $log->changed_at?->format('d.m.Y H:i') }}</td>
                                <td style="padding: 8px 6px;">{{ $log->change_type }}</td>
                                <td style="padding: 8px 6px; text-align: right;">{{ $log->old_preis_netto ?? $log->old_ek ?? '—' }}</td>
                                <td style="padding: 8px 6px; text-align: right; font-weight: 600;">{{ $log->new_preis_netto ?? $log->new_ek ?? '—' }}</td>
                                <td style="padding: 8px 6px; color:#6b7280;">{{ $log->source }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

    </div>
</x-filament-panels::page>
