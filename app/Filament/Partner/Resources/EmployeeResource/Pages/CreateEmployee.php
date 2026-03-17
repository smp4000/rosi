<?php

namespace App\Filament\Partner\Resources\EmployeeResource\Pages;

use App\Filament\Partner\Resources\EmployeeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Mitarbeiter direkt anlegen (ohne Einladung).
 * Erstellt User + EmployeeProfile in einer Transaktion.
 */
class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenantId = session('tenant_id');

        // User-Felder setzen
        $data['tenant_id'] = $tenantId;
        $data['type'] = 'employee';
        $data['is_active'] = true;
        $data['email_verified_at'] = now();

        // Temporaeres Passwort generieren (Mitarbeiter muss es zuruecksetzen)
        if (empty($data['password'])) {
            $data['password'] = Hash::make(str()->random(16));
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $user = $this->record;
        $tenantId = session('tenant_id');

        // EmployeeProfile erstellen falls nicht via Relation schon angelegt
        if (! $user->employeeProfile) {
            $user->employeeProfile()->create([
                'tenant_id' => $tenantId,
                'status' => 'pending',
            ]);
        }

        // Standard-Rolle zuweisen
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        if (! $user->hasAnyRole(['partner', 'stationsleiter', 'mitarbeiter'])) {
            $user->assignRole('mitarbeiter');
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
