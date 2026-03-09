<div>
    <div class="rounded-2xl border border-gray-200/60 bg-white/80 p-8 shadow-xl shadow-gray-200/50 backdrop-blur-sm">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-bold text-gray-900">Neues Passwort</h1>
            <p class="mt-1 text-sm text-gray-500">Waehlen Sie ein neues sicheres Passwort</p>
        </div>

        <form wire:submit="resetPassword" class="space-y-5">
            {{-- E-Mail --}}
            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700">E-Mail-Adresse</label>
                <input wire:model="email" type="email" id="email" autocomplete="email"
                       class="w-full rounded-xl border border-gray-300 bg-gray-50 px-4 py-2.5 text-sm
                              @error('email') border-red-400 @enderror"
                       readonly>
                @error('email')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Neues Passwort --}}
            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700">Neues Passwort</label>
                <input wire:model="password" type="password" id="password" autofocus autocomplete="new-password"
                       class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm transition
                              focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none
                              @error('password') border-red-400 @enderror"
                       placeholder="Mind. 8 Zeichen, Gross-/Kleinbuchstaben + Zahl">
                @error('password')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Passwort bestaetigen --}}
            <div>
                <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-gray-700">Passwort bestaetigen</label>
                <input wire:model="password_confirmation" type="password" id="password_confirmation" autocomplete="new-password"
                       class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm transition
                              focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none"
                       placeholder="Passwort wiederholen">
            </div>

            <button type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-primary-600 to-primary-700 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary-600/25 transition hover:from-primary-700 hover:to-primary-800 disabled:opacity-50"
                    wire:loading.attr="disabled">
                <svg wire:loading wire:target="resetPassword" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span wire:loading.remove wire:target="resetPassword">Passwort zuruecksetzen</span>
                <span wire:loading wire:target="resetPassword">Wird gespeichert...</span>
            </button>
        </form>
    </div>
</div>
