<div>
    <div class="rounded-2xl border border-gray-200/60 bg-white/80 p-8 shadow-xl shadow-gray-200/50 backdrop-blur-sm">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-bold text-gray-900">Kostenlos testen</h1>
            <p class="mt-1 text-sm text-gray-500">14 Tage gratis &mdash; keine Kreditkarte noetig</p>
        </div>

        <form wire:submit="register" class="space-y-5">
            {{-- Firmendaten --}}
            <div>
                <label for="company_name" class="mb-1.5 block text-sm font-medium text-gray-700">Firmenname *</label>
                <input wire:model="company_name" type="text" id="company_name" autofocus
                       class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm transition
                              focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none
                              @error('company_name') border-red-400 @enderror"
                       placeholder="z.B. Muster Tankstellen GmbH">
                @error('company_name')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Name (2 Spalten) --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="first_name" class="mb-1.5 block text-sm font-medium text-gray-700">Vorname</label>
                    <input wire:model="first_name" type="text" id="first_name"
                           class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm transition
                                  focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none"
                           placeholder="Max">
                </div>
                <div>
                    <label for="last_name" class="mb-1.5 block text-sm font-medium text-gray-700">Nachname *</label>
                    <input wire:model="last_name" type="text" id="last_name"
                           class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm transition
                                  focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none
                                  @error('last_name') border-red-400 @enderror"
                           placeholder="Mustermann">
                    @error('last_name')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- E-Mail --}}
            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700">E-Mail-Adresse *</label>
                <input wire:model="email" type="email" id="email" autocomplete="email"
                       class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm transition
                              focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none
                              @error('email') border-red-400 @enderror"
                       placeholder="ihre@email.de">
                @error('email')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Telefon --}}
            <div>
                <label for="phone" class="mb-1.5 block text-sm font-medium text-gray-700">Telefon</label>
                <input wire:model="phone" type="tel" id="phone"
                       class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm transition
                              focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none"
                       placeholder="+49 123 456789">
            </div>

            {{-- Passwort --}}
            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700">Passwort *</label>
                <input wire:model="password" type="password" id="password" autocomplete="new-password"
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
                <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-gray-700">Passwort bestaetigen *</label>
                <input wire:model="password_confirmation" type="password" id="password_confirmation" autocomplete="new-password"
                       class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm transition
                              focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none"
                       placeholder="Passwort wiederholen">
            </div>

            {{-- DSGVO Checkboxen --}}
            <div class="space-y-3 rounded-xl bg-gray-50 p-4">
                <label class="flex items-start gap-2">
                    <input wire:model="accept_terms" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    <span class="text-sm text-gray-600">
                        Ich akzeptiere die <a href="#" class="text-primary-600 underline">Nutzungsbedingungen</a> *
                    </span>
                </label>
                @error('accept_terms')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror

                <label class="flex items-start gap-2">
                    <input wire:model="accept_privacy" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    <span class="text-sm text-gray-600">
                        Ich akzeptiere die <a href="#" class="text-primary-600 underline">Datenschutzerklaerung</a> *
                    </span>
                </label>
                @error('accept_privacy')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Submit --}}
            <button type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-primary-600 to-primary-700 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary-600/25 transition hover:from-primary-700 hover:to-primary-800 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-50"
                    wire:loading.attr="disabled">
                <svg wire:loading wire:target="register" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span wire:loading.remove wire:target="register">Kostenlos registrieren</span>
                <span wire:loading wire:target="register">Wird erstellt...</span>
            </button>

            <p class="text-center text-xs text-gray-400">
                14 Tage kostenlos testen, danach flexible Preismodelle
            </p>
        </form>
    </div>

    {{-- Login-Link --}}
    <p class="mt-6 text-center text-sm text-gray-500">
        Bereits registriert?
        <a href="{{ route('login') }}" class="font-semibold text-primary-600 transition hover:text-primary-700" wire:navigate>
            Jetzt anmelden
        </a>
    </p>
</div>
