<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * E-Mail-Verifizierungs-Hinweis.
 * Zeigt die Aufforderung zur E-Mail-Bestaetigung.
 */
#[Layout('layouts.guest')]
#[Title('E-Mail bestaetigen')]
class VerifyEmail extends Component
{
    /**
     * Verifizierungsmail erneut senden.
     */
    public function resend(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirect(route('dashboard'));
            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        session()->flash('resent', true);
    }

    public function render()
    {
        // Falls E-Mail bereits verifiziert, zum Dashboard weiterleiten
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirect(route('dashboard'));
        }

        return view('livewire.auth.verify-email');
    }
}
