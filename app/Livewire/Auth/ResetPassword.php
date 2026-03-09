<?php

namespace App\Livewire\Auth;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Passwort-zuruecksetzen-Komponente.
 * Wird aufgerufen ueber den Reset-Link aus der E-Mail.
 */
#[Layout('layouts.guest')]
#[Title('Passwort zuruecksetzen')]
class ResetPassword extends Component
{
    #[Locked]
    public string $token = '';

    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->query('email', '');
    }

    public function rules(): array
    {
        return [
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Bitte geben Sie Ihre E-Mail-Adresse ein.',
            'password.required' => 'Bitte waehlen Sie ein neues Passwort.',
            'password.confirmed' => 'Die Passwoerter stimmen nicht ueberein.',
        ];
    }

    /**
     * Passwort zuruecksetzen.
     */
    public function resetPassword(): void
    {
        $this->validate();

        $status = Password::reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function ($user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            session()->flash('success', 'Ihr Passwort wurde erfolgreich zurueckgesetzt. Sie koennen sich jetzt anmelden.');
            $this->redirect(route('login'));
        } else {
            $this->addError('email', 'Der Reset-Link ist ungueltig oder abgelaufen. Bitte fordern Sie einen neuen an.');
        }
    }

    public function render()
    {
        return view('livewire.auth.reset-password');
    }
}
