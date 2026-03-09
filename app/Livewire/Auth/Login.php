<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Login-Komponente mit Rate-Limiting und Tenant-Kontext.
 */
#[Layout('layouts.guest')]
#[Title('Anmelden')]
class Login extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Bitte geben Sie Ihre E-Mail-Adresse ein.',
            'email.email' => 'Bitte geben Sie eine gueltige E-Mail-Adresse ein.',
            'password.required' => 'Bitte geben Sie Ihr Passwort ein.',
        ];
    }

    /**
     * Login ausfuehren mit Rate-Limiting.
     */
    public function login(): void
    {
        $this->validate();

        // Rate-Limiting: Max. 5 Versuche pro Minute
        $throttleKey = Str::transliterate(Str::lower($this->email) . '|' . request()->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Zu viele Anmeldeversuche. Bitte versuchen Sie es in {$seconds} Sekunden erneut.",
            ]);
        }

        // Authentifizierung versuchen
        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => 'Die eingegebenen Zugangsdaten sind ungueltig.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $user = Auth::user();

        // Pruefen ob der Benutzer aktiv ist
        if (! $user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'Ihr Konto wurde deaktiviert. Bitte kontaktieren Sie den Support.',
            ]);
        }

        // Letzten Login aktualisieren
        $user->update(['last_login_at' => now()]);

        // Tenant-Kontext setzen (wenn kein Super-Admin)
        if ($user->tenant_id) {
            session(['tenant_id' => $user->tenant_id]);
        }

        session()->regenerate();

        // Weiterleitung je nach Benutzertyp
        $this->redirectIntended($this->getRedirectUrl($user));
    }

    /**
     * Weiterleitungs-URL basierend auf Benutzertyp bestimmen.
     */
    protected function getRedirectUrl($user): string
    {
        if ($user->isSuperAdmin()) {
            return route('filament.admin.pages.dashboard');
        }

        if ($user->isPartner()) {
            return route('dashboard');
        }

        if ($user->isEmployee()) {
            return route('dashboard'); // Spaeter: portal.dashboard
        }

        return route('dashboard');
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
