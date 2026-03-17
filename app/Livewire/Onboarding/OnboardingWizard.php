<?php

namespace App\Livewire\Onboarding;

use App\Models\Invitation;
use App\Services\OnboardingService;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * 7-Step Onboarding-Wizard fuer neue Mitarbeiter.
 * Oeffentlich zugaenglich via Token-Link (kein Login noetig).
 */
#[Layout('layouts.onboarding')]
#[Title('Mitarbeiter-Registrierung')]
class OnboardingWizard extends Component
{
    public int $currentStep = 1;
    public int $totalSteps = 7;

    // Token & Einladung
    public string $token = '';
    public ?Invitation $invitation = null;
    public string $companyName = '';

    // Step 1: Persoenliche Daten
    public string $first_name = '';
    public string $last_name = '';
    public string $date_of_birth = '';
    public string $place_of_birth = '';
    public string $gender = '';
    public string $nationality = 'deutsch';
    public string $marital_status = '';

    // Step 2: Adresse & Kontakt
    public string $street = '';
    public string $zip = '';
    public string $city = '';
    public string $country = 'Deutschland';
    public string $phone = '';
    public string $emergency_name = '';
    public string $emergency_phone = '';
    public string $emergency_relation = '';

    // Step 3: Steuer & SV
    public string $tax_id = '';
    public string $tax_class = '1';
    public bool $church_tax = false;
    public string $denomination = '';
    public string $social_security_number = '';
    public string $health_insurance_name = '';
    public string $health_insurance_type = 'gesetzlich';
    public string $health_insurance_number = '';
    public bool $pension_insurance = true;

    // Step 4: Bankverbindung
    public string $iban = '';
    public string $bic = '';
    public string $bank_name = '';
    public string $account_holder = '';

    // Step 5: Qualifikationen
    public string $education_level = '';
    public string $professional_training = '';
    public array $drivers_license = [];
    public string $certifications_text = '';

    // Step 6: Kinder & Familie
    public int $num_children = 0;
    public array $children = [];
    public bool $child_allowance = false;

    // Step 7: Passwort & Bestaetigung
    public string $password = '';
    public string $password_confirmation = '';
    public bool $accept_privacy = false;
    public bool $accept_terms = false;
    public bool $confirm_data = false;
    public string $signed_name = '';

    // Status
    public bool $isInvalid = false;
    public string $invalidReason = '';

    public function mount(string $token): void
    {
        $this->token = $token;

        $service = app(OnboardingService::class);
        $this->invitation = $service->validateToken($token);

        if (! $this->invitation) {
            $this->isInvalid = true;

            // Grund ermitteln
            $inv = Invitation::where('token', $token)->first();
            if (! $inv) {
                $this->invalidReason = 'not_found';
            } elseif ($inv->isAccepted()) {
                $this->invalidReason = 'accepted';
            } else {
                $this->invalidReason = 'expired';
            }

            return;
        }

        $this->companyName = $this->invitation->invitedByUser?->tenant?->company_name
            ?? $this->invitation->invitedByUser?->tenant?->name
            ?? 'Ihr Arbeitgeber';
    }

    /**
     * Validierungsregeln pro Step.
     */
    protected function stepRules(): array
    {
        return match ($this->currentStep) {
            1 => [
                'last_name' => 'required|string|max:255',
                'first_name' => 'nullable|string|max:255',
                'date_of_birth' => 'required|date|before:today',
                'place_of_birth' => 'nullable|string|max:255',
                'gender' => 'required|in:male,female,diverse',
                'nationality' => 'required|string|max:100',
                'marital_status' => 'required|in:single,married,divorced,widowed,separated,partnership',
            ],
            2 => [
                'street' => 'required|string|max:255',
                'zip' => 'required|string|max:10',
                'city' => 'required|string|max:255',
                'country' => 'required|string|max:100',
                'phone' => 'nullable|string|max:50',
                'emergency_name' => 'nullable|string|max:255',
                'emergency_phone' => 'nullable|string|max:50',
                'emergency_relation' => 'nullable|string|max:100',
            ],
            3 => [
                'tax_id' => 'required|string|max:20',
                'tax_class' => 'required|in:1,2,3,4,5,6',
                'social_security_number' => 'nullable|string|max:20',
                'health_insurance_name' => 'required|string|max:255',
                'health_insurance_type' => 'required|in:gesetzlich,privat,familienversichert',
                'health_insurance_number' => 'nullable|string|max:50',
            ],
            4 => [
                'iban' => 'required|string|max:34',
                'bic' => 'nullable|string|max:11',
                'bank_name' => 'nullable|string|max:255',
                'account_holder' => 'required|string|max:255',
            ],
            5 => [
                'education_level' => 'nullable|in:hauptschule,realschule,abitur,fachabitur,ohne',
                'professional_training' => 'nullable|string|max:255',
            ],
            6 => [
                'num_children' => 'integer|min:0|max:20',
            ],
            7 => [
                'password' => 'required|string|min:8|confirmed',
                'accept_privacy' => 'accepted',
                'accept_terms' => 'accepted',
                'confirm_data' => 'accepted',
                'signed_name' => 'required|string|max:255',
            ],
            default => [],
        };
    }

    /**
     * Deutsche Fehlermeldungen.
     */
    protected function stepMessages(): array
    {
        return [
            'last_name.required' => 'Nachname ist erforderlich.',
            'date_of_birth.required' => 'Geburtsdatum ist erforderlich.',
            'date_of_birth.before' => 'Geburtsdatum muss in der Vergangenheit liegen.',
            'gender.required' => 'Bitte waehlen Sie ein Geschlecht.',
            'marital_status.required' => 'Bitte waehlen Sie den Familienstand.',
            'street.required' => 'Strasse ist erforderlich.',
            'zip.required' => 'PLZ ist erforderlich.',
            'city.required' => 'Stadt ist erforderlich.',
            'tax_id.required' => 'Steuer-ID ist erforderlich.',
            'tax_class.required' => 'Steuerklasse ist erforderlich.',
            'health_insurance_name.required' => 'Krankenkasse ist erforderlich.',
            'iban.required' => 'IBAN ist erforderlich.',
            'account_holder.required' => 'Kontoinhaber ist erforderlich.',
            'password.required' => 'Passwort ist erforderlich.',
            'password.min' => 'Passwort muss mindestens 8 Zeichen lang sein.',
            'password.confirmed' => 'Passwoerter stimmen nicht ueberein.',
            'accept_privacy.accepted' => 'Bitte akzeptieren Sie die Datenschutzerklaerung.',
            'accept_terms.accepted' => 'Bitte akzeptieren Sie die Nutzungsbedingungen.',
            'confirm_data.accepted' => 'Bitte bestaetigen Sie die Richtigkeit Ihrer Angaben.',
            'signed_name.required' => 'Bitte geben Sie Ihren vollstaendigen Namen ein.',
        ];
    }

    public function nextStep(): void
    {
        $this->validate($this->stepRules(), $this->stepMessages());
        $this->currentStep = min($this->currentStep + 1, $this->totalSteps);
    }

    public function previousStep(): void
    {
        $this->currentStep = max($this->currentStep - 1, 1);
    }

    public function goToStep(int $step): void
    {
        if ($step < $this->currentStep) {
            $this->currentStep = $step;
        }
    }

    /**
     * Kind hinzufuegen (Step 6).
     */
    public function addChild(): void
    {
        $this->children[] = ['name' => '', 'birth_date' => ''];
        $this->num_children = count($this->children);
    }

    /**
     * Kind entfernen (Step 6).
     */
    public function removeChild(int $index): void
    {
        unset($this->children[$index]);
        $this->children = array_values($this->children);
        $this->num_children = count($this->children);
    }

    /**
     * Onboarding abschliessen.
     */
    public function completeOnboarding()
    {
        $this->validate($this->stepRules(), $this->stepMessages());

        try {
            $profileData = [
                // Step 1
                'first_name' => $this->first_name ?: null,
                'last_name' => $this->last_name,
                'date_of_birth' => $this->date_of_birth,
                'place_of_birth' => $this->place_of_birth ?: null,
                'gender' => $this->gender,
                'nationality' => $this->nationality,
                'marital_status' => $this->marital_status,
                // Step 2
                'street' => $this->street,
                'zip' => $this->zip,
                'city' => $this->city,
                'country' => $this->country,
                'emergency_name' => $this->emergency_name ?: null,
                'emergency_phone' => $this->emergency_phone ?: null,
                'emergency_relation' => $this->emergency_relation ?: null,
                // Step 3
                'tax_id' => $this->tax_id,
                'tax_class' => $this->tax_class,
                'church_tax' => $this->church_tax,
                'denomination' => $this->denomination ?: null,
                'social_security_number' => $this->social_security_number ?: null,
                'health_insurance_name' => $this->health_insurance_name,
                'health_insurance_type' => $this->health_insurance_type,
                'health_insurance_number' => $this->health_insurance_number ?: null,
                'pension_insurance' => $this->pension_insurance,
                // Step 4
                'iban' => $this->iban,
                'bic' => $this->bic ?: null,
                'bank_name' => $this->bank_name ?: null,
                'account_holder' => $this->account_holder,
                // Step 5
                'education_level' => $this->education_level ?: null,
                'professional_training' => $this->professional_training ?: null,
                'drivers_license' => $this->drivers_license ?: null,
                'certifications' => $this->certifications_text
                    ? array_map('trim', explode(',', $this->certifications_text))
                    : null,
                // Step 6
                'num_children' => $this->num_children,
                'children_data' => ! empty($this->children) ? $this->children : null,
                'child_allowance' => $this->child_allowance,
            ];

            app(OnboardingService::class)->completeOnboarding(
                $this->invitation,
                $profileData,
                $this->password,
            );

            return $this->redirect(route('onboarding.success'), navigate: true);
        } catch (\Exception $e) {
            Log::error('Onboarding-Fehler: ' . $e->getMessage(), [
                'token' => $this->token,
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addError('general', 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.');
        }
    }

    public function render()
    {
        return view('livewire.onboarding.wizard');
    }
}
