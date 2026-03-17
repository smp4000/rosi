<div>
    {{-- Ungueltige Einladung --}}
    @if($isInvalid)
        <div class="rounded-2xl bg-white/80 p-8 shadow-xl ring-1 ring-gray-900/5 backdrop-blur-sm text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-100">
                <svg class="h-8 w-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-900 mb-2">{{ __('partner.onboarding.invalid.title') }}</h2>
            <p class="text-gray-600">
                @if($invalidReason === 'expired')
                    {{ __('partner.onboarding.invalid.expired') }}
                @elseif($invalidReason === 'accepted')
                    {{ __('partner.onboarding.invalid.accepted') }}
                @else
                    {{ __('partner.onboarding.invalid.not_found') }}
                @endif
            </p>
            <a href="{{ route('login') }}" class="mt-6 inline-block rounded-lg bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 transition">
                Zum Login
            </a>
        </div>
    @else
        <div class="rounded-2xl bg-white/80 shadow-xl ring-1 ring-gray-900/5 backdrop-blur-sm">
            {{-- Header --}}
            <div class="border-b border-gray-100 px-8 py-6">
                <h1 class="text-xl font-bold text-gray-900">{{ __('partner.onboarding.title') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ $companyName }} &middot; {{ __('partner.onboarding.subtitle') }}</p>
            </div>

            {{-- Progress Bar --}}
            <div class="px-8 pt-6">
                <div class="flex items-center justify-between mb-2">
                    @php
                        $stepNames = __('partner.onboarding.steps');
                        $stepKeys = array_keys($stepNames);
                    @endphp
                    @foreach($stepNames as $key => $label)
                        @php $stepNum = array_search($key, $stepKeys) + 1; @endphp
                        <button
                            wire:click="goToStep({{ $stepNum }})"
                            class="flex flex-col items-center gap-1.5 group min-w-0 flex-shrink-0 {{ $stepNum <= $currentStep ? 'cursor-pointer' : 'cursor-default' }}"
                            @if($stepNum > $currentStep) disabled @endif
                        >
                            <div class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold transition-all
                                {{ $stepNum < $currentStep ? 'bg-green-500 text-white' : ($stepNum === $currentStep ? 'bg-primary-600 text-white ring-4 ring-primary-100' : 'bg-gray-200 text-gray-500') }}">
                                @if($stepNum < $currentStep)
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                @else
                                    {{ $stepNum }}
                                @endif
                            </div>
                            <span class="hidden sm:block text-xs text-center leading-tight {{ $stepNum === $currentStep ? 'text-primary-700 font-semibold' : 'text-gray-400' }}">{{ $label }}</span>
                        </button>
                        @if(!$loop->last)
                            <div class="flex-1 h-0.5 mx-1 {{ $stepNum < $currentStep ? 'bg-green-400' : 'bg-gray-200' }}"></div>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- Form Content --}}
            <div class="px-8 py-8">
                @if($errors->has('general'))
                    <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ $errors->first('general') }}</div>
                @endif

                {{-- Step 1: Persoenliche Daten --}}
                @if($currentStep === 1)
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">{{ __('partner.onboarding.steps.personal') }}</h3>
                        <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.first_name') }}</label>
                                <input wire:model="first_name" type="text" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.last_name') }} *</label>
                                <input wire:model="last_name" type="text" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" required>
                                @error('last_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.date_of_birth') }} *</label>
                                <input wire:model="date_of_birth" type="date" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" required>
                                @error('date_of_birth') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.place_of_birth') }}</label>
                                <input wire:model="place_of_birth" type="text" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.gender') }} *</label>
                                <select wire:model="gender" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" required>
                                    <option value="">Bitte waehlen</option>
                                    @foreach(__('partner.employee.genders') as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('gender') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.nationality') }} *</label>
                                <input wire:model="nationality" type="text" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" required>
                                @error('nationality') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.marital_status') }} *</label>
                                <select wire:model="marital_status" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" required>
                                    <option value="">Bitte waehlen</option>
                                    @foreach(__('partner.employee.marital_statuses') as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('marital_status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Step 2: Adresse & Kontakt --}}
                @if($currentStep === 2)
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">{{ __('partner.onboarding.steps.address') }}</h3>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.street') }} *</label>
                            <input wire:model="street" type="text" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" required>
                            @error('street') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.zip') }} *</label>
                                <input wire:model="zip" type="text" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" required>
                                @error('zip') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.city') }} *</label>
                                <input wire:model="city" type="text" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" required>
                                @error('city') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.country') }} *</label>
                                <input wire:model="country" type="text" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" required>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.phone') }}</label>
                            <input wire:model="phone" type="tel" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        </div>

                        <h4 class="mt-8 text-base font-semibold text-gray-700 border-t border-gray-200 pt-6">Notfallkontakt</h4>
                        <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.emergency_name') }}</label>
                                <input wire:model="emergency_name" type="text" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.emergency_phone') }}</label>
                                <input wire:model="emergency_phone" type="tel" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.emergency_relation') }}</label>
                                <input wire:model="emergency_relation" type="text" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" placeholder="z.B. Ehepartner, Eltern">
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Step 3: Steuer & SV --}}
                @if($currentStep === 3)
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">{{ __('partner.onboarding.steps.tax') }}</h3>
                        <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.tax_id') }} *</label>
                                <input wire:model="tax_id" type="text" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" required>
                                @error('tax_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.tax_class') }} *</label>
                                <select wire:model="tax_class" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" required>
                                    @foreach(__('partner.employee.tax_classes') as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                            <div class="flex items-center gap-3">
                                <input wire:model="church_tax" type="checkbox" id="church_tax" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                <label for="church_tax" class="text-sm font-medium text-gray-700">{{ __('partner.employee.fields.church_tax') }}</label>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.denomination') }}</label>
                                <input wire:model="denomination" type="text" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" placeholder="z.B. rk, ev">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.social_security_number') }}</label>
                            <input wire:model="social_security_number" type="text" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" placeholder="12 345678 A 123">
                        </div>

                        <h4 class="mt-8 text-base font-semibold text-gray-700 border-t border-gray-200 pt-6">Krankenversicherung</h4>
                        <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.health_insurance_name') }} *</label>
                                <input wire:model="health_insurance_name" type="text" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" required>
                                @error('health_insurance_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.health_insurance_type') }} *</label>
                                <select wire:model="health_insurance_type" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" required>
                                    @foreach(__('partner.employee.insurance_types') as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.health_insurance_number') }}</label>
                                <input wire:model="health_insurance_number" type="text" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>
                            <div class="flex items-center gap-3 sm:pt-7">
                                <input wire:model="pension_insurance" type="checkbox" id="pension_insurance" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                <label for="pension_insurance" class="text-sm font-medium text-gray-700">{{ __('partner.employee.fields.pension_insurance') }}</label>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Step 4: Bankverbindung --}}
                @if($currentStep === 4)
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">{{ __('partner.onboarding.steps.bank') }}</h3>
                        <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-700 mb-4">
                            <svg class="inline h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                            Ihre Bankdaten werden verschluesselt gespeichert (AES-256).
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.iban') }} *</label>
                            <input wire:model="iban" type="text" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 font-mono" required placeholder="DE89 3704 0044 0532 0130 00">
                            @error('iban') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.bic') }}</label>
                                <input wire:model="bic" type="text" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 font-mono" placeholder="COBADEFFXXX">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.bank_name') }}</label>
                                <input wire:model="bank_name" type="text" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.account_holder') }} *</label>
                            <input wire:model="account_holder" type="text" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" required>
                            @error('account_holder') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endif

                {{-- Step 5: Qualifikationen --}}
                @if($currentStep === 5)
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">{{ __('partner.onboarding.steps.qualifications') }}</h3>
                        <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.education_level') }}</label>
                                <select wire:model="education_level" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                    <option value="">Bitte waehlen</option>
                                    @foreach(__('partner.employee.education_levels') as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.professional_training') }}</label>
                                <input wire:model="professional_training" type="text" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" placeholder="z.B. Kauffrau im Einzelhandel">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('partner.employee.fields.drivers_license') }}</label>
                            <div class="flex flex-wrap gap-3">
                                @foreach(['B', 'BE', 'C', 'CE', 'C1', 'C1E', 'AM', 'A1', 'A2', 'A'] as $class)
                                    <label class="flex items-center gap-1.5">
                                        <input wire:model="drivers_license" type="checkbox" value="{{ $class }}" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                        <span class="text-sm text-gray-700">{{ $class }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.certifications') }}</label>
                            <input wire:model="certifications_text" type="text" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" placeholder="Kommagetrennt: ADR-Schein, Staplerschein">
                        </div>
                    </div>
                @endif

                {{-- Step 6: Kinder & Familie --}}
                @if($currentStep === 6)
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">{{ __('partner.onboarding.steps.children') }}</h3>
                        <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.num_children') }}</label>
                                <input wire:model.live="num_children" type="number" min="0" max="20" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>
                            <div class="flex items-center gap-3 pt-6">
                                <input wire:model="child_allowance" type="checkbox" id="child_allowance" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                <label for="child_allowance" class="text-sm font-medium text-gray-700">{{ __('partner.employee.fields.child_allowance') }}</label>
                            </div>
                        </div>

                        @if(count($children) > 0)
                            <div class="space-y-3 mt-4">
                                @foreach($children as $index => $child)
                                    <div class="flex items-end gap-3 p-3 bg-gray-50 rounded-lg">
                                        <div class="flex-1">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.child_name') }}</label>
                                            <input wire:model="children.{{ $index }}.name" type="text" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                        </div>
                                        <div class="flex-1">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.employee.fields.child_birth_date') }}</label>
                                            <input wire:model="children.{{ $index }}.birth_date" type="date" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                        </div>
                                        <button wire:click="removeChild({{ $index }})" type="button" class="mb-1 rounded-lg p-2 text-red-500 hover:bg-red-50 transition">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <button wire:click="addChild" type="button" class="flex items-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-800 transition">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            Kind hinzufuegen
                        </button>
                    </div>
                @endif

                {{-- Step 7: Passwort & Bestaetigung --}}
                @if($currentStep === 7)
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">{{ __('partner.onboarding.steps.confirmation') }}</h3>

                        <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.onboarding.confirmation.password') }} *</label>
                                <input wire:model="password" type="password" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" required>
                                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.onboarding.confirmation.password_confirm') }} *</label>
                                <input wire:model="password_confirmation" type="password" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" required>
                            </div>
                        </div>

                        <div class="mt-6 space-y-3 rounded-lg bg-gray-50 p-4">
                            <label class="flex items-start gap-3">
                                <input wire:model="accept_privacy" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                <span class="text-sm text-gray-700">{{ __('partner.onboarding.confirmation.accept_privacy') }} *</span>
                            </label>
                            @error('accept_privacy') <p class="text-sm text-red-600 ml-7">{{ $message }}</p> @enderror

                            <label class="flex items-start gap-3">
                                <input wire:model="accept_terms" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                <span class="text-sm text-gray-700">{{ __('partner.onboarding.confirmation.accept_terms') }} *</span>
                            </label>
                            @error('accept_terms') <p class="text-sm text-red-600 ml-7">{{ $message }}</p> @enderror

                            <label class="flex items-start gap-3">
                                <input wire:model="confirm_data" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                <span class="text-sm text-gray-700">{{ __('partner.onboarding.confirmation.confirm_data') }} *</span>
                            </label>
                            @error('confirm_data') <p class="text-sm text-red-600 ml-7">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.onboarding.confirmation.signed_name') }} *</label>
                            <input wire:model="signed_name" type="text" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 italic" placeholder="Ihr vollstaendiger Name" required>
                            @error('signed_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endif
            </div>

            {{-- Navigation Buttons --}}
            <div class="flex items-center justify-between border-t border-gray-100 px-8 py-5">
                <div>
                    @if($currentStep > 1)
                        <button wire:click="previousStep" type="button" class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                            {{ __('partner.onboarding.buttons.back') }}
                        </button>
                    @endif
                </div>
                <div>
                    @if($currentStep < $totalSteps)
                        <button wire:click="nextStep" type="button" class="rounded-lg bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 shadow-sm transition">
                            {{ __('partner.onboarding.buttons.next') }}
                            <svg class="inline ml-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                        </button>
                    @else
                        <button wire:click="completeOnboarding" type="button" class="inline-flex items-center gap-2 rounded-lg bg-[#059669] px-6 py-2.5 text-sm font-semibold text-white hover:bg-[#047857] shadow-sm transition" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="completeOnboarding">
                                <svg class="inline h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                {{ __('partner.onboarding.buttons.complete') }}
                            </span>
                            <span wire:loading wire:target="completeOnboarding">
                                <svg class="inline h-4 w-4 mr-1 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                Wird verarbeitet...
                            </span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
