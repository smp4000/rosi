<?php

namespace App\Filament\Partner\Resources\EmployeeResource\Pages;

use App\Filament\Partner\Resources\EmployeeResource;
use Filament\Resources\Pages\EditRecord;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Sicherstellen dass EmployeeProfile existiert
        $user = $this->getRecord();
        if (! $user->employeeProfile) {
            $user->employeeProfile()->create([
                'tenant_id' => session('tenant_id'),
                'status' => 'pending',
            ]);
            $user->load('employeeProfile');
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
