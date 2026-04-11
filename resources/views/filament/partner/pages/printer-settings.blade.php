<x-filament-panels::page>
    <div x-data="dymoManager()" x-init="init()">

        {{-- Status-Banner --}}
        <div style="background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.1);border:1px solid #e5e7eb;padding:24px;margin-bottom:20px">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#9ca3af" style="width:20px;height:20px"><path stroke-linecap="round" stroke-linejoin="round" d="M9.348 14.652a3.75 3.75 0 0 1 0-5.304m5.304 0a3.75 3.75 0 0 1 0 5.304m-7.425 2.121a6.75 6.75 0 0 1 0-9.546m9.546 0a6.75 6.75 0 0 1 0 9.546M5.106 18.894c-3.808-3.807-3.808-9.98 0-13.788m13.788 0c3.808 3.807 3.808 9.98 0 13.788M12 12h.008v.008H12V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
                <h3 style="font-size:16px;font-weight:600;margin:0">DYMO Connect Service</h3>
            </div>

            <div style="display:flex;align-items:center;gap:10px">
                <div :style="'width:12px;height:12px;border-radius:50%;background:' + (status === 'connected' ? '#22c55e' : (status === 'checking' ? '#eab308' : '#ef4444'))"></div>
                <span style="font-size:14px" x-text="statusText"></span>
            </div>

            <template x-if="status === 'error'">
                <div style="margin-top:16px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:16px">
                    <p style="font-size:14px;color:#92400e;margin:0">
                        <strong>DYMO Connect</strong> muss auf diesem PC installiert und gestartet sein.<br>
                        Der Service laeuft lokal auf Port 41951.
                    </p>
                </div>
            </template>

            <button @click="checkConnection()"
                    style="margin-top:16px;display:inline-flex;align-items:center;gap:8px;background:#f3f4f6;border:1px solid #d1d5db;border-radius:8px;padding:8px 14px;font-size:14px;font-weight:500;color:#374151;cursor:pointer">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" /></svg>
                Verbindung pruefen
            </button>
        </div>

        {{-- Drucker-Liste --}}
        <template x-if="status === 'connected'">
            <div style="background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.1);border:1px solid #e5e7eb;padding:24px;margin-bottom:20px">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
                    <div style="display:flex;align-items:center;gap:12px">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#9ca3af" style="width:20px;height:20px"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" /></svg>
                        <h3 style="font-size:16px;font-weight:600;margin:0">Verfuegbare Drucker</h3>
                    </div>
                    <button @click="loadPrinters()"
                            style="display:inline-flex;align-items:center;gap:6px;font-size:14px;color:#2563eb;background:none;border:none;cursor:pointer">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" /></svg>
                        Aktualisieren
                    </button>
                </div>

                <template x-if="printers.length === 0">
                    <p style="font-size:14px;color:#6b7280">Keine DYMO-Drucker gefunden.</p>
                </template>

                <div>
                    <template x-for="printer in printers" :key="printer.name">
                        <div style="display:flex;align-items:center;justify-content:space-between;border-radius:8px;padding:16px;margin-bottom:10px"
                             :style="printer.isConnected ? 'background:#f0fdf4;border:1px solid #bbf7d0' : 'background:#f9fafb;border:1px solid #e5e7eb'">
                            <div style="display:flex;align-items:center;gap:12px">
                                <div :style="'width:10px;height:10px;border-radius:50%;background:' + (printer.isConnected ? '#22c55e' : '#9ca3af')"></div>
                                <div>
                                    <p style="font-size:14px;font-weight:500;margin:0" x-text="printer.name"></p>
                                    <p style="font-size:12px;color:#6b7280;margin:2px 0 0" x-text="printer.modelName || 'DYMO LabelWriter'"></p>
                                </div>
                            </div>
                            <button @click="testPrint(printer.name)"
                                    :disabled="!printer.isConnected || printing"
                                    :style="printer.isConnected && !printing
                                        ? 'display:inline-flex;align-items:center;gap:8px;background:#16a34a;color:#fff;border:none;border-radius:8px;padding:8px 18px;font-size:14px;font-weight:500;cursor:pointer'
                                        : 'display:inline-flex;align-items:center;gap:8px;background:#9ca3af;color:#fff;border:none;border-radius:8px;padding:8px 18px;font-size:14px;font-weight:500;cursor:not-allowed'">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" /></svg>
                                <span x-text="printing && printingPrinter === printer.name ? 'Druckt...' : 'Testdruck'"></span>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        {{-- Testdruck-Ergebnis --}}
        <template x-if="printResult">
            <div :style="printResult.success
                ? 'background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:20px;margin-bottom:20px'
                : 'background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:20px;margin-bottom:20px'">
                <div style="display:flex;align-items:flex-start;gap:12px">
                    <template x-if="printResult.success">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#16a34a" style="width:20px;height:20px;flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    </template>
                    <template x-if="!printResult.success">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#dc2626" style="width:20px;height:20px;flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    </template>
                    <p style="font-size:14px;font-weight:500;margin:0" x-text="printResult.message"></p>
                </div>
            </div>
        </template>

        {{-- Hinweise --}}
        <div style="background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.1);border:1px solid #e5e7eb;padding:24px">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#9ca3af" style="width:20px;height:20px"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
                <h3 style="font-size:16px;font-weight:600;margin:0">Hinweise</h3>
            </div>
            <ul style="font-size:14px;color:#4b5563;padding-left:20px;margin:0;line-height:2">
                <li><strong>DYMO Connect Software</strong> muss auf diesem PC installiert und gestartet sein</li>
                <li>Der Drucker muss per USB oder WLAN mit diesem PC verbunden sein</li>
                <li>Der Browser kommuniziert direkt mit dem lokalen DYMO Service (Port 41951)</li>
                <li>Es werden keine Daten an den Server gesendet — alles laeuft lokal</li>
            </ul>
        </div>
    </div>

    @push('scripts')
    <script>
    function dymoManager() {
        return {
            status: 'checking',
            statusText: 'Verbindung wird geprueft...',
            printers: [],
            printing: false,
            printingPrinter: null,
            printResult: null,
            activePort: null,

            async init() {
                await this.checkConnection();
            },

            async checkConnection() {
                this.status = 'checking';
                this.statusText = 'Verbindung wird geprueft...';
                this.printResult = null;

                try {
                    const response = await this.dymoFetch('/DYMO/DLS/Printing/StatusConnected');
                    if (response === 'true') {
                        this.status = 'connected';
                        this.statusText = 'DYMO Connect Service ist verbunden';
                        await this.loadPrinters();
                    } else {
                        this.status = 'error';
                        this.statusText = 'DYMO Connect Service antwortet nicht';
                    }
                } catch (e) {
                    this.status = 'error';
                    this.statusText = 'DYMO Connect Service nicht erreichbar (Port 41951)';
                }
            },

            async loadPrinters() {
                try {
                    const xml = await this.dymoFetch('/DYMO/DLS/Printing/GetPrinters');
                    this.printers = this.parsePrintersXml(xml);
                } catch (e) {
                    this.printers = [];
                }
            },

            parsePrintersXml(xml) {
                const printers = [];
                const parser = new DOMParser();
                const doc = parser.parseFromString(xml, 'text/xml');
                const nodes = doc.querySelectorAll('LabelWriterPrinter');

                nodes.forEach(node => {
                    const name = node.querySelector('Name')?.textContent || '';
                    const modelName = node.querySelector('ModelName')?.textContent || '';
                    const isConnected = node.querySelector('IsConnected')?.textContent === 'True';
                    printers.push({ name, modelName, isConnected });
                });

                return printers;
            },

            async testPrint(printerName) {
                this.printing = true;
                this.printingPrinter = printerName;
                this.printResult = null;

                try {
                    const labelXml = this.buildTestLabelXml();
                    const printParams = '<LabelWriterPrintParams><Copies>1</Copies></LabelWriterPrintParams>';

                    // Roh senden wie curl — DYMO kann kein URL-Encoding/Multipart
                    const body = 'printerName=' + printerName
                        + '&labelXml=' + labelXml
                        + '&printParamsXml=' + printParams;

                    const result = await this.dymoFetch('/DYMO/DLS/Printing/PrintLabel2', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body,
                    });

                    if (result === 'true') {
                        this.printResult = {
                            success: true,
                            message: 'Testdruck erfolgreich auf "' + printerName + '" gesendet!',
                        };
                    } else {
                        this.printResult = {
                            success: false,
                            message: 'Drucker hat den Auftrag abgelehnt: ' + result,
                        };
                    }
                } catch (e) {
                    this.printResult = {
                        success: false,
                        message: 'Druckfehler: ' + e.message,
                    };
                } finally {
                    this.printing = false;
                    this.printingPrinter = null;
                }
            },

            buildTestLabelXml() {
                const now = new Date();
                const date = now.toLocaleDateString('de-DE');
                const time = now.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
                const txt = 'ROSI Testdruck  ' + date + ' ' + time;

                // Minimal-XML in einer Zeile — DYMO Connect reagiert empfindlich auf Whitespace
                return '<' + '?xml version="1.0" encoding="utf-8"?' + '>'
                    + '<DesktopLabel Version="1"><DYMOLabel Version="3">'
                    + '<Description>ROSI Testdruck</Description>'
                    + '<Orientation>Landscape</Orientation>'
                    + '<LabelName>Shipping</LabelName>'
                    + '<InitialLength>0</InitialLength>'
                    + '<BorderStyle>SolidLine</BorderStyle>'
                    + '<DYMORect><DYMOPoint><X>0</X><Y>0</Y></DYMOPoint><Size><Width>3.98</Width><Height>2.13</Height></Size></DYMORect>'
                    + '<BorderColor><SolidColorBrush><Color A="0" R="0" G="0" B="0" /></SolidColorBrush></BorderColor>'
                    + '<BorderThickness>1</BorderThickness>'
                    + '<Show_Border>False</Show_Border>'
                    + '<DynamicLayoutManager><RotationBehavior>ClearObjects</RotationBehavior><LabelObjects>'
                    + '<TextObject>'
                    + '<Name>Text</Name>'
                    + '<Brushes>'
                    + '<BackgroundBrush><SolidColorBrush><Color A="0" R="1" G="1" B="1" /></SolidColorBrush></BackgroundBrush>'
                    + '<BorderBrush><SolidColorBrush><Color A="0" R="0" G="0" B="0" /></SolidColorBrush></BorderBrush>'
                    + '<StrokeBrush><SolidColorBrush><Color A="0" R="0" G="0" B="0" /></SolidColorBrush></StrokeBrush>'
                    + '<FillBrush><SolidColorBrush><Color A="0" R="0" G="0" B="0" /></SolidColorBrush></FillBrush>'
                    + '</Brushes>'
                    + '<Rotation>Rotation0</Rotation>'
                    + '<OutlineThickness>1</OutlineThickness>'
                    + '<IsOutlined>False</IsOutlined>'
                    + '<BorderStyle>SolidLine</BorderStyle>'
                    + '<Margin><DYMOThickness Left="0" Top="0" Right="0" Bottom="0" /></Margin>'
                    + '<HorizontalAlignment>Left</HorizontalAlignment>'
                    + '<VerticalAlignment>Middle</VerticalAlignment>'
                    + '<FitMode>ShrinkToFit</FitMode>'
                    + '<IsVertical>False</IsVertical>'
                    + '<FormattedText>'
                    + '<FitMode>ShrinkToFit</FitMode>'
                    + '<HorizontalAlignment>Left</HorizontalAlignment>'
                    + '<VerticalAlignment>Middle</VerticalAlignment>'
                    + '<IsVertical>False</IsVertical>'
                    + '<LineTextSpan><TextSpan>'
                    + '<Text>' + txt + '</Text>'
                    + '<FontInfo><FontName>Arial</FontName><FontSize>12</FontSize><IsBold>True</IsBold><IsItalic>False</IsItalic><IsUnderline>False</IsUnderline>'
                    + '<FontBrush><SolidColorBrush><Color A="1" R="0" G="0" B="0" /></SolidColorBrush></FontBrush></FontInfo>'
                    + '</TextSpan></LineTextSpan>'
                    + '</FormattedText>'
                    + '<ObjectLayout><DYMOPoint><X>0.35</X><Y>0.20</Y></DYMOPoint><Size><Width>3.0</Width><Height>0.7</Height></Size></ObjectLayout>'
                    + '</TextObject>'
                    + '</LabelObjects></DynamicLayoutManager>'
                    + '</DYMOLabel></DesktopLabel>';
            },

            async dymoFetch(path, options = {}) {
                // Wenn ein Port bereits bekannt ist, direkt verwenden
                if (this.activePort) {
                    try {
                        const url = 'https://127.0.0.1:' + this.activePort + path;
                        const response = await fetch(url, { ...options, mode: 'cors' });
                        return await response.text();
                    } catch (e) {
                        // Port funktioniert nicht mehr, neu scannen
                        this.activePort = null;
                    }
                }

                // Port-Scan
                const ports = [41951, 41952, 41953, 41954, 41955];
                for (const port of ports) {
                    try {
                        const url = 'https://127.0.0.1:' + port + path;
                        const response = await fetch(url, { ...options, mode: 'cors' });
                        this.activePort = port;
                        console.log('DYMO Service gefunden auf Port', port);
                        return await response.text();
                    } catch (e) {
                        if (port === ports[ports.length - 1]) throw e;
                    }
                }
            },
        };
    }
    </script>
    @endpush
</x-filament-panels::page>
