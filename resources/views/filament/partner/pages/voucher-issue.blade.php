<x-filament-panels::page>
    @php $kpis = $this->kpis; @endphp

    {{-- KPIs --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 20px;">
        <div style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 16px;">
            <p style="margin:0;font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">Gesamt</p>
            <p style="margin:4px 0 0;font-size:24px;font-weight:700;">{{ $kpis['total'] }}</p>
        </div>
        <div style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 16px;">
            <p style="margin:0;font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">Aktiv</p>
            <p style="margin:4px 0 0;font-size:24px;font-weight:700;color:#059669;">{{ $kpis['active'] }}</p>
        </div>
        <div style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 16px;">
            <p style="margin:0;font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">Eingeloest</p>
            <p style="margin:4px 0 0;font-size:24px;font-weight:700;">{{ $kpis['redeemed'] }}</p>
        </div>
        <div style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 16px;">
            <p style="margin:0;font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">Gesamtwert</p>
            <p style="margin:4px 0 0;font-size:24px;font-weight:700;">{{ $kpis['total_value'] }} &euro;</p>
        </div>
        <div style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 16px;">
            <p style="margin:0;font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">Offener Wert</p>
            <p style="margin:4px 0 0;font-size:24px;font-weight:700;color:#d97706;">{{ $kpis['open_value'] }} &euro;</p>
        </div>
    </div>

    {{-- Eingabe-Formular --}}
    <div style="margin-bottom: 24px;">
        {{ $this->form }}

        <div style="display:flex; gap: 12px; justify-content:flex-end; margin-top: 16px;">
            @if ($this->lastResult)
                <x-filament::button
                    wire:click="resetForm"
                    size="lg"
                    color="gray"
                    icon="heroicon-o-arrow-path"
                >
                    Neue Gruppe
                </x-filament::button>

                <x-filament::button
                    wire:click="printVouchers"
                    size="lg"
                    color="success"
                    icon="heroicon-o-printer"
                    :disabled="$this->isPrinting"
                >
                    @if ($this->isPrinting)
                        Drucke {{ $this->printedCount }}/{{ $this->totalToPrint }}...
                    @else
                        {{ $this->lastResult['count'] }} Gutscheine drucken
                    @endif
                </x-filament::button>
            @else
                <x-filament::button
                    wire:click="checkAndGenerate"
                    size="lg"
                    color="primary"
                    icon="heroicon-o-gift"
                >
                    Gutscheine generieren
                </x-filament::button>
            @endif
        </div>
    </div>

    {{-- Ergebnis der Generierung --}}
    @if ($this->lastResult)
        <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
            <h3 style="margin:0 0 12px;font-size:16px;font-weight:600;color:#065f46;">Gutscheine generiert</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 8px; font-size: 14px;">
                <div><span style="color:#6b7280;">Gruppe:</span> <strong>{{ $this->lastResult['group'] }}</strong></div>
                <div><span style="color:#6b7280;">Anzahl:</span> <strong>{{ $this->lastResult['count'] }}</strong></div>
                <div><span style="color:#6b7280;">Betrag:</span> <strong>{{ $this->lastResult['amount'] }} &euro;</strong></div>
                <div><span style="color:#6b7280;">Nummern:</span> <strong>{{ $this->lastResult['first_number'] }} &ndash; {{ $this->lastResult['last_number'] }}</strong></div>
                <div><span style="color:#6b7280;">Gesamtwert:</span> <strong>{{ $this->lastResult['total'] }} &euro;</strong></div>
                <div><span style="color:#6b7280;">Gueltig bis:</span> <strong>{{ $this->lastResult['valid_until'] }}</strong></div>
            </div>
        </div>
    @endif

    {{-- Letzte Gutschein-Gruppen --}}
    <div style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 20px;">
        <h3 style="margin:0 0 12px;font-size:16px;font-weight:600;">Letzte Gutschein-Gruppen</h3>
        @if ($this->recentGroups->count())
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="text-align: left; color: #6b7280; border-bottom: 1px solid #e5e7eb;">
                        <th style="padding: 8px 6px;">Gruppe</th>
                        <th style="padding: 8px 6px;">Nummern</th>
                        <th style="padding: 8px 6px; text-align: right;">Anzahl</th>
                        <th style="padding: 8px 6px; text-align: right;">Betrag</th>
                        <th style="padding: 8px 6px;">Ausgabedatum</th>
                        <th style="padding: 8px 6px;">Gueltig bis</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->recentGroups as $grp)
                        <tr style="border-bottom: 1px solid #f3f4f6;">
                            <td style="padding: 8px 6px; font-weight: 600;">{{ $grp->voucher_group }}</td>
                            <td style="padding: 8px 6px;">{{ $grp->first_number }} &ndash; {{ $grp->last_number }}</td>
                            <td style="padding: 8px 6px; text-align: right;">{{ $grp->count }}</td>
                            <td style="padding: 8px 6px; text-align: right;">{{ number_format($grp->amount, 2, ',', '.') }} &euro;</td>
                            <td style="padding: 8px 6px;">{{ \Carbon\Carbon::parse($grp->issued_at)->format('d.m.Y') }}</td>
                            <td style="padding: 8px 6px;">{{ \Carbon\Carbon::parse($grp->valid_until)->format('d.m.Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p style="margin:0;color:#6b7280;font-size:13px;">Noch keine Gutscheine vorhanden.</p>
        @endif
    </div>
</x-filament-panels::page>
