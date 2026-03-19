<?php

namespace App\Filament\Partner\Resources\EmployeeResource\Pages;

use App\Filament\Partner\Resources\EmployeeResource;
use App\Models\EmployeeBankAccount;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

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

        // EmployeeProfile-Daten in den Form-State laden
        // Filament 5 loest employeeProfile.xyz nicht automatisch auf
        $profile = $user->employeeProfile;
        if ($profile) {
            $data['employeeProfile'] = $profile->toArray();
        }

        // Bankkonten aus eigener Tabelle laden
        $bankAccounts = $user->bankAccounts;
        if ($bankAccounts->isEmpty() && ! empty($data['employeeProfile']['iban'])) {
            // Migration: Alte Einzelfelder als erstes Konto uebernehmen
            $data['_bank_accounts'] = [[
                'type' => 'Gehalt',
                'account_holder' => $data['employeeProfile']['account_holder'] ?? '',
                'iban' => $data['employeeProfile']['iban'] ?? '',
                'bic' => $data['employeeProfile']['bic'] ?? '',
                'bank_name' => $data['employeeProfile']['bank_name'] ?? '',
            ]];
        } else {
            $data['_bank_accounts'] = $bankAccounts
                ->map(fn ($acc) => [
                    'id' => $acc->id,
                    'type' => $acc->type,
                    'account_holder' => $acc->account_holder ?? '',
                    'iban' => $acc->iban,
                    'bic' => $acc->bic ?? '',
                    'bank_name' => $acc->bank_name ?? '',
                ])
                ->values()
                ->toArray();
        }

        // Pivot-Daten fuer Tankstellen-Repeater laden
        $data['_gas_station_assignments'] = $user->gasStations
            ->map(fn ($gs) => [
                'gas_station_id' => $gs->id,
                'station_role' => $gs->pivot->station_role,
                'is_primary' => (bool) $gs->pivot->is_primary,
            ])
            ->values()
            ->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Dehydrated(false) Felder entfernen
        unset($data['_gas_station_assignments']);
        unset($data['_bank_accounts']);

        // EmployeeProfile-Daten separat speichern
        if (isset($data['employeeProfile'])) {
            $profileData = $data['employeeProfile'];
            unset($data['employeeProfile']);

            // bank_accounts JSON-Feld nicht im Profile speichern
            unset($profileData['bank_accounts']);

            // NOT NULL Felder mit Defaults absichern (ConvertEmptyStringsToNull → null)
            $notNullDefaults = [
                'country' => 'DE',
                'church_tax' => false,
                'pension_insurance' => true,
                'num_children' => 0,
                'child_allowance' => false,
                'health_certificate' => false,
                'status' => 'pending',
                'preferred_channel' => 'email',
            ];
            foreach ($notNullDefaults as $field => $default) {
                if (array_key_exists($field, $profileData) && $profileData[$field] === null) {
                    $profileData[$field] = $default;
                }
            }

            $user = $this->getRecord();
            $profile = $user->employeeProfile;
            if ($profile) {
                $profile->update($profileData);
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->syncGasStationAssignments();
        $this->syncBankAccounts();
    }

    protected function getCancelFormAction(): \Filament\Actions\Action
    {
        return parent::getCancelFormAction()
            ->label('Zurueck zur Uebersicht')
            ->url($this->getResource()::getUrl('index'));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    private function syncBankAccounts(): void
    {
        $accounts = $this->data['_bank_accounts'] ?? [];
        $user = $this->record;
        $tenantId = session('tenant_id');

        // Bestehende IDs sammeln
        $existingIds = $user->bankAccounts()->pluck('id')->toArray();
        $keepIds = [];

        foreach ($accounts as $index => $account) {
            $iban = $account['iban'] ?? null;
            if (! $iban) continue;

            $data = [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'type' => $account['type'] ?? 'Gehalt',
                'account_holder' => $account['account_holder'] ?: null,
                'iban' => $iban,
                'bic' => $account['bic'] ?: null,
                'bank_name' => $account['bank_name'] ?: null,
                'sort_order' => $index,
            ];

            if (! empty($account['id']) && in_array($account['id'], $existingIds)) {
                // Update
                EmployeeBankAccount::where('id', $account['id'])->update($data);
                $keepIds[] = $account['id'];
            } else {
                // Create
                $new = EmployeeBankAccount::create($data);
                $keepIds[] = $new->id;
            }
        }

        // Geloeschte Konten entfernen
        $user->bankAccounts()->whereNotIn('id', $keepIds)->delete();
    }

    private function syncGasStationAssignments(): void
    {
        $assignments = $this->data['_gas_station_assignments'] ?? [];
        $user = $this->record;

        // Neue Zuordnungen aufbauen
        $syncData = [];
        foreach ($assignments as $assignment) {
            $gsId = $assignment['gas_station_id'] ?? null;
            if (! $gsId) continue;

            $syncData[$gsId] = [
                'station_role' => $assignment['station_role'] ?? null,
                'is_primary' => $assignment['is_primary'] ?? false,
                'assigned_at' => now(),
            ];
        }

        // Sync ersetzt alle bisherigen Zuordnungen
        $user->gasStations()->sync($syncData);

        // Falls Hauptstandort gesetzt: andere User-Zuordnungen auf nicht-primaer
        foreach ($syncData as $gsId => $pivotData) {
            if ($pivotData['is_primary']) {
                DB::table('gas_station_user')
                    ->where('user_id', $user->id)
                    ->where('gas_station_id', '!=', $gsId)
                    ->update(['is_primary' => false]);
            }
        }
    }
}
