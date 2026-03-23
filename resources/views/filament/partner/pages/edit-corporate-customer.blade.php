<x-filament-panels::page>
    @php
        $customer = $this->record;
        $invoiceCount = $customer->invoices()->count();
        $totalAmount = $customer->invoices()->sum('amount');
        $lastInvoice = $customer->invoices()->orderByDesc('invoice_date')->first();
        $initial = strtoupper(substr($customer->name ?? 'K', 0, 1));
    @endphp

    {{-- Kunden-Zusammenfassung --}}
    <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; margin-bottom: 24px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: {{ $invoiceCount > 0 ? '20px' : '0' }};">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div style="width: 56px; height: 56px; border-radius: 50%; background: #dc2626; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: bold;">
                    {{ $initial }}
                </div>
                <div>
                    <div style="font-size: 1.2rem; font-weight: 700; color: #1a202c;">{{ $customer->name }}</div>
                    <div style="font-size: 0.85rem; color: #6b7280;">
                        # {{ $customer->customer_number }}
                        @if($customer->email)
                            &nbsp; ✉ {{ $customer->email }}
                        @endif
                    </div>
                </div>
            </div>
            @if($customer->email && $customer->email !== 'smp4000@me.com')
                <a href="mailto:{{ $customer->email }}" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border: 1px solid #e5e7eb; border-radius: 8px; text-decoration: none; color: #374151; font-size: 0.85rem;">
                    ✉ E-Mail
                </a>
            @endif
        </div>

        @if($invoiceCount > 0)
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;">
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px;">
                    <div style="font-size: 0.75rem; color: #dc2626; font-weight: 600; text-transform: uppercase;">Rechnungen</div>
                    <div style="font-size: 1.5rem; font-weight: 700;">{{ $invoiceCount }}</div>
                </div>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px;">
                    <div style="font-size: 0.75rem; color: #dc2626; font-weight: 600; text-transform: uppercase;">Gesamtbetrag</div>
                    <div style="font-size: 1.5rem; font-weight: 700;">{{ number_format($totalAmount, 2, ',', '.') }} &euro;</div>
                </div>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px;">
                    <div style="font-size: 0.75rem; color: #dc2626; font-weight: 600; text-transform: uppercase;">Letzte Rechnung</div>
                    <div style="font-size: 1.5rem; font-weight: 700;">{{ $lastInvoice?->invoice_date instanceof \Carbon\Carbon ? $lastInvoice->invoice_date->format('d.m.Y') : ($lastInvoice?->invoice_date ?? '-') }}</div>
                </div>
            </div>
        @endif
    </div>

    {{-- Standard Filament Formular --}}
    {{ $this->form }}

    <div class="mt-4 flex gap-3">
        <x-filament::button type="submit" wire:click="save">
            Speichern
        </x-filament::button>
    </div>
</x-filament-panels::page>
