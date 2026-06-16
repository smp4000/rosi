<x-filament-panels::page>
    {{-- Druckziel fuer Demo-Druck (Stations-Agent + Drucker) --}}
    <div style="background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.1);border:1px solid #e5e7eb;padding:20px 24px;margin-bottom:20px">
        <label style="display:block;font-size:13px;color:#6b7280;margin:0 0 6px">Drucker fuer Demo-Druck</label>
        @if(count($destinations) > 0)
            <select wire:model="printTarget"
                style="width:100%;max-width:420px;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:14px;background:#fff">
                @foreach($destinations as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <p style="margin:8px 0 0;font-size:12px;color:#9ca3af">
                Der Demo-Druck geht in die Warteschlange — der Stations-Agent (ROSI Print) druckt lokal am DYMO.
            </p>
        @else
            <p style="margin:0;font-size:13px;color:#b45309">
                Kein Druck-Agent online. Bitte ROSI Print auf dem Stations-PC starten, dann lädt diese Seite neu.
            </p>
        @endif
    </div>

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
                                wire:click="demoPrint('{{ $template->slug }}')"
                                wire:loading.attr="disabled"
                                wire:target="demoPrint"
                                @disabled(count($destinations) === 0)
                                style="background:#f3f4f6;color:#374151;border:1px solid #d1d5db;border-radius:8px;padding:8px 16px;font-size:14px;font-weight:500;cursor:pointer">
                                Demo drucken
                            </button>
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
</x-filament-panels::page>
