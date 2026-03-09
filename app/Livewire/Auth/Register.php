<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\ConsentService;
use App\Services\TenantService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Registrierungs-Komponente fuer Tankstellenpartner.
 * Erstellt User + Mandant (Tenant) mit 14-Tage-Testphase.
 */
#[Layout('layouts.guest')]
#[Title('Registrieren')]
class Register extends Component
{
    // Persoenliche Daten
    public string $first_name = '';
    public string $last_name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    // Firmendaten
    public string $company_name = '';
    public string $phone = '';

    // DSGVO
    public bool $accept_terms = false;
    public bool $accept_privacy = false;

    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'company_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'accept_terms' => ['accepted'],
            'accept_privacy' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'last_name.required' => 'Bitte geben Sie Ihren Nachnamen ein.',
            'email.required' => 'Bitte geben Sie Ihre E-Mail-Adresse ein.',
            'email.email' => 'Bitte geben Sie eine gueltige E-Mail-Adresse ein.',
            'email.unique' => 'Diese E-Mail-Adresse wird bereits verwendet.',
            'password.required' => 'Bitte waehlen Sie ein Passwort.',
            'password.confirmed' => 'Die Passwoerter stimmen nicht ueberein.',
            'password.min' => 'Das Passwort muss mindestens 8 Zeichen lang sein.',
            'company_name.required' => 'Bitte geben Sie Ihren Firmennamen ein.',
            'accept_terms.accepted' => 'Bitte akzeptieren Sie die Nutzungsbedingungen.',
            'accept_privacy.accepted' => 'Bitte akzeptieren Sie die Datenschutzerklaerung.',
        ];
    }

    /**
     * Registrierung durchfuehren.
     * Erstellt User + Mandant + DSGVO-Einwilligungen.
     */
    public function register(TenantService $tenantService, ConsentService $consentService): void
    {
        $this->validate();

        // Benutzer erstellen
        $user = User::create([
            'first_name' => $this->first_name ?: null,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'type' => 'partner',
            'phone' => $this->phone ?: null,
            'is_active' => true,
            'locale' => 'de',
        ]);

        // Mandant erstellen (mit 14-Tage-Trial)
        $tenant = $tenantService->createTenant($user, [
            'name' => $this->company_name,
            'email' => $this->email,
            'phone' => $this->phone,
        ]);

        // DSGVO: Einwilligungen protokollieren (Datenschutz + AGB)
        $consentService->recordRegistrationConsents($user);

        // E-Mail-Verifizierung ausloesen
        event(new Registered($user));

        // Einloggen
        Auth::login($user);

        // Tenant-Kontext setzen
        session(['tenant_id' => $tenant->id]);

        // Weiterleitung zur E-Mail-Verifizierung
        $this->redirect(route('verification.notice'));
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
