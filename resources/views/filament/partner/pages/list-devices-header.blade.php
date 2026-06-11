{{-- QR-Code Modal --}}
@if(session('show_qr_code'))
<div
    x-data="{ open: true }"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
    @keydown.escape.window="open = false"
>
    <div
        class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl p-8 max-w-md w-full mx-4"
        @click.outside="open = false"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                QR-Code fuer Station
            </h2>
            <button @click="open = false" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Station Name --}}
        <div class="text-center mb-4">
            <span class="inline-block bg-blue-100 text-blue-800 text-sm font-semibold px-3 py-1 rounded-full">
                {{ session('qr_station') }}
            </span>
        </div>

        {{-- QR Code Image --}}
        <div class="flex justify-center mb-4">
            <div class="bg-white p-4 rounded-lg border-2 border-gray-200">
                <img
                    src="data:image/svg+xml;base64,{{ session('qr_base64') }}"
                    alt="QR-Code"
                    class="w-64 h-64"
                />
            </div>
        </div>

        {{-- Token (zum manuellen Eingeben) --}}
        <div class="mb-4">
            <label class="block text-xs text-gray-500 mb-1">Setup-Token (fuer manuelle Eingabe):</label>
            <div class="flex">
                <input
                    type="text"
                    value="{{ session('qr_token') }}"
                    readonly
                    class="flex-1 text-xs font-mono bg-gray-50 border rounded-l px-2 py-1 text-gray-600"
                    id="qr-token-input"
                />
                <button
                    @click="navigator.clipboard.writeText(document.getElementById('qr-token-input').value); $el.textContent = 'Kopiert!'; setTimeout(() => $el.textContent = 'Kopieren', 2000)"
                    class="bg-blue-500 text-white text-xs px-3 py-1 rounded-r hover:bg-blue-600"
                >
                    Kopieren
                </button>
            </div>
        </div>

        {{-- Gueltig bis (Datum mit "Uhr", Texte wie "Dauerhaft gueltig" ohne) --}}
        @php $expiresText = session('qr_expires') . (preg_match('/\d{2}:\d{2}$/', session('qr_expires', '')) ? ' Uhr' : ''); @endphp
        <div class="text-center text-sm text-gray-500">
            Gueltig bis: <strong>{{ $expiresText }}</strong>
        </div>

        {{-- Drucken Button --}}
        <div class="mt-4 flex gap-2">
            <button
                @click="
                    const w = window.open('', '_blank');
                    w.document.write('<html><body style=&quot;text-align:center;font-family:sans-serif&quot;>');
                    w.document.write('<h2>ROSI POS - {{ session('qr_station') }}</h2>');
                    w.document.write('<img src=&quot;data:image/svg+xml;base64,{{ session('qr_base64') }}&quot; style=&quot;width:300px;height:300px&quot;/>');
                    w.document.write('<p>Gueltig bis: {{ $expiresText }}</p>');
                    w.document.write('</body></html>');
                    w.document.close();
                    w.print();
                "
                class="flex-1 bg-gray-100 text-gray-700 text-sm font-semibold px-4 py-2 rounded-lg hover:bg-gray-200"
            >
                Drucken
            </button>
            <button
                @click="open = false"
                class="flex-1 bg-blue-500 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-blue-600"
            >
                Schliessen
            </button>
        </div>
    </div>
</div>
@endif
