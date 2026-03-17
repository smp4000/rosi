<?php

namespace App\Livewire\Onboarding;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Erfolgsseite nach abgeschlossenem Onboarding.
 */
#[Layout('layouts.onboarding')]
#[Title('Registrierung erfolgreich')]
class OnboardingSuccess extends Component
{
    public function render()
    {
        return view('livewire.onboarding.success');
    }
}
