<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Passwort-vergessen-Komponente.
 * Sendet den Reset-Link per E-Mail.
 */
#[Layout('layouts.guest')]
#[Title('Passwort vergessen')]
class ForgotPassword extends Component
{
    public string $email = '';
    public bool $sent = false;

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Bitte geben Sie Ihre E-Mail-Adresse ein.',
            'email.email' => 'Bitte geben Sie eine gueltige E-Mail-Adresse ein.',
        ];
    }

    /**
     * Reset-Link senden.
     */
    public function sendResetLink(): void
    {
        $this->validate();

        $status = Password::sendResetLink(['email' => $this->email]);

        if ($status === Password::RESET_LINK_SENT) {
            $this->sent = true;
        } else {
            $this->addError('email', 'Es konnte kein Reset-Link an diese Adresse gesendet werden.');
        }
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}
