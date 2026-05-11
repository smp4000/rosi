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
        <div wire:change="checkGroupAvailability">
            {{ $this->form }}
        </div>

        {{-- Live-Validierung Anzeige --}}
        @if ($this->groupCheckStatus === 'ok')
            <div style="display:flex;align-items:center;gap:8px;margin-top:8px;padding:10px 14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#16a34a" style="width:18px;height:18px;flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                <span style="font-size:13px;color:#15803d;font-weight:500;">{{ $this->groupCheckMessage }}</span>
            </div>
        @elseif ($this->groupCheckStatus === 'conflict')
            <div style="display:flex;align-items:center;gap:8px;margin-top:8px;padding:10px 14px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#dc2626" style="width:18px;height:18px;flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                <span style="font-size:13px;color:#dc2626;font-weight:500;">{{ $this->groupCheckMessage }}</span>
            </div>
        @endif

        <div style="display:flex; gap: 12px; justify-content:flex-end; margin-top: 16px;" x-data>
            @if ($this->lastResult)
                <x-filament::button
                    wire:click="resetForm"
                    size="lg"
                    color="gray"
                    icon="heroicon-o-arrow-path"
                >
                    Neue Gruppe
                </x-filament::button>

                {{-- Drucken-Button: versteckt wenn fertig oder am Drucken --}}
                <template x-if="!$store.dymo || $store.dymo.status === 'idle' || $store.dymo.status === 'error'">
                    <button wire:click="preparePrint"
                            class="fi-btn fi-btn-size-lg fi-btn-color-success"
                            style="display:inline-flex;align-items:center;gap:8px;background:#16a34a;color:#fff;border:none;border-radius:8px;padding:10px 20px;font-size:14px;font-weight:600;cursor:pointer;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:20px;height:20px"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" /></svg>
                        {{ $this->lastResult['count'] }} Gutscheine drucken
                    </button>
                </template>

                {{-- Spinner waehrend Druck laeuft --}}
                <template x-if="$store.dymo && ($store.dymo.status === 'connecting' || $store.dymo.status === 'printing')">
                    <button disabled
                            style="display:inline-flex;align-items:center;gap:8px;background:#9ca3af;color:#fff;border:none;border-radius:8px;padding:10px 20px;font-size:14px;font-weight:600;cursor:not-allowed;opacity:0.7;">
                        <div style="width:16px;height:16px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite;"></div>
                        Druckt...
                    </button>
                </template>
            @else
                <x-filament::button
                    wire:click="checkAndGenerate"
                    size="lg"
                    color="primary"
                    icon="heroicon-o-gift"
                    :disabled="$this->groupCheckStatus === 'conflict'"
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

    {{-- DYMO Druck-Status (Alpine.js) --}}
    <div x-data="voucherPrinter()" x-init="init()"
         @start-dymo-print.window="startPrint($event.detail)"
         x-show="printStatus !== 'idle'"
         x-cloak
         style="margin-bottom: 24px;">

        {{-- Druckfortschritt --}}
        <template x-if="printStatus === 'connecting'">
            <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:20px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:20px;height:20px;border:3px solid #eab308;border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite;"></div>
                    <span style="font-size:14px;font-weight:500;color:#92400e;">Verbinde mit DYMO Connect Service...</span>
                </div>
            </div>
        </template>

        <template x-if="printStatus === 'printing'">
            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:20px;">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                    <div style="width:20px;height:20px;border:3px solid #3b82f6;border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite;"></div>
                    <span style="font-size:14px;font-weight:500;color:#1e40af;" x-text="'Drucke ' + printedCount + '/' + totalCount + ' ...'"></span>
                </div>
                <div style="background:#dbeafe;border-radius:4px;height:8px;overflow:hidden;">
                    <div style="background:#3b82f6;height:100%;border-radius:4px;transition:width 0.3s;"
                         :style="'width:' + (totalCount > 0 ? Math.round(printedCount/totalCount*100) : 0) + '%'"></div>
                </div>
            </div>
        </template>

        <template x-if="printStatus === 'done'">
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:20px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#16a34a" style="width:20px;height:20px"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    <span style="font-size:14px;font-weight:500;color:#15803d;" x-text="printedCount + ' Gutscheine erfolgreich gedruckt!'"></span>
                </div>
                <template x-if="printErrors.length > 0">
                    <div style="margin-top:12px;font-size:13px;color:#92400e;">
                        <p style="margin:0 0 4px;font-weight:500;">Fehler bei:</p>
                        <template x-for="err in printErrors" :key="err">
                            <p style="margin:0 0 2px;" x-text="err"></p>
                        </template>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="printStatus === 'error'">
            <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:20px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#dc2626" style="width:20px;height:20px"><path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    <span style="font-size:14px;font-weight:500;color:#dc2626;" x-text="errorMessage"></span>
                </div>
                <p style="margin:12px 0 0;font-size:13px;color:#6b7280;">
                    Stelle sicher dass <strong>DYMO Connect</strong> auf diesem PC gestartet ist und der Drucker verbunden ist.
                </p>
            </div>
        </template>

        {{-- Drucker-Auswahl wenn mehrere vorhanden --}}
        <template x-if="showPrinterSelect">
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;margin-top:12px;">
                <p style="font-size:14px;font-weight:500;margin:0 0 12px;">Drucker auswaehlen:</p>
                <template x-for="p in availablePrinters" :key="p.name">
                    <button @click="selectPrinterAndPrint(p.name)"
                            style="display:block;width:100%;text-align:left;padding:12px 16px;margin-bottom:8px;border-radius:8px;border:1px solid #d1d5db;background:#f9fafb;cursor:pointer;font-size:14px;"
                            :style="p.isConnected ? 'border-color:#bbf7d0;background:#f0fdf4' : ''">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div :style="'width:8px;height:8px;border-radius:50%;background:' + (p.isConnected ? '#22c55e' : '#9ca3af')"></div>
                            <span x-text="p.name"></span>
                            <span style="font-size:12px;color:#6b7280;" x-text="p.modelName || ''"></span>
                        </div>
                    </button>
                </template>
            </div>
        </template>
    </div>

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

    <style>
        @keyframes spin { to { transform: rotate(360deg); } }
        [x-cloak] { display: none !important; }
    </style>

    @push('scripts')
    <script>
    // Globaler Store fuer Druck-Status (Button kann darauf zugreifen)
    document.addEventListener('alpine:init', () => {
        Alpine.store('dymo', { status: 'idle' });
    });

    function voucherPrinter() {
        return {
            printStatus: 'idle',  // idle, connecting, printing, done, error
            printedCount: 0,
            totalCount: 0,
            printErrors: [],
            errorMessage: '',
            activePort: null,
            availablePrinters: [],
            showPrinterSelect: false,
            selectedPrinter: null,
            pendingLabels: [],

            init() {
                // Gespeicherten Drucker aus localStorage laden
                this.selectedPrinter = localStorage.getItem('dymo_printer') || null;
            },

            setStatus(status) {
                this.printStatus = status;
                if (Alpine.store('dymo')) Alpine.store('dymo').status = status;
            },

            async startPrint(detail) {
                this.setStatus('connecting');
                this.printedCount = 0;
                this.printErrors = [];
                this.showPrinterSelect = false;

                // Label-XMLs aus dem Livewire-Event (Livewire 3 format)
                let labelXmls = [];
                if (Array.isArray(detail) && detail.length > 0 && detail[0]?.labelXmls) {
                    labelXmls = detail[0].labelXmls;
                } else if (detail?.labelXmls) {
                    labelXmls = detail.labelXmls;
                } else if (Array.isArray(detail)) {
                    labelXmls = detail;
                }
                console.log('DYMO: Empfange', labelXmls.length, 'Labels zum Drucken');
                this.pendingLabels = labelXmls;
                this.totalCount = labelXmls.length;

                if (this.totalCount === 0) {
                    this.setStatus('error');
                    this.errorMessage = 'Keine Label-Daten vorhanden';
                    return;
                }

                // DYMO Service finden
                try {
                    await this.findDymoService();
                } catch (e) {
                    this.setStatus('error');
                    this.errorMessage = 'DYMO Connect Service nicht erreichbar. Ist DYMO Connect gestartet?';
                    return;
                }

                // Drucker ermitteln
                try {
                    await this.loadPrinters();
                } catch (e) {
                    this.setStatus('error');
                    this.errorMessage = 'Konnte Drucker-Liste nicht laden';
                    return;
                }

                const connectedPrinters = this.availablePrinters.filter(p => p.isConnected);

                if (connectedPrinters.length === 0) {
                    this.setStatus('error');
                    this.errorMessage = 'Kein DYMO-Drucker verbunden';
                    return;
                }

                // Wenn gespeicherter Drucker noch verbunden, direkt drucken
                if (this.selectedPrinter && connectedPrinters.find(p => p.name === this.selectedPrinter)) {
                    await this.printAll(labelXmls, this.selectedPrinter);
                    return;
                }

                // Nur ein Drucker? Direkt verwenden
                if (connectedPrinters.length === 1) {
                    this.selectedPrinter = connectedPrinters[0].name;
                    localStorage.setItem('dymo_printer', this.selectedPrinter);
                    await this.printAll(labelXmls, this.selectedPrinter);
                    return;
                }

                // Mehrere Drucker: Auswahl zeigen
                this.showPrinterSelect = true;
                this.setStatus('idle');
            },

            async selectPrinterAndPrint(printerName) {
                this.selectedPrinter = printerName;
                localStorage.setItem('dymo_printer', printerName);
                this.showPrinterSelect = false;

                await this.printAll(this.pendingLabels, printerName);
            },

            async printAll(labelXmls, printerName) {
                this.setStatus('printing');
                this.printedCount = 0;
                this.printErrors = [];

                // Roh senden — DYMO kann kein URL-Encoding (genau wie bei Drucker-Einstellungen)
                const printParams = '<LabelWriterPrintParams><Copies>1</Copies></LabelWriterPrintParams>';

                for (const label of labelXmls) {
                    try {
                        const body = 'printerName=' + printerName
                            + '&labelXml=' + label.xml
                            + '&printParamsXml=' + printParams;

                        const result = await this.dymoFetch('/DYMO/DLS/Printing/PrintLabel2', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: body,
                        });

                        if (result !== 'true') {
                            this.printErrors.push(label.number + ': ' + result);
                        }
                    } catch (e) {
                        this.printErrors.push(label.number + ': ' + e.message);
                    }
                    this.printedCount++;

                    // Pause zwischen Labels — DYMO braucht Zeit
                    await new Promise(r => setTimeout(r, 800));

                this.setStatus('done');
            },

            async findDymoService() {
                // Bekannten Port probieren
                if (this.activePort) {
                    try {
                        const url = 'https://127.0.0.1:' + this.activePort + '/DYMO/DLS/Printing/StatusConnected';
                        const res = await fetch(url, { mode: 'cors' });
                        const text = await res.text();
                        if (text === 'true') return;
                    } catch (e) {
                        this.activePort = null;
                    }
                }

                // Port-Scan
                const ports = [41951, 41952, 41953, 41954, 41955];
                for (const port of ports) {
                    try {
                        const url = 'https://127.0.0.1:' + port + '/DYMO/DLS/Printing/StatusConnected';
                        const res = await fetch(url, { mode: 'cors' });
                        const text = await res.text();
                        if (text === 'true') {
                            this.activePort = port;
                            console.log('DYMO Service gefunden auf Port', port);
                            return;
                        }
                    } catch (e) {
                        // Naechsten Port probieren
                    }
                }
                throw new Error('Kein DYMO Service gefunden');
            },

            async loadPrinters() {
                const xml = await this.dymoFetch('/DYMO/DLS/Printing/GetPrinters');
                const parser = new DOMParser();
                const doc = parser.parseFromString(xml, 'text/xml');
                const nodes = doc.querySelectorAll('LabelWriterPrinter');
                this.availablePrinters = [];
                nodes.forEach(node => {
                    this.availablePrinters.push({
                        name: node.querySelector('Name')?.textContent || '',
                        modelName: node.querySelector('ModelName')?.textContent || '',
                        isConnected: node.querySelector('IsConnected')?.textContent === 'True',
                    });
                });
            },

            async dymoFetch(path, options = {}) {
                if (!this.activePort) throw new Error('Kein aktiver Port');
                const url = 'https://127.0.0.1:' + this.activePort + path;
                const response = await fetch(url, { ...options, mode: 'cors' });
                return await response.text();
            },
        };
    }
    </script>
    @endpush
</x-filament-panels::page>
