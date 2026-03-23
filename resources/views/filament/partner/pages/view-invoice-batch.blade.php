<x-filament-panels::page>
    {{-- Statistik-Karten --}}
    <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 24px;">
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px;">
            <div style="font-size: 0.8rem; color: #6b7280;">Gesamt importiert</div>
            <div style="font-size: 1.8rem; font-weight: bold;">{{ $this->record->total_files }}</div>
            <div style="font-size: 0.75rem; color: #9ca3af;">Alle hochgeladenen Dateien 📄</div>
        </div>
        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px;">
            <div style="font-size: 0.8rem; color: #16a34a;">Erfolgreich</div>
            <div style="font-size: 1.8rem; font-weight: bold; color: #16a34a;">{{ $this->record->success_count }}</div>
            <div style="font-size: 0.75rem; color: #9ca3af;">Verarbeitet und zugeordnet ✅</div>
        </div>
        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 16px;">
            <div style="font-size: 0.8rem; color: #2563eb;">Neue Kunden</div>
            <div style="font-size: 1.8rem; font-weight: bold; color: #2563eb;">{{ $this->record->new_customers_count }}</div>
            <div style="font-size: 0.75rem; color: #9ca3af;">Automatisch angelegt 👤</div>
        </div>
        <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 16px;">
            <div style="font-size: 0.8rem; color: #d97706;">Duplikate</div>
            <div style="font-size: 1.8rem; font-weight: bold;">{{ $this->record->duplicate_count }}</div>
            <div style="font-size: 0.75rem; color: #9ca3af;">Bereits existierende Rechnungen 🔄</div>
        </div>
        <div style="background: {{ $this->record->error_count > 0 ? '#fef2f2' : '#f8fafc' }}; border: 1px solid {{ $this->record->error_count > 0 ? '#fecaca' : '#e2e8f0' }}; border-radius: 8px; padding: 16px;">
            <div style="font-size: 0.8rem; color: #dc2626;">Fehler</div>
            <div style="font-size: 1.8rem; font-weight: bold; color: {{ $this->record->error_count > 0 ? '#dc2626' : '#333' }};">{{ $this->record->error_count }}</div>
            <div style="font-size: 0.75rem; color: #9ca3af;">Nicht zuordenbar ⚠️</div>
        </div>
    </div>

    {{-- Fortschrittsbalken E-Mail-Versand --}}
    @if($this->isSending)
        <div wire:poll.3s="sendNextEmail" style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 20px; height: 20px; border: 3px solid #3b82f6; border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                    <span style="font-weight: bold; font-size: 1rem; color: #1e40af;">
                        📧 Sende E-Mail {{ $this->sendingCurrent }} von {{ $this->sendingTotal }}...
                    </span>
                </div>
                <button wire:click="cancelSending" style="background: #ef4444; color: #fff; border: none; padding: 6px 16px; border-radius: 6px; cursor: pointer; font-size: 0.85rem;">
                    ✕ Abbrechen
                </button>
            </div>

            {{-- Fortschrittsbalken --}}
            @php
                $percent = $this->sendingTotal > 0 ? round(($this->sendingCurrent / $this->sendingTotal) * 100) : 0;
            @endphp
            <div style="background: #dbeafe; border-radius: 999px; height: 24px; overflow: hidden; position: relative;">
                <div style="background: linear-gradient(90deg, #3b82f6, #2563eb); height: 100%; width: {{ $percent }}%; border-radius: 999px; transition: width 0.5s ease;">
                </div>
                <span style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 0.8rem; font-weight: bold; color: {{ $percent > 50 ? '#fff' : '#1e40af' }};">
                    {{ $percent }}%
                </span>
            </div>

            {{-- Aktueller Kunde + Statistik --}}
            <div style="display: flex; justify-content: space-between; margin-top: 10px; font-size: 0.85rem; color: #374151;">
                <span>
                    @if($this->sendingCurrentName)
                        Aktuell: <strong>{{ $this->sendingCurrentName }}</strong>
                    @endif
                </span>
                <span>
                    <span style="color: #16a34a;">✅ {{ $this->sendingSent }} gesendet</span>
                    @if($this->sendingFailed > 0)
                        <span style="margin-left: 12px; color: #dc2626;">❌ {{ $this->sendingFailed }} fehlgeschlagen</span>
                    @endif
                </span>
            </div>
        </div>

        <style>
            @keyframes spin { to { transform: rotate(360deg); } }
        </style>
    @endif

    {{-- Tab-Navigation --}}
    <div style="display: flex; gap: 4px; border-bottom: 2px solid #e5e7eb; margin-bottom: 16px;">
        @php
            $tabs = [
                'email' => ['label' => 'E-Mail-Versand', 'icon' => '📧', 'count' => $this->getEmailInvoices()->count()],
                'print' => ['label' => 'Druck-Versand', 'icon' => '🖨️', 'count' => $this->getPrintInvoices()->count()],
                'new_customers' => ['label' => 'Neue Kunden', 'icon' => '👤', 'count' => $this->record->new_customers_count],
                'errors' => ['label' => 'Fehler', 'icon' => '⚠️', 'count' => $this->getErrorInvoices()->count()],
                'duplicates' => ['label' => 'Duplikate', 'icon' => '🔄', 'count' => $this->getDuplicateInvoices()->count()],
            ];
        @endphp
        @foreach($tabs as $key => $tab)
            <button
                wire:click="$set('activeTab', '{{ $key }}')"
                style="padding: 10px 16px; font-size: 0.9rem; cursor: pointer; border: none; background: none; border-bottom: 3px solid {{ $activeTab === $key ? '#3b82f6' : 'transparent' }}; color: {{ $activeTab === $key ? '#3b82f6' : '#6b7280' }}; font-weight: {{ $activeTab === $key ? 'bold' : 'normal' }};"
            >
                {{ $tab['icon'] }} {{ $tab['label'] }}
                @if($tab['count'] > 0)
                    <span style="background: {{ $activeTab === $key ? '#3b82f6' : '#e5e7eb' }}; color: {{ $activeTab === $key ? '#fff' : '#374151' }}; border-radius: 9999px; padding: 1px 8px; font-size: 0.75rem; margin-left: 4px;">{{ $tab['count'] }}</span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- Tab-Inhalt --}}
    @if($activeTab === 'email')
        @php $emailInvoices = $this->getEmailInvoices(); @endphp
        @if($emailInvoices->isEmpty())
            <div style="padding: 24px; text-align: center; color: #9ca3af;">Keine E-Mail-Rechnungen in diesem Batch.</div>
        @else
            <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e5e7eb; background: #f9fafb;">
                            <th style="padding: 10px 12px; text-align: left;">Rechnungsnr.</th>
                            <th style="padding: 10px 12px; text-align: left;">Kunde</th>
                            <th style="padding: 10px 12px; text-align: left;">Tankstelle</th>
                            <th style="padding: 10px 12px; text-align: right;">Betrag</th>
                            <th style="padding: 10px 12px; text-align: center;">Status</th>
                            <th style="padding: 10px 12px; text-align: right;">Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($emailInvoices as $inv)
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 8px 12px; font-family: monospace;">{{ $inv->invoice_number }}</td>
                                <td style="padding: 8px 12px;">{{ $inv->corporateCustomer?->name ?? '-' }}</td>
                                <td style="padding: 8px 12px;">{{ $inv->gasStation?->name ?? '-' }}</td>
                                <td style="padding: 8px 12px; text-align: right;">{{ number_format($inv->amount, 2, ',', '.') }} &euro;</td>
                                <td style="padding: 8px 12px; text-align: center;">
                                    @if($inv->email_status === 'sent')
                                        <span style="background: #dcfce7; color: #16a34a; padding: 2px 8px; border-radius: 9999px; font-size: 0.8rem;">✅ Versendet</span>
                                    @elseif($inv->email_status === 'failed')
                                        <span style="background: #fef2f2; color: #dc2626; padding: 2px 8px; border-radius: 9999px; font-size: 0.8rem;" title="{{ $inv->email_error ?? '' }}">❌ Fehlgeschlagen</span>
                                    @else
                                        <span style="background: #f3f4f6; color: #6b7280; padding: 2px 8px; border-radius: 9999px; font-size: 0.8rem;">⏳ Ausstehend</span>
                                    @endif
                                </td>
                                <td style="padding: 8px 12px; text-align: right;">
                                    @if($inv->email_status !== 'sent')
                                        <button wire:click="sendSingleEmail('{{ $inv->id }}')" style="background: #3b82f6; color: #fff; border: none; padding: 4px 12px; border-radius: 6px; cursor: pointer; font-size: 0.8rem;">📧 Senden</button>
                                    @else
                                        <span style="color: #9ca3af; font-size: 0.8rem;">✅</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    @if($activeTab === 'print')
        @php $printInvoices = $this->getPrintInvoices(); @endphp
        @if($printInvoices->isEmpty())
            <div style="padding: 24px; text-align: center; color: #9ca3af;">Keine Druck-Rechnungen in diesem Batch.</div>
        @else
            @if($this->lastPrintPdfPath)
                <div style="padding: 12px 16px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between;">
                    <span style="color: #16a34a; font-size: 0.9rem;">✅ Sammel-PDF erstellt ({{ $printInvoices->count() }} Rechnungen)</span>
                    <a href="/sammel-pdf/{{ basename($this->lastPrintPdfPath) }}" target="_blank" style="background: #16a34a; color: #fff; padding: 6px 16px; border-radius: 6px; text-decoration: none; font-size: 0.85rem;">🖨️ PDF herunterladen</a>
                </div>
            @endif
            <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e5e7eb; background: #f9fafb;">
                            <th style="padding: 10px 12px; text-align: left;">Rechnungsnr.</th>
                            <th style="padding: 10px 12px; text-align: left;">Kunde</th>
                            <th style="padding: 10px 12px; text-align: left;">Adresse</th>
                            <th style="padding: 10px 12px; text-align: right;">Betrag</th>
                            <th style="padding: 10px 12px; text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($printInvoices as $inv)
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 8px 12px; font-family: monospace;">{{ $inv->invoice_number }}</td>
                                <td style="padding: 8px 12px;">{{ $inv->corporateCustomer?->name ?? '-' }}</td>
                                <td style="padding: 8px 12px;">{{ $inv->corporateCustomer?->full_address ?? '-' }}</td>
                                <td style="padding: 8px 12px; text-align: right;">{{ number_format($inv->amount, 2, ',', '.') }} &euro;</td>
                                <td style="padding: 8px 12px; text-align: center;">
                                    @if($inv->print_status === 'printed')
                                        <span style="background: #dbeafe; color: #2563eb; padding: 2px 8px; border-radius: 9999px; font-size: 0.8rem;">🖨️ Gedruckt</span>
                                    @else
                                        <span style="background: #f3f4f6; color: #6b7280; padding: 2px 8px; border-radius: 9999px; font-size: 0.8rem;">⏳ Ausstehend</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    @if($activeTab === 'new_customers')
        @php $newCustomers = $this->getNewCustomers(); @endphp
        @if($newCustomers->isEmpty())
            <div style="padding: 24px; text-align: center; color: #9ca3af;">Keine neuen Kunden in diesem Batch.</div>
        @else
            <div style="padding: 12px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; margin-bottom: 16px; font-size: 0.85rem; color: #1e40af;">
                ℹ️ Diese Kunden wurden automatisch aus den ZUGFeRD-Daten angelegt. Bitte E-Mail-Adresse und Versandeinstellungen pruefen und speichern.
            </div>
            <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e5e7eb; background: #f9fafb;">
                            <th style="padding: 10px 12px; text-align: left; width: 80px;">Kd.Nr.</th>
                            <th style="padding: 10px 12px; text-align: left;">Name</th>
                            <th style="padding: 10px 12px; text-align: left; width: 250px;">E-Mail</th>
                            <th style="padding: 10px 12px; text-align: center; width: 80px;">E-Mail</th>
                            <th style="padding: 10px 12px; text-align: center; width: 80px;">Druck</th>
                            <th style="padding: 10px 12px; text-align: center; width: 80px;">Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($newCustomers as $cust)
                            <tr style="border-bottom: 1px solid #f3f4f6;" wire:key="cust-{{ $cust->id }}">
                                <td style="padding: 8px 12px; font-family: monospace;">{{ $cust->customer_number }}</td>
                                <td style="padding: 8px 12px; font-weight: bold;">
                                    {{ $cust->name }}
                                    <div style="font-size: 0.75rem; color: #9ca3af; font-weight: normal;">{{ $cust->full_address }}</div>
                                </td>
                                <td style="padding: 8px 12px;">
                                    <input
                                        type="email"
                                        wire:model.blur="customerEmails.{{ $cust->id }}"
                                        value="{{ $cust->email }}"
                                        style="width: 100%; padding: 6px 10px; border: 1px solid {{ $cust->email === 'smp4000@me.com' ? '#fbbf24' : '#d1d5db' }}; border-radius: 6px; font-size: 0.85rem; background: {{ $cust->email === 'smp4000@me.com' ? '#fffbeb' : '#fff' }};"
                                        placeholder="E-Mail eingeben"
                                    />
                                </td>
                                <td style="padding: 8px 12px; text-align: center;">
                                    <input
                                        type="checkbox"
                                        wire:model.live="customerSendEmail.{{ $cust->id }}"
                                        style="width: 18px; height: 18px; cursor: pointer; accent-color: #16a34a;"
                                        {{ $cust->send_via_email ? 'checked' : '' }}
                                    />
                                </td>
                                <td style="padding: 8px 12px; text-align: center;">
                                    <input
                                        type="checkbox"
                                        wire:model.live="customerSendPrint.{{ $cust->id }}"
                                        style="width: 18px; height: 18px; cursor: pointer; accent-color: #2563eb;"
                                        {{ $cust->send_via_print ? 'checked' : '' }}
                                    />
                                </td>
                                <td style="padding: 8px 12px; text-align: center;">
                                    @php
                                        $emailChanged = ($customerEmails[$cust->id] ?? '') !== $cust->email;
                                        $sendEmailChanged = ($customerSendEmail[$cust->id] ?? false) != $cust->send_via_email;
                                        $sendPrintChanged = ($customerSendPrint[$cust->id] ?? false) != $cust->send_via_print;
                                        $hasChanges = $emailChanged || $sendEmailChanged || $sendPrintChanged;
                                    @endphp
                                    @if($hasChanges)
                                        <button
                                            wire:click="saveCustomer('{{ $cust->id }}')"
                                            style="background: #16a34a; color: #fff; border: none; padding: 4px 12px; border-radius: 6px; cursor: pointer; font-size: 0.8rem;"
                                        >
                                            Speichern
                                        </button>
                                    @else
                                        <span style="color: #9ca3af; font-size: 0.8rem;">✅</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    @if($activeTab === 'errors')
        @php $errorInvoices = $this->getErrorInvoices(); @endphp
        @if($errorInvoices->isEmpty())
            <div style="padding: 24px; text-align: center; color: #16a34a;">✅ Keine Fehler - alle Rechnungen erfolgreich verarbeitet!</div>
        @else
            <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e5e7eb; background: #f9fafb;">
                            <th style="padding: 10px 12px; text-align: left;">Rechnungsnr.</th>
                            <th style="padding: 10px 12px; text-align: left;">Fehlermeldung</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($errorInvoices as $inv)
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 8px 12px; font-family: monospace;">{{ $inv->invoice_number }}</td>
                                <td style="padding: 8px 12px; color: #dc2626;">{{ $inv->import_error_message }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    @if($activeTab === 'duplicates')
        @php $duplicateInvoices = $this->getDuplicateInvoices(); @endphp
        @if($duplicateInvoices->isEmpty())
            <div style="padding: 24px; text-align: center; color: #9ca3af;">Keine Duplikate in diesem Batch.</div>
        @else
            <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e5e7eb; background: #f9fafb;">
                            <th style="padding: 10px 12px; text-align: left;">Rechnungsnr.</th>
                            <th style="padding: 10px 12px; text-align: left;">Kunde</th>
                            <th style="padding: 10px 12px; text-align: right;">Betrag</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($duplicateInvoices as $inv)
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 8px 12px; font-family: monospace;">{{ $inv->invoice_number }}</td>
                                <td style="padding: 8px 12px;">{{ $inv->corporateCustomer?->name ?? '-' }}</td>
                                <td style="padding: 8px 12px; text-align: right;">{{ number_format($inv->amount, 2, ',', '.') }} &euro;</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</x-filament-panels::page>
