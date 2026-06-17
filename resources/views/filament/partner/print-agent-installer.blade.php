{{-- Stations-Installer in 2 Schritten:
     1) Geruest installieren (generischer .cmd-Installer, Ordnerwahl + Standard)
     2) Mit Station verbinden (enroll.json per "Speichern unter" in den Ordner)
     Logik via Alpine (x-data), da <script> im Filament-Modal nicht laeuft. --}}
<div style="display:flex;flex-direction:column;gap:18px"
    x-data="{
        async save(url, name, btn) {
            if (window.showSaveFilePicker) {
                try {
                    const handle = await window.showSaveFilePicker({
                        suggestedName: 'enroll.json',
                        types: [{ description: 'ROSI Enrollment', accept: { 'application/json': ['.json'] } }],
                    });
                    const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    if (!resp.ok) throw new Error('HTTP ' + resp.status);
                    const text = await resp.text();
                    const writable = await handle.createWritable();
                    await writable.write(text);
                    await writable.close();
                    btn.innerHTML = '✓ Gespeichert: ' + name;
                    btn.style.background = '#15803d';
                    return;
                } catch (e) {
                    if (e && e.name === 'AbortError') { return; }
                }
            }
            window.location = url;
        }
    }">

    {{-- Schritt 1 --}}
    <div>
        <h3 style="font-size:15px;font-weight:700;margin:0 0 8px">1. Geruest installieren</h3>
        <p style="font-size:13px;color:#374151;margin:0 0 10px;line-height:1.5">
            Lade den Installer herunter und führe ihn am Stations-PC aus. Im Setup wählst du den
            <b>Installationsordner</b> (Standard <code>%LOCALAPPDATA%\RosiPrintAgent</code>),
            der Agent wird installiert, in den Autostart gelegt und gestartet.
        </p>
        <a href="{{ url('downloads/ROSI-Print-Setup.exe') }}"
            style="display:inline-flex;align-items:center;gap:10px;background:#2563eb;color:#fff;text-decoration:none;border-radius:10px;padding:12px 18px;font-size:14px;font-weight:600">
            <span style="font-size:18px">⬇️</span> Installer herunterladen (ROSI-Print-Setup.exe)
        </a>
        <p style="font-size:11px;color:#9ca3af;margin:8px 0 0">
            Beim ersten Start meldet Windows evtl. „Unbekannter Herausgeber" → „Weitere Informationen"
            → „Trotzdem ausführen".
        </p>
    </div>

    <hr style="border:none;border-top:1px solid #e5e7eb;margin:0">

    {{-- Schritt 2 --}}
    <div>
        <h3 style="font-size:15px;font-weight:700;margin:0 0 8px">2. Mit Station verbinden</h3>
        <p style="font-size:13px;color:#374151;margin:0 0 10px;line-height:1.5">
            Nach dem Start meldet sich der Agent selbst und wartet auf <b>Freigabe</b> hier im
            Dashboard. <b>Oder</b> direkt verbinden: Station wählen → <b>„Speichern unter"</b> →
            <code>enroll.json</code> in den Installationsordner legen.
        </p>
        <div style="display:flex;flex-direction:column;gap:8px">
            @forelse($stations as $id => $name)
                <button type="button"
                    x-on:click="save('{{ route('print-agent.enroll', $id) }}', '{{ addslashes($name) }}', $el)"
                    style="display:flex;align-items:center;gap:10px;width:100%;text-align:left;background:#16a34a;color:#fff;border:none;border-radius:10px;padding:12px 16px;font-size:14px;font-weight:600;cursor:pointer">
                    <span style="font-size:18px">💾</span>
                    <span>enroll.json für: {{ $name }}</span>
                </button>
            @empty
                <p style="color:#6b7280;font-size:13px">Keine Stationen vorhanden.</p>
            @endforelse
        </div>
        <p style="font-size:11px;color:#9ca3af;margin:8px 0 0">
            „Ordner wählen" beim Speichern funktioniert in Chrome/Edge. In anderen Browsern wird
            die Datei normal heruntergeladen — dann in den Installationsordner verschieben.
        </p>
    </div>
</div>
