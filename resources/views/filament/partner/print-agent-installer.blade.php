{{-- Stations-Installer: enroll.json per "Speichern unter" in den EXE-Ordner ablegen.
     Logik via Alpine (x-data), da <script> im Filament-Modal nicht ausgefuehrt wird. --}}
<div style="display:flex;flex-direction:column;gap:14px"
    x-data="{
        async save(url, name, btn) {
            const orig = btn.innerHTML;
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
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px;font-size:13px;color:#1e3a8a;line-height:1.5">
        <b>So geht's:</b>
        <ol style="margin:6px 0 0;padding-left:18px">
            <li>Station unten anklicken → <b>„Speichern unter"</b> öffnet sich</li>
            <li>In den Ordner wechseln, in dem <code>RosiPrintAgent.exe</code> liegt</li>
            <li>Speichern → <code>enroll.json</code> liegt dort, EXE starten = verbindet sich automatisch</li>
        </ol>
    </div>

    <div style="display:flex;flex-direction:column;gap:8px">
        @forelse($stations as $id => $name)
            <button type="button"
                x-on:click="save('{{ route('print-agent.enroll', $id) }}', '{{ addslashes($name) }}', $el)"
                style="display:flex;align-items:center;gap:10px;width:100%;text-align:left;background:#16a34a;color:#fff;border:none;border-radius:10px;padding:12px 16px;font-size:14px;font-weight:600;cursor:pointer">
                <span style="font-size:18px">💾</span>
                <span>{{ $name }}</span>
            </button>
        @empty
            <p style="color:#6b7280;font-size:13px">Keine Stationen vorhanden.</p>
        @endforelse
    </div>

    <p style="font-size:11px;color:#9ca3af;margin:0">
        Hinweis: „Ordner wählen" funktioniert in Chrome/Edge. In anderen Browsern wird die
        Datei normal heruntergeladen — dann bitte in den EXE-Ordner verschieben.
    </p>
</div>
