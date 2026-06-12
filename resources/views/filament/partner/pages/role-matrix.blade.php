<x-filament-panels::page>
    {{-- Kopfzeile: Hinweis + eigene Rolle anlegen --}}
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:4px"
         x-data="{ neuerName: '' }">
        <div style="font-size:13px;color:#6b7280">
            🔒 System-Rollen sind anpassbar, aber nicht löschbar &nbsp;·&nbsp;
            Der Inhaber (Rolle „Partner") hat immer alle Rechte &nbsp;·&nbsp;
            Klick auf eine Zeile klappt die Aktionen auf
        </div>
        <div style="display:flex;gap:8px;align-items:center">
            <input type="text" x-model="neuerName" placeholder="Name der neuen Rolle"
                   maxlength="50"
                   @keydown.enter="if (neuerName.trim()) { $wire.rolleAnlegen(neuerName); neuerName = '' }"
                   style="border:1px solid #d1d5db;border-radius:8px;padding:7px 12px;font-size:13px;min-width:190px">
            <button type="button"
                    @click="if (neuerName.trim()) { $wire.rolleAnlegen(neuerName); neuerName = '' }"
                    style="background:#7c3aed;color:#fff;border:none;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:600;cursor:pointer">
                ＋ Eigene Rolle
            </button>
        </div>
    </div>

    @foreach ($this->bereiche as $bereichKey => $bereichLabel)
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden">
            <div style="padding:12px 16px;font-weight:700;font-size:14px;background:#f9fafb;border-bottom:1px solid #e5e7eb">
                {{ $bereichKey === 'mde' ? '📱' : '🖥️' }} {{ $bereichLabel }}
            </div>

            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:13px;min-width:640px">
                    <thead>
                        <tr style="border-bottom:2px solid #e5e7eb">
                            <th style="text-align:left;padding:10px 16px;min-width:230px;position:sticky;left:0;background:#fff;z-index:1">
                                Berechtigung
                            </th>
                            @foreach ($this->rollen as $rolle)
                                <th style="padding:10px 6px;text-align:center;min-width:104px">
                                    <div style="font-weight:600">{{ ucfirst($rolle->name) }}</div>
                                    <div style="font-weight:400;font-size:10.5px;color:#9ca3af;display:flex;align-items:center;justify-content:center;gap:4px">
                                        @if ($rolle->is_system)
                                            🔒 System
                                        @else
                                            ✏️ Eigene
                                            <button type="button"
                                                    wire:click="rolleLoeschen({{ $rolle->id }})"
                                                    wire:confirm="Rolle &quot;{{ $rolle->name }}&quot; wirklich löschen?"
                                                    title="Rolle löschen"
                                                    style="background:none;border:none;cursor:pointer;color:#dc2626;font-size:11px;padding:0">
                                                🗑️
                                            </button>
                                        @endif
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    @foreach ($this->katalog as $ressourceKey => $ressource)
                        @continue($ressource['bereich'] !== $bereichKey)
                        @php $aktionen = array_keys($ressource['aktionen']); @endphp

                        {{-- Pro Ressource ein eigenes tbody als Alpine-Scope (Auf-/Zuklappen) --}}
                        <tbody x-data="{ offen: false }">
                            {{-- Gruppen-Zeile: Ressource mit Zaehlern --}}
                            <tr style="background:#f9fafb;border-top:1px solid #f3f4f6;cursor:pointer"
                                @click="offen = !offen">
                                <td style="padding:9px 16px;font-weight:600;position:sticky;left:0;background:#f9fafb;z-index:1">
                                    <span x-text="offen ? '▾' : '▸'" style="color:#9ca3af;display:inline-block;width:14px">▸</span>
                                    {{ $ressource['emoji'] ?? '' }} {{ $ressource['label'] }}
                                </td>
                                @foreach ($this->rollen as $rolle)
                                    @php
                                        $anzahl = count(array_filter($aktionen, fn ($a) =>
                                            in_array("{$ressourceKey}.{$a}", $matrix[$rolle->id] ?? [], true)));
                                    @endphp
                                    <td style="text-align:center;padding:9px 6px"
                                        @click.stop="$wire.toggleRessource({{ $rolle->id }}, '{{ $ressourceKey }}')"
                                        title="Alle Aktionen an/aus">
                                        <span style="font-size:11.5px;color:{{ $anzahl === count($aktionen) ? '#16a34a' : ($anzahl > 0 ? '#b45309' : '#9ca3af') }};font-weight:600;cursor:pointer">
                                            {{ $anzahl }}/{{ count($aktionen) }}
                                        </span>
                                    </td>
                                @endforeach
                            </tr>

                            {{-- Aktions-Zeilen --}}
                            @foreach ($ressource['aktionen'] as $aktion => $aktionLabel)
                                <tr x-show="offen" x-cloak style="border-top:1px solid #f9fafb">
                                    <td style="padding:6px 16px 6px 44px;color:#4b5563;position:sticky;left:0;background:#fff;z-index:1">
                                        {{ $aktionLabel }}
                                    </td>
                                    @foreach ($this->rollen as $rolle)
                                        <td style="text-align:center;padding:6px">
                                            <input type="checkbox"
                                                   wire:click="togglePermission({{ $rolle->id }}, '{{ $ressourceKey }}.{{ $aktion }}')"
                                                   @checked(in_array("{$ressourceKey}.{$aktion}", $matrix[$rolle->id] ?? [], true))
                                                   style="width:16px;height:16px;accent-color:#7c3aed;cursor:pointer">
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>
        </div>
    @endforeach
</x-filament-panels::page>
