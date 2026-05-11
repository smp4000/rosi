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

                <div id="dymo-print-btn-wrapper">
                    <x-filament::button
                        wire:click="preparePrint"
                        size="lg"
                        color="success"
                        icon="heroicon-o-printer"
                    >
                        {{ $this->lastResult['count'] }} Gutscheine drucken
                    </x-filament::button>
                </div>
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

    {{-- DYMO Druck-Bereich --}}
    <div id="dymo-print-area" style="margin-bottom:24px;display:none;"></div>

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
        @keyframes dymospin { to { transform: rotate(360deg); } }
    </style>

    {{-- Reines JS ohne Alpine — kein Konflikt mit Livewire morphing --}}
    <script>
    (function() {
        var dymo = {
            activePort: null,
            selectedPrinter: localStorage.getItem('dymo_printer') || null,

            show: function(html) {
                var area = document.getElementById('dymo-print-area');
                if (area) { area.innerHTML = html; area.style.display = ''; }
            },

            hideBtn: function() {
                var btn = document.getElementById('dymo-print-btn-wrapper');
                if (btn) btn.style.display = 'none';
            },

            showBtn: function() {
                var btn = document.getElementById('dymo-print-btn-wrapper');
                if (btn) btn.style.display = '';
            },

            showConnecting: function() {
                this.hideBtn();
                this.show('<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:20px;">'
                    + '<div style="display:flex;align-items:center;gap:12px;">'
                    + '<div style="width:20px;height:20px;border:3px solid #eab308;border-top-color:transparent;border-radius:50%;animation:dymospin 1s linear infinite;"></div>'
                    + '<span style="font-size:14px;font-weight:500;color:#92400e;">Verbinde mit DYMO Connect Service...</span>'
                    + '</div></div>');
            },

            showPrinting: function(current, total) {
                var pct = total > 0 ? Math.round(current / total * 100) : 0;
                this.show('<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:20px;">'
                    + '<div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">'
                    + '<div style="width:20px;height:20px;border:3px solid #3b82f6;border-top-color:transparent;border-radius:50%;animation:dymospin 1s linear infinite;"></div>'
                    + '<span style="font-size:14px;font-weight:500;color:#1e40af;">Drucke ' + current + '/' + total + ' ...</span>'
                    + '</div>'
                    + '<div style="background:#dbeafe;border-radius:4px;height:8px;overflow:hidden;">'
                    + '<div style="background:#3b82f6;height:100%;border-radius:4px;width:' + pct + '%;transition:width 0.3s;"></div>'
                    + '</div></div>');
            },

            showDone: function(count, errors) {
                var html = '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:20px;">'
                    + '<div style="display:flex;align-items:center;gap:12px;">'
                    + '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#16a34a" style="width:20px;height:20px"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>'
                    + '<span style="font-size:14px;font-weight:500;color:#15803d;">' + count + ' Gutscheine erfolgreich gedruckt!</span>'
                    + '</div>';
                if (errors && errors.length > 0) {
                    html += '<div style="margin-top:12px;font-size:13px;color:#92400e;"><p style="margin:0 0 4px;font-weight:500;">Fehler bei:</p>';
                    for (var i = 0; i < errors.length; i++) {
                        html += '<p style="margin:0 0 2px;">' + errors[i] + '</p>';
                    }
                    html += '</div>';
                }
                html += '</div>';
                this.show(html);
            },

            showError: function(msg) {
                this.showBtn();
                this.show('<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:20px;">'
                    + '<div style="display:flex;align-items:center;gap:12px;">'
                    + '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#dc2626" style="width:20px;height:20px"><path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>'
                    + '<span style="font-size:14px;font-weight:500;color:#dc2626;">' + msg + '</span>'
                    + '</div>'
                    + '<p style="margin:12px 0 0;font-size:13px;color:#6b7280;">Stelle sicher dass <strong>DYMO Connect</strong> auf diesem PC gestartet ist.</p>'
                    + '</div>');
            },

            findService: async function() {
                if (this.activePort) {
                    try {
                        var r = await fetch('https://127.0.0.1:' + this.activePort + '/DYMO/DLS/Printing/StatusConnected', { mode: 'cors' });
                        if ((await r.text()) === 'true') return;
                    } catch(e) { this.activePort = null; }
                }
                var ports = [41951, 41952, 41953, 41954, 41955];
                for (var i = 0; i < ports.length; i++) {
                    try {
                        var r2 = await fetch('https://127.0.0.1:' + ports[i] + '/DYMO/DLS/Printing/StatusConnected', { mode: 'cors' });
                        if ((await r2.text()) === 'true') { this.activePort = ports[i]; return; }
                    } catch(e) {}
                }
                throw new Error('not found');
            },

            getPrinters: async function() {
                var xml = await this.fetchDymo('/DYMO/DLS/Printing/GetPrinters');
                var doc = new DOMParser().parseFromString(xml, 'text/xml');
                var nodes = doc.querySelectorAll('LabelWriterPrinter');
                var list = [];
                nodes.forEach(function(n) {
                    list.push({
                        name: n.querySelector('Name') ? n.querySelector('Name').textContent : '',
                        isConnected: n.querySelector('IsConnected') ? n.querySelector('IsConnected').textContent === 'True' : false
                    });
                });
                return list;
            },

            fetchDymo: async function(path, opts) {
                if (!this.activePort) throw new Error('no port');
                var r = await fetch('https://127.0.0.1:' + this.activePort + path, Object.assign({}, opts || {}, { mode: 'cors' }));
                return await r.text();
            },

            startPrint: async function(labelXmls) {
                console.log('DYMO: Start Druck mit', labelXmls.length, 'Labels');
                this.showConnecting();

                try {
                    await this.findService();
                    console.log('DYMO: Service gefunden auf Port', this.activePort);
                }
                catch(e) { this.showError('DYMO Connect nicht erreichbar'); return; }

                var printers;
                try { printers = await this.getPrinters(); }
                catch(e) { this.showError('Drucker-Liste nicht ladbar'); return; }

                var connected = printers.filter(function(p) { return p.isConnected; });
                if (connected.length === 0) { this.showError('Kein DYMO-Drucker verbunden'); return; }

                console.log('DYMO: Gefundene Drucker:', JSON.stringify(printers));
                console.log('DYMO: Verbundene Drucker:', JSON.stringify(connected));

                var printer = this.selectedPrinter;
                if (!printer || !connected.find(function(p) { return p.name === printer; })) {
                    printer = connected[0].name;
                }
                console.log('DYMO: Verwende Drucker:', printer);
                this.selectedPrinter = printer;
                localStorage.setItem('dymo_printer', printer);

                var printParams = '<LabelWriterPrintParams><Copies>1</Copies></LabelWriterPrintParams>';
                var errors = [];
                var self = this;

                for (var i = 0; i < labelXmls.length; i++) {
                    self.showPrinting(i + 1, labelXmls.length);
                    try {
                        var body = 'printerName=' + encodeURIComponent(printer)
                            + '&labelXml=' + encodeURIComponent(labelXmls[i].xml)
                            + '&printParamsXml=' + encodeURIComponent(printParams);
                        var result = await self.fetchDymo('/DYMO/DLS/Printing/PrintLabel2', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: body,
                        });
                        console.log('DYMO PrintLabel2 Antwort fuer', labelXmls[i].number, ':', JSON.stringify(result));
                        if (result !== 'true') errors.push(labelXmls[i].number + ': ' + result);
                    } catch(e) {
                        errors.push(labelXmls[i].number + ': ' + e.message);
                    }
                    await new Promise(function(r) { setTimeout(r, 800); });
                }

                self.showDone(labelXmls.length, errors);
            }
        };

        // Livewire Event abfangen — Labels per fetch vom Server holen
        window.addEventListener('start-dymo-print', function() {
            console.log('DYMO: Event empfangen, hole Labels vom Server...');
            fetch('/dymo/print-labels', { credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(labels) {
                    console.log('DYMO: Labels geladen:', labels.length);
                    if (labels.length > 0) {
                        dymo.startPrint(labels);
                    } else {
                        dymo.showError('Keine Label-Daten vom Server erhalten');
                    }
                })
                .catch(function(err) {
                    console.error('DYMO: Fetch fehlgeschlagen', err);
                    dymo.showError('Label-Daten konnten nicht geladen werden');
                });
        });

        // ── Print-Queue: Automatisch nach Jobs von der App pollen ──
        var pollActive = false;
        var pollInterval = null;

        function startPolling() {
            if (pollInterval) return;
            console.log('DYMO Queue: Polling gestartet (alle 5s)');
            pollInterval = setInterval(checkPendingJobs, 5000);
            checkPendingJobs(); // sofort einmal pruefen
        }

        function stopPolling() {
            if (pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
        }

        async function checkPendingJobs() {
            if (pollActive) return;
            try {
                var r = await fetch('/dymo/pending-jobs', { credentials: 'same-origin' });
                var jobs = await r.json();
                if (jobs.length > 0) {
                    console.log('DYMO Queue:', jobs.length, 'Job(s) gefunden');
                    for (var j = 0; j < jobs.length; j++) {
                        await processPrintJob(jobs[j]);
                    }
                }
            } catch(e) {
                console.error('DYMO Queue: Polling-Fehler', e);
            }
        }

        async function processPrintJob(job) {
            pollActive = true;
            console.log('DYMO Queue: Verarbeite Job', job.id, '(' + job.reference + ')', job.payload.length, 'Labels');
            dymo.show('<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:20px;">'
                + '<div style="display:flex;align-items:center;gap:12px;">'
                + '<div style="width:20px;height:20px;border:3px solid #3b82f6;border-top-color:transparent;border-radius:50%;animation:dymospin 1s linear infinite;"></div>'
                + '<div><span style="font-size:14px;font-weight:600;color:#1e40af;">Druckauftrag von App: ' + (job.created_by || '') + '</span>'
                + '<br><span style="font-size:13px;color:#6b7280;">Gruppe ' + (job.reference || '') + ' — ' + job.payload.length + ' Etiketten</span></div>'
                + '</div></div>');

            try {
                await dymo.findService();
                var printers = await dymo.getPrinters();
                var connected = printers.filter(function(p) { return p.isConnected; });
                if (connected.length === 0) {
                    dymo.showError('Kein DYMO-Drucker verbunden — Job wartet');
                    pollActive = false;
                    return;
                }
                var printer = dymo.selectedPrinter;
                if (!printer || !connected.find(function(p) { return p.name === printer; })) {
                    printer = connected[0].name;
                }
                dymo.selectedPrinter = printer;

                var printParams = '<LabelWriterPrintParams><Copies>1</Copies></LabelWriterPrintParams>';
                var errors = [];
                for (var i = 0; i < job.payload.length; i++) {
                    dymo.showPrinting(i + 1, job.payload.length);
                    try {
                        var body = 'printerName=' + encodeURIComponent(printer)
                            + '&labelXml=' + encodeURIComponent(job.payload[i].xml)
                            + '&printParamsXml=' + encodeURIComponent(printParams);
                        var result = await dymo.fetchDymo('/DYMO/DLS/Printing/PrintLabel2', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: body,
                        });
                        console.log('DYMO Queue: Label', job.payload[i].number, ':', result);
                        if (result !== 'true') errors.push(job.payload[i].number + ': ' + result);
                    } catch(e) {
                        errors.push(job.payload[i].number + ': ' + e.message);
                    }
                    await new Promise(function(r) { setTimeout(r, 800); });
                }

                // Job als erledigt markieren
                await fetch('/dymo/complete-job/' + job.id, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                });

                dymo.showDone(job.payload.length, errors);
                console.log('DYMO Queue: Job', job.id, 'abgeschlossen');
            } catch(e) {
                console.error('DYMO Queue: Druck fehlgeschlagen', e);
                dymo.showError('Druckfehler: ' + e.message + ' — Job bleibt in Warteschlange');
            }
            pollActive = false;
        }

        // Polling automatisch starten
        startPolling();
    })();
    </script>
</x-filament-panels::page>
