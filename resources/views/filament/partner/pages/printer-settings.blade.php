<x-filament-panels::page>
    <div x-data="dymoManager()" x-init="init()" class="space-y-6">

        {{-- Status-Banner --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center gap-3 mb-4">
                <x-heroicon-o-signal class="w-5 h-5 text-gray-400" />
                <h3 class="text-base font-semibold">DYMO Connect Service</h3>
            </div>

            <div class="flex items-center gap-3">
                <div class="flex h-3 w-3 rounded-full"
                     :class="status === 'connected' ? 'bg-green-500' : (status === 'checking' ? 'bg-yellow-500 animate-pulse' : 'bg-red-500')">
                </div>
                <span class="text-sm" x-text="statusText"></span>
            </div>

            <template x-if="status === 'error'">
                <div class="mt-4 rounded-lg bg-amber-50 border border-amber-200 p-4 dark:bg-amber-900/20 dark:border-amber-700">
                    <p class="text-sm text-amber-800 dark:text-amber-200">
                        <strong>DYMO Connect</strong> muss auf diesem PC installiert und gestartet sein.<br>
                        Der Service laeuft lokal auf Port 41951.
                    </p>
                </div>
            </template>

            <button @click="checkConnection()"
                    class="mt-4 inline-flex items-center gap-2 rounded-lg bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                <x-heroicon-o-arrow-path class="w-4 h-4" />
                Verbindung pruefen
            </button>
        </div>

        {{-- Drucker-Liste --}}
        <template x-if="status === 'connected'">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <x-heroicon-o-printer class="w-5 h-5 text-gray-400" />
                        <h3 class="text-base font-semibold">Verfuegbare Drucker</h3>
                    </div>
                    <button @click="loadPrinters()"
                            class="inline-flex items-center gap-1 text-sm text-primary-600 hover:text-primary-700">
                        <x-heroicon-o-arrow-path class="w-4 h-4" />
                        Aktualisieren
                    </button>
                </div>

                <template x-if="printers.length === 0">
                    <p class="text-sm text-gray-500">Keine DYMO-Drucker gefunden.</p>
                </template>

                <div class="space-y-3">
                    <template x-for="printer in printers" :key="printer.name">
                        <div class="flex items-center justify-between rounded-lg border p-4"
                             :class="printer.isConnected ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20' : 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800'">
                            <div class="flex items-center gap-3">
                                <div class="flex h-2.5 w-2.5 rounded-full"
                                     :class="printer.isConnected ? 'bg-green-500' : 'bg-gray-400'">
                                </div>
                                <div>
                                    <p class="text-sm font-medium" x-text="printer.name"></p>
                                    <p class="text-xs text-gray-500" x-text="printer.modelName || 'DYMO LabelWriter'"></p>
                                </div>
                            </div>
                            <button @click="testPrint(printer.name)"
                                    :disabled="!printer.isConnected || printing"
                                    class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white transition"
                                    :class="printer.isConnected && !printing ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-400 cursor-not-allowed'">
                                <template x-if="printing && printingPrinter === printer.name">
                                    <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                </template>
                                <template x-if="!(printing && printingPrinter === printer.name)">
                                    <x-heroicon-o-printer class="w-4 h-4" />
                                </template>
                                <span x-text="printing && printingPrinter === printer.name ? 'Druckt...' : 'Testdruck'"></span>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        {{-- Testdruck-Ergebnis --}}
        <template x-if="printResult">
            <div class="fi-section rounded-xl shadow-sm ring-1 p-6"
                 :class="printResult.success ? 'bg-green-50 ring-green-200 dark:bg-green-900/20 dark:ring-green-800' : 'bg-red-50 ring-red-200 dark:bg-red-900/20 dark:ring-red-800'">
                <div class="flex items-center gap-3">
                    <template x-if="printResult.success">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-green-600" />
                    </template>
                    <template x-if="!printResult.success">
                        <x-heroicon-o-x-circle class="w-5 h-5 text-red-600" />
                    </template>
                    <p class="text-sm font-medium" x-text="printResult.message"></p>
                </div>
            </div>
        </template>

        {{-- Hinweise --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center gap-3 mb-4">
                <x-heroicon-o-information-circle class="w-5 h-5 text-gray-400" />
                <h3 class="text-base font-semibold">Hinweise</h3>
            </div>
            <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-2 list-disc list-inside">
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

                    const body = new URLSearchParams({
                        printerName: printerName,
                        labelXml: labelXml,
                        printParamsXml: printParams,
                    });

                    const result = await this.dymoFetch('/DYMO/DLS/Printing/PrintLabel', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body.toString(),
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

                return `<?xml version="1.0" encoding="utf-8"?>
<DesktopLabel Version="1">
  <DYMOLabel Version="3">
    <Description>ROSI Testdruck</Description>
    <Orientation>Landscape</Orientation>
    <LabelName>Address</LabelName>
    <InitialLength>0</InitialLength>
    <BorderStyle>SolidLine</BorderStyle>
    <DYMORect>
      <DYMOPoint><X>0</X><Y>0</Y></DYMOPoint>
      <Size><Width>3.5</Width><Height>1.1</Height></Size>
    </DYMORect>
    <BorderColor><SolidColorBrush><Color A="0" R="0" G="0" B="0" /></SolidColorBrush></BorderColor>
    <BorderThickness>1</BorderThickness>
    <Show_Border>False</Show_Border>
    <DynamicLayoutManager>
      <RotationBehavior>ClearObjects</RotationBehavior>
      <LabelObjects>
        <TextObject>
          <Name>Title</Name>
          <Brushes>
            <BackgroundBrush><SolidColorBrush><Color A="0" R="1" G="1" B="1" /></SolidColorBrush></BackgroundBrush>
            <BorderBrush><SolidColorBrush><Color A="0" R="0" G="0" B="0" /></SolidColorBrush></BorderBrush>
            <StrokeBrush><SolidColorBrush><Color A="0" R="0" G="0" B="0" /></SolidColorBrush></StrokeBrush>
            <FillBrush><SolidColorBrush><Color A="0" R="0" G="0" B="0" /></SolidColorBrush></FillBrush>
          </Brushes>
          <Rotation>Rotation0</Rotation>
          <OutlineThickness>1</OutlineThickness>
          <IsOutlined>False</IsOutlined>
          <BorderStyle>SolidLine</BorderStyle>
          <Margin><DYMOThickness Left="0" Top="0" Right="0" Bottom="0" /></Margin>
          <HorizontalAlignment>Center</HorizontalAlignment>
          <VerticalAlignment>Middle</VerticalAlignment>
          <FitMode>ShrinkToFit</FitMode>
          <IsVertical>False</IsVertical>
          <FormattedText>
            <FitMode>ShrinkToFit</FitMode>
            <HorizontalAlignment>Center</HorizontalAlignment>
            <VerticalAlignment>Middle</VerticalAlignment>
            <LineTextSpan>
              <TextSpan>
                <Text>ROSI Testdruck</Text>
                <FontInfo><FontName>Arial</FontName><FontSize>14</FontSize><IsBold>True</IsBold><IsItalic>False</IsItalic><IsUnderline>False</IsUnderline><FontBrush><SolidColorBrush><Color A="1" R="0" G="0" B="0" /></SolidColorBrush></FontBrush></FontInfo>
              </TextSpan>
            </LineTextSpan>
          </FormattedText>
          <ObjectLayout>
            <DYMOPoint><X>0.35</X><Y>0.05</Y></DYMOPoint>
            <Size><Width>3.0</Width><Height>0.4</Height></Size>
          </ObjectLayout>
        </TextObject>
        <TextObject>
          <Name>DateTime</Name>
          <Brushes>
            <BackgroundBrush><SolidColorBrush><Color A="0" R="1" G="1" B="1" /></SolidColorBrush></BackgroundBrush>
            <BorderBrush><SolidColorBrush><Color A="0" R="0" G="0" B="0" /></SolidColorBrush></BorderBrush>
            <StrokeBrush><SolidColorBrush><Color A="0" R="0" G="0" B="0" /></SolidColorBrush></StrokeBrush>
            <FillBrush><SolidColorBrush><Color A="0" R="0" G="0" B="0" /></SolidColorBrush></FillBrush>
          </Brushes>
          <Rotation>Rotation0</Rotation>
          <OutlineThickness>1</OutlineThickness>
          <IsOutlined>False</IsOutlined>
          <BorderStyle>SolidLine</BorderStyle>
          <Margin><DYMOThickness Left="0" Top="0" Right="0" Bottom="0" /></Margin>
          <HorizontalAlignment>Center</HorizontalAlignment>
          <VerticalAlignment>Middle</VerticalAlignment>
          <FitMode>ShrinkToFit</FitMode>
          <IsVertical>False</IsVertical>
          <FormattedText>
            <FitMode>ShrinkToFit</FitMode>
            <HorizontalAlignment>Center</HorizontalAlignment>
            <VerticalAlignment>Middle</VerticalAlignment>
            <LineTextSpan>
              <TextSpan>
                <Text>${date} ${time}</Text>
                <FontInfo><FontName>Arial</FontName><FontSize>11</FontSize><IsBold>False</IsBold><IsItalic>False</IsItalic><IsUnderline>False</IsUnderline><FontBrush><SolidColorBrush><Color A="1" R="0" G="0" B="0" /></SolidColorBrush></FontBrush></FontInfo>
              </TextSpan>
            </LineTextSpan>
          </FormattedText>
          <ObjectLayout>
            <DYMOPoint><X>0.35</X><Y>0.50</Y></DYMOPoint>
            <Size><Width>3.0</Width><Height>0.35</Height></Size>
          </ObjectLayout>
        </TextObject>
      </LabelObjects>
    </DynamicLayoutManager>
  </DYMOLabel>
</DesktopLabel>`;
            },

            async dymoFetch(path, options = {}) {
                // DYMO Connect Service nutzt self-signed SSL auf localhost
                // Ports 41951-41960 werden durchprobiert
                const ports = [41951, 41952, 41953, 41954, 41955];

                for (const port of ports) {
                    try {
                        const url = `https://127.0.0.1:${port}${path}`;
                        const response = await fetch(url, {
                            ...options,
                            mode: 'cors',
                        });
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
