<div>
    <div class="rounded-2xl border border-gray-200/60 bg-white/80 p-8 shadow-xl shadow-gray-200/50 backdrop-blur-sm">
        <div class="mb-6 text-center">
            {{-- E-Mail Icon --}}
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-primary-100">
                <svg class="h-8 w-8 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">E-Mail bestaetigen</h1>
            <p class="mt-2 text-sm text-gray-500">
                Wir haben eine Bestaetigungsmail an <strong class="text-gray-700">{{ auth()->user()->email }}</strong> gesendet.
                Bitte klicken Sie auf den Link in der E-Mail um Ihr Konto zu aktivieren.
            </p>
        </div>

        @if(session('resent'))
            <div class="mb-4 rounded-xl border border-green-200 bg-green-50 p-3 text-center text-sm text-green-700">
                ✓ Eine neue Bestaetigungsmail wurde gesendet!
            </div>
        @endif

        <div class="space-y-4">
            <button wire:click="resend"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-primary-600 to-primary-700 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary-600/25 transition hover:from-primary-700 hover:to-primary-800 disabled:opacity-50"
                    wire:loading.attr="disabled">
                <svg wire:loading wire:target="resend" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span wire:loading.remove wire:target="resend">Bestaetigungsmail erneut senden</span>
                <span wire:loading wire:target="resend">Wird gesendet...</span>
            </button>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                    Abmelden
                </button>
            </form>
        </div>
    </div>
</div>
