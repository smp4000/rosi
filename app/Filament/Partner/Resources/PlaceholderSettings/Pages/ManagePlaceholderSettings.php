<?php

namespace App\Filament\Partner\Resources\PlaceholderSettings\Pages;

use App\Filament\Partner\Resources\PlaceholderSettings\PlaceholderSettingResource;
use App\Models\PlaceholderSetting;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePlaceholderSettings extends ManageRecords
{
    protected static string $resource = PlaceholderSettingResource::class;

    public function mount(): void
    {
        parent::mount();

        // System-Platzhalter automatisch initialisieren (einmalig)
        $tenantId = session('tenant_id');
        if ($tenantId) {
            $exists = PlaceholderSetting::where('tenant_id', $tenantId)->exists();
            if (! $exists) {
                PlaceholderSetting::initializeForTenant($tenantId);
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Neuer Platzhalter')
                ->mutateFormDataUsing(function (array $data): array {
                    $data['tenant_id'] = session('tenant_id');

                    return $data;
                }),
        ];
    }
}
