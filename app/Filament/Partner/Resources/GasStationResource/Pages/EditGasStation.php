<?php

namespace App\Filament\Partner\Resources\GasStationResource\Pages;

use App\Filament\Partner\Resources\GasStationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditGasStation extends EditRecord
{
    protected static string $resource = GasStationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Vor dem Speichern: Mitarbeiter-Felder entfernen (sind nicht in der DB).
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Diese Felder gehoeren nicht zum GasStation-Model
        unset($data['_add_employee_id'], $data['_station_role'], $data['_is_primary']);

        return $data;
    }

    /**
     * Nach dem Speichern: Mitarbeiter-Zuordnung aktualisieren.
     */
    protected function afterSave(): void
    {
        // $this->data enthaelt ALLE Formulardaten inkl. dehydrated(false)
        $employeeId = $this->data['_add_employee_id'] ?? null;
        $stationRole = $this->data['_station_role'] ?? null;
        $isPrimary = $this->data['_is_primary'] ?? false;

        if ($employeeId) {
            // Sync: Falls schon zugeordnet updaten, sonst neu hinzufuegen
            $this->record->users()->syncWithoutDetaching([
                $employeeId => [
                    'station_role' => $stationRole,
                    'is_primary' => $isPrimary,
                    'assigned_at' => now(),
                ],
            ]);

            // Falls Hauptstandort: andere Zuordnungen auf nicht-primaer setzen
            if ($isPrimary) {
                DB::table('gas_station_user')
                    ->where('user_id', $employeeId)
                    ->where('gas_station_id', '!=', $this->record->id)
                    ->update(['is_primary' => false]);
            }

            \Filament\Notifications\Notification::make()
                ->title('Mitarbeiter zugeordnet')
                ->success()
                ->send();
        }
    }
}
