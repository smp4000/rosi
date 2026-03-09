<div>
    <div class="rounded-2xl border border-gray-200/60 bg-white/80 p-8 shadow-xl shadow-gray-200/50 backdrop-blur-sm">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-bold text-gray-900">Passwort vergessen?</h1>
            <p class="mt-1 text-sm text-gray-500">Kein Problem. Geben Sie Ihre E-Mail ein und wir senden Ihnen einen Reset-Link.</p>
        </div>

        @if($sent)
            {{-- Erfolg --}}
            <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-center">
                <svg class="mx-auto mb-2 h-10 w-10 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <p class="text-sm font-medium text-green-800">Reset-Link wurde gesendet!</p>
                <p class="mt-1 text-xs text-green-600">Bitte pruefen Sie Ihren Posteingang (und Spam-Ordner).</p>
            </div>
        @else
            <form wire:submit="sendResetLink" class="space-y-5">
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700">E-Mail-Adresse</label>
                    <input wire:model="email" type="email" id="email" autofocus autocomplete="email"
                           class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm transition
                                  focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none
                                  @error('email') border-red-400 @enderror"
                           placeholder="ihre@email.de">
                    @error('email')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-primary-600 to-primary-700 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary-600/25 transition hover:from-primary-700 hover:to-primary-800 disabled:opacity-50"
                        wire:loading.attr="disabled">
                    <svg wire:loading wire:target="sendResetLink" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="sendResetLink">Reset-Link senden</span>
                    <span wire:loading wire:target="sendResetLink">Wird gesendet...</span>
                </button>
            </form>
        @endif

        <p class="mt-6 text-center text-sm text-gray-500">
            <a href="{{ route('login') }}" class="font-semibold text-primary-600 transition hover:text-primary-700" wire:navigate>
                &larr; Zurueck zur Anmeldung
            </a>
        </p>
    </div>
</div>
