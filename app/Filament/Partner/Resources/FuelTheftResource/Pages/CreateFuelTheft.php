<?php

namespace App\Filament\Partner\Resources\FuelTheftResource\Pages;

use App\Filament\Partner\Resources\FuelTheftResource;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;

/**
 * Tankbetrug melden: 5-Step Wizard.
 */
class CreateFuelTheft extends CreateRecord
{
    use HasWizard;

    protected static string $resource = FuelTheftResource::class;

    protected static ?string $title = 'Tankbetrug melden';

    /**
     * Steps koennen nicht uebersprungen werden - alle muessen durchlaufen werden.
     */
    public function hasSkippableSteps(): bool
    {
        return false;
    }

    /**
     * 5-Step Wizard Definition.
     */
    protected function getSteps(): array
    {
        return [
            Step::make('Vorfall')
                ->icon('heroicon-o-clock')
                ->description('Was ist passiert?')
                ->schema(FuelTheftResource::getStep1Schema()),

            Step::make('Fahrzeug')
                ->icon('heroicon-o-truck')
                ->description('Wer war es?')
                ->schema(FuelTheftResource::getStep2Schema()),

            Step::make('Umstaende')
                ->icon('heroicon-o-clipboard-document-list')
                ->description('Was war drumherum?')
                ->schema(FuelTheftResource::getStep3Schema()),

            Step::make('Belege & Unterschrift')
                ->icon('heroicon-o-paper-clip')
                ->description('Dokumentation')
                ->schema(FuelTheftResource::getStep4Schema()),

            Step::make('Rechtsbelehrung')
                ->icon('heroicon-o-shield-check')
                ->description('Bestaetigung & Absenden')
                ->schema(FuelTheftResource::getStep5Schema()),
        ];
    }

    /**
     * Vor dem Speichern: Melder und Status setzen.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reported_by'] = auth()->id();
        $data['tenant_id'] = auth()->user()->tenant_id;
        $data['status'] = 'submitted';
        $data['submitted_at'] = now();

        // Rechtsbelehrung-Zeitstempel
        if ($data['legal_notice_accepted'] ?? false) {
            $data['legal_notice_accepted_at'] = now();
        }

        // Kennzeichen uppercase
        if (! empty($data['license_plate'])) {
            $data['license_plate'] = strtoupper(trim($data['license_plate']));
        }

        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Tankbetrug erfolgreich gemeldet';
    }
}
