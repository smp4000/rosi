<div>
    <div class="rounded-2xl border border-gray-200/60 bg-white/80 p-8 shadow-xl shadow-gray-200/50 backdrop-blur-sm">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-bold text-gray-900">Willkommen zurueck</h1>
            <p class="mt-1 text-sm text-gray-500">Melden Sie sich in Ihrem Konto an</p>
        </div>

        <form wire:submit="login" class="space-y-5">
            {{-- E-Mail --}}
            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700">E-Mail-Adresse</label>
                <input wire:model="email" type="email" id="email" autofocus autocomplete="email"
                       class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm transition
                              focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none
                              @error('email') border-red-400 focus:border-red-500 focus:ring-red-500/20 @enderror"
                       placeholder="ihre@email.de">
                @error('email')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Passwort --}}
            <div>
                <div class="mb-1.5 flex items-center justify-between">
                    <label for="password" class="text-sm font-medium text-gray-700">Passwort</label>
                    <a href="{{ route('password.request') }}" class="text-xs font-medium text-primary-600 transition hover:text-primary-700" wire:navigate>
                        Passwort vergessen?
                    </a>
                </div>
                <input wire:model="password" type="password" id="password" autocomplete="current-password"
                       class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm transition
                              focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none
                              @error('password') border-red-400 focus:border-red-500 focus:ring-red-500/20 @enderror"
                       placeholder="••••••••">
                @error('password')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Angemeldet bleiben --}}
            <label class="flex items-center gap-2">
                <input wire:model="remember" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                <span class="text-sm text-gray-600">Angemeldet bleiben</span>
            </label>

            {{-- Submit --}}
            <button type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-primary-600 to-primary-700 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary-600/25 transition hover:from-primary-700 hover:to-primary-800 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-50"
                    wire:loading.attr="disabled">
                <svg wire:loading wire:target="login" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span wire:loading.remove wire:target="login">Anmelden</span>
                <span wire:loading wire:target="login">Wird angemeldet...</span>
            </button>
        </form>
    </div>

    {{-- Registrierungs-Link --}}
    <p class="mt-6 text-center text-sm text-gray-500">
        Noch kein Konto?
        <a href="{{ route('register') }}" class="font-semibold text-primary-600 transition hover:text-primary-700" wire:navigate>
            Jetzt kostenlos testen
        </a>
    </p>
</div>
