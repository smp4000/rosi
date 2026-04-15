<x-filament-panels::page>
    @foreach($categories as $category => $templates)
        <div style="background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.1);border:1px solid #e5e7eb;padding:24px;margin-bottom:20px">
            <h3 style="font-size:18px;font-weight:600;margin:0 0 16px">
                {{ $categoryLabels[$category] ?? ucfirst($category) }}
            </h3>

            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
                @foreach($templates as $template)
                    @php
                        $isSelected = ($selections[$category] ?? null) === $template->slug;
                        $ph = is_string($template->placeholders) ? json_decode($template->placeholders, true) : $template->placeholders;
                        // Beispieldaten fuer Demo-Druck generieren
                        $demoData = [];
                        if (is_array($ph)) {
                            foreach ($ph as $p) {
                                $demoData[$p['key']] = $p['example'] ?? $p['key'];
                            }
                        }
                        $demoData['datum'] = now()->format('d.m.Y H:i');
                        $demoXml = str_replace(
                            array_map(fn($k) => '{{'.$k.'}}', array_keys($demoData)),
                            array_values($demoData),
                            $template->xml_template
                        );
                    @endphp
                    <div style="border-radius:10px;padding:20px;border:2px solid {{ $isSelected ? '#22c55e' : '#e5e7eb' }};background:{{ $isSelected ? '#f0fdf4' : '#fafafa' }};position:relative">
                        @if($isSelected)
                            <div style="position:absolute;top:10px;right:10px;background:#22c55e;color:#fff;border-radius:20px;padding:2px 10px;font-size:12px;font-weight:600">
                                Aktiv
                            </div>
                        @endif

                        <h4 style="font-size:16px;font-weight:600;margin:0 0 8px">{{ $template->name }}</h4>

                        <p style="font-size:12px;color:#6b7280;margin:0 0 12px">
                            Kennung: <code style="background:#f3f4f6;padding:2px 6px;border-radius:4px">{{ $template->slug }}</code>
                            &middot; {{ $template->orientation }}
                            &middot; {{ $template->width }} x {{ $template->height }} cm
                        </p>

                        @if(is_array($ph) && count($ph) > 0)
                            <div style="margin-bottom:16px">
                                <p style="font-size:12px;color:#9ca3af;margin:0 0 4px">Platzhalter:</p>
                                <div style="display:flex;flex-wrap:wrap;gap:4px">
                                    @foreach($ph as $p)
                                        <span style="background:#e0e7ff;color:#3730a3;font-size:11px;padding:2px 8px;border-radius:12px">{{ $p['key'] }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div style="display:flex;gap:8px">
                            <button
                                onclick="demoPrintDymo(this, '{{ $template->slug }}')"
                                style="background:#f3f4f6;color:#374151;border:1px solid #d1d5db;border-radius:8px;padding:8px 16px;font-size:14px;font-weight:500;cursor:pointer">
                                Demo drucken
                            </button>
                            <script>
                                if (!window._demoTemplates) window._demoTemplates = {};
                                window._demoTemplates['{{ $template->slug }}'] = @json($demoXml);
                            </script>
                            @if(!$isSelected)
                                <button
                                    wire:click="selectTemplate('{{ $category }}', '{{ $template->slug }}')"
                                    style="flex:1;background:#2563eb;color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:14px;font-weight:500;cursor:pointer">
                                    Auswaehlen
                                </button>
                            @else
                                <button disabled
                                    style="flex:1;background:#22c55e;color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:14px;font-weight:500;cursor:not-allowed">
                                    Ausgewaehlt
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    @if($categories->isEmpty())
        <div style="background:#fff;border-radius:12px;padding:40px;text-align:center;color:#6b7280">
            <p>Keine Druckvorlagen verfuegbar. Bitte den Administrator kontaktieren.</p>
        </div>
    @endif

    @push('scripts')
    <script>
    async function demoPrintDymo(btn, slug) {
        var labelXml = window._demoTemplates[slug];
        if (!labelXml) { alert('Template nicht gefunden'); return; }
        var origText = btn.textContent;
        btn.textContent = 'Druckt...';
        btn.disabled = true;

        try {
            var port = await findDymoPort();
            var printerName = await getDymoPrinterName(port);
            var printParams = '<' + 'LabelWriterPrintParams><' + 'Copies>1<' + '/Copies><' + '/LabelWriterPrintParams>';
            var body = 'printerName=' + encodeURIComponent(printerName)
                + '&labelXml=' + encodeURIComponent(labelXml)
                + '&printParamsXml=' + encodeURIComponent(printParams);

            var response = await fetch('https://127.0.0.1:' + port + '/DYMO/DLS/Printing/PrintLabel2', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body,
                mode: 'cors',
            });
            var result = await response.text();

            if (result === 'true') {
                btn.textContent = 'Gedruckt!';
                btn.style.background = '#dcfce7';
                btn.style.color = '#16a34a';
                setTimeout(function() { btn.textContent = origText; btn.style.background = ''; btn.style.color = ''; btn.disabled = false; }, 3000);
            } else {
                alert('Druckfehler: ' + result);
                btn.textContent = origText;
                btn.disabled = false;
            }
        } catch (e) {
            alert('DYMO Service nicht erreichbar. Ist DYMO Connect auf diesem PC gestartet?');
            btn.textContent = origText;
            btn.disabled = false;
        }
    }

    async function getDymoPrinterName(port) {
        try {
            var r = await fetch('https://127.0.0.1:' + port + '/DYMO/DLS/Printing/GetPrinters', { mode: 'cors' });
            var xml = await r.text();
            // Ersten <Name>...</Name> aus LabelWriterPrinter ziehen
            var m = xml.match(/<Name>([^<]+)<\/Name>/);
            if (m && m[1]) return m[1];
        } catch (e) {}
        return 'DYMO LabelWriter Wireless';
    }

    async function findDymoPort() {
        var ports = [41951, 41952, 41953, 41954, 41955];
        for (var i = 0; i < ports.length; i++) {
            try {
                var r = await fetch('https://127.0.0.1:' + ports[i] + '/DYMO/DLS/Printing/StatusConnected', { mode: 'cors' });
                var t = await r.text();
                if (t === 'true') return ports[i];
            } catch (e) { continue; }
        }
        throw new Error('DYMO nicht gefunden');
    }
    </script>
    @endpush
</x-filament-panels::page>
