<x-filament-panels::page>
    @php $kpis = $this->kpis; @endphp

    {{-- KPIs --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 20px;">
        <div style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 16px;">
            <p style="margin:0;font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">Rechnungen</p>
            <p style="margin:4px 0 0;font-size:24px;font-weight:700;">{{ $kpis['invoices'] }}</p>
        </div>
        <div style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 16px;">
            <p style="margin:0;font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">Artikel</p>
            <p style="margin:4px 0 0;font-size:24px;font-weight:700;">{{ $kpis['articles'] }}</p>
        </div>
        <div style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 16px;">
            <p style="margin:0;font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">Pending</p>
            <p style="margin:4px 0 0;font-size:24px;font-weight:700;color:{{ $kpis['pending'] > 0 ? '#d97706' : 'inherit' }};">{{ $kpis['pending'] }}</p>
        </div>
        <div style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 16px;">
            <p style="margin:0;font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">Bestellzeilen</p>
            <p style="margin:4px 0 0;font-size:24px;font-weight:700;">{{ $kpis['order_lines'] }}</p>
        </div>
        <div style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 16px;">
            <p style="margin:0;font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">Preisaenderungen</p>
            <p style="margin:4px 0 0;font-size:24px;font-weight:700;">{{ $kpis['price_changes'] }}</p>
        </div>
    </div>

    {{-- Upload-Formular --}}
    <div style="margin-bottom: 24px;">
        {{ $this->form }}
        <div style="display:flex; justify-content:flex-end; margin-top: 16px;">
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
    <div style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 20px; margin-bottom: 16px;">
        <h3 style="margin:0 0 12px;font-size:16px;font-weight:600;">Letzte Importe</h3>
        @if ($this->recentImports->count())
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="text-align: left; color: #6b7280; border-bottom: 1px solid #e5e7eb;">
                        <th style="padding: 8px 6px;">Datum</th>
                        <th style="padding: 8px 6px;">Datei</th>
                        <th style="padding: 8px 6px;">Status</th>
                        <th style="padding: 8px 6px; text-align: right;">Eingefuegt</th>
                        <th style="padding: 8px 6px; text-align: right;">Aktualisiert</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->recentImports as $imp)
                        <tr style="border-bottom: 1px solid #f3f4f6;">
                            <td style="padding: 8px 6px;">{{ $imp->created_at?->format('d.m.Y H:i') }}</td>
                            <td style="padding: 8px 6px; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $imp->filename ?? '—' }}</td>
                            <td style="padding: 8px 6px;">
                                @if ($imp->status === 'success' && $imp->articles_inserted == 0 && $imp->articles_updated == 0)
                                    <span style="padding: 2px 8px; border-radius: 8px; background: #fef3c7; color: #92400e; font-size: 11px;">Leer</span>
                                @elseif ($imp->status === 'success')
                                    <span style="padding: 2px 8px; border-radius: 8px; background: #d1fae5; color: #065f46; font-size: 11px;">OK</span>
                                @elseif ($imp->status === 'skipped')
                                    <span style="padding: 2px 8px; border-radius: 8px; background: #f3f4f6; color: #4b5563; font-size: 11px;">Skipped</span>
                                @else
                                    <span style="padding: 2px 8px; border-radius: 8px; background: #fee2e2; color: #991b1b; font-size: 11px;">Fehler</span>
                                @endif
                            </td>
                            <td style="padding: 8px 6px; text-align: right;">{{ $imp->articles_inserted }}</td>
                            <td style="padding: 8px 6px; text-align: right;">{{ $imp->articles_updated }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p style="margin:0;color:#6b7280;font-size:13px;">Noch keine Importe vorhanden.</p>
        @endif
    </div>

    {{-- Letzte Preisaenderungen --}}
    <div style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 20px;">
        <h3 style="margin:0 0 12px;font-size:16px;font-weight:600;">Letzte Preisaenderungen</h3>
        @if ($this->recentPriceChanges->count())
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="text-align: left; color: #6b7280; border-bottom: 1px solid #e5e7eb;">
                        <th style="padding: 8px 6px;">Datum</th>
                        <th style="padding: 8px 6px;">Artikel</th>
                        <th style="padding: 8px 6px;">Typ</th>
                        <th style="padding: 8px 6px;">Alt</th>
                        <th style="padding: 8px 6px;">Neu</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->recentPriceChanges as $pc)
                        <tr style="border-bottom: 1px solid #f3f4f6;">
                            <td style="padding: 8px 6px;">{{ $pc->changed_at?->format('d.m.Y H:i') }}</td>
                            <td style="padding: 8px 6px;">{{ $pc->article?->bezeichnung ?? '—' }}</td>
                            <td style="padding: 8px 6px;">{{ $pc->change_type }}</td>
                            <td style="padding: 8px 6px;">{{ $pc->old_preis_netto ?? $pc->old_ek ?? '—' }}</td>
                            <td style="padding: 8px 6px; font-weight: 600;">{{ $pc->new_preis_netto ?? $pc->new_ek ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p style="margin:0;color:#6b7280;font-size:13px;">Noch keine Preisaenderungen vorhanden.</p>
        @endif
    </div>
</x-filament-panels::page>
