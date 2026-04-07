<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Device;
use App\Models\FuelTheft;
use App\Models\GasStation;
use App\Models\User;
use App\Notifications\FuelTheftNotification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Tankbetrug-API fuer POS-App.
 */
class FuelTheftController extends ApiController
{
    /**
     * GET /api/v1/fuel-thefts/form-data?device_token=xxx
     *
     * Formulardaten: Station-Details + Mitarbeiter-Profil.
     */
    public function formData(Request $request): JsonResponse
    {
        $device = $this->findDevice($request->query('device_token', ''));
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $user = $request->user();
        if (! $user) {
            return $this->error('Nicht angemeldet.', 401);
        }

        $station = GasStation::find($device->station_id);
        if (! $station) {
            return $this->error('Tankstelle nicht gefunden.', 404);
        }

        $profile = $user->employeeProfile;

        return $this->success([
            'station' => [
                'id' => $station->id,
                'name' => $station->name,
                'num_pumps' => $station->num_pumps ?? 8,
                'fuel_types' => $station->fuel_types ?? [],
                'has_camera' => (bool) ($station->has_camera ?? true),
            ],
            'employee' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'street' => $profile?->street,
                'zip' => $profile?->zip,
                'city' => $profile?->city,
            ],
        ]);
    }

    /**
     * POST /api/v1/fuel-thefts
     *
     * Tankbetrug melden (Multipart wegen Beleg-Foto).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Nicht angemeldet.', 401);
        }

        $device = $this->findDevice($request->input('device_token', ''));
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $station = GasStation::find($device->station_id);
        if (! $station) {
            return $this->error('Tankstelle nicht gefunden.', 404);
        }

        // Validierung
        $validated = $request->validate([
            'incident_at' => 'required|date',
            'product' => 'required|string|max:100',
            'pump_number' => 'required|integer|min:1',
            'quantity' => 'required|numeric|min:0.01',
            'amount' => 'required|numeric|min:0.01',
            'license_plate_available' => 'required',
            'license_plate' => 'nullable|string|max:20',
            'comment' => 'required|string|max:2000',
            'signature' => 'required|string',
            'legal_notice_accepted' => 'required',
            'camera_missing' => 'nullable',
            'receipt' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
        ]);

        // Beleg speichern
        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')
                ->store('fuel-thefts/receipts', 'public');
        }

        // Mitarbeiter-Profil fuer Zeugen-Daten
        $profile = $user->employeeProfile;

        $licensePlateAvailable = filter_var($validated['license_plate_available'], FILTER_VALIDATE_BOOLEAN);

        $fuelTheft = FuelTheft::create([
            'tenant_id' => $station->tenant_id,
            'gas_station_id' => $station->id,
            'reported_by' => $user->id,
            'status' => 'submitted',
            'submitted_at' => now(),

            // Vorfall
            'incident_at' => Carbon::parse($validated['incident_at']),
            'product' => $validated['product'],
            'pump_number' => $validated['pump_number'],
            'quantity' => $validated['quantity'],
            'amount' => $validated['amount'],

            // Fahrzeug
            'license_plate_available' => $licensePlateAvailable,
            'license_plate' => $licensePlateAvailable
                ? strtoupper(trim($validated['license_plate'] ?? ''))
                : null,
            'camera_missing' => filter_var($validated['camera_missing'] ?? '0', FILTER_VALIDATE_BOOLEAN),

            // Umstaende
            'comment' => $validated['comment'],

            // Zeuge (vom angemeldeten Mitarbeiter)
            'witness_first_name' => $user->first_name,
            'witness_last_name' => $user->last_name,
            'witness_address' => $profile?->street,
            'witness_zip' => $profile?->zip,
            'witness_city' => $profile?->city,
            'witness_country' => 'DE',

            // Belege
            'receipt_path' => $receiptPath,
            'signature' => $validated['signature'],

            // Rechtsbelehrung
            'legal_notice_accepted' => true,
            'legal_notice_accepted_at' => now(),

            // Zahlung
            'payment_status' => 'open',
        ]);

        // Etikett automatisch drucken (DYMO)
        try {
            PrintController::printFuelTheftLabel([
                'date' => Carbon::parse($validated['incident_at'])->format('d.m.Y H:i'),
                'license_plate' => $licensePlateAvailable
                    ? strtoupper(trim($validated['license_plate'] ?? ''))
                    : 'Kein Kennzeichen',
                'product' => $this->productLabel($validated['product']),
                'pump_number' => $validated['pump_number'],
                'quantity' => number_format((float) $validated['quantity'], 3, ',', '.'),
                'amount' => number_format((float) $validated['amount'], 2, ',', '.'),
                'station' => $station->name,
            ]);
        } catch (\Exception $e) {
            Log::warning('Tankbetrug-Etikett konnte nicht gedruckt werden', [
                'error' => $e->getMessage(),
            ]);
        }

        // Bell-Notification an alle Partner/Inhaber des Mandanten senden
        $partners = User::where('tenant_id', $station->tenant_id)
            ->where('type', 'partner')
            ->where('is_active', true)
            ->get();

        $notifTitle = 'Neuer Tankbetrug: ' . $station->name;
        $notifBody = number_format($validated['amount'], 2, ',', '.') . ' EUR'
            . ' | Zapfpunkt ' . $validated['pump_number']
            . ' | ' . $validated['product']
            . ' | ' . Carbon::parse($validated['incident_at'])->format('d.m.Y H:i');

        foreach ($partners as $partner) {
            $partner->notify(new FuelTheftNotification(
                title: $notifTitle,
                body: $notifBody,
                level: 'danger',
            ));
        }

        return $this->success([
            'id' => $fuelTheft->id,
            'status' => $fuelTheft->status,
        ], 'Tankbetrug erfolgreich gemeldet.', 201);
    }

    /**
     * Kraftstoffart-Kuerzel in lesbaren Text umwandeln.
     */
    private function productLabel(string $product): string
    {
        return match ($product) {
            'super_e5' => 'Super E5',
            'super_e10' => 'Super E10',
            'diesel' => 'Diesel',
            'super_plus' => 'Super Plus',
            'ultimate_diesel' => 'Ultimate Diesel',
            'ultimate_102' => 'Ultimate 102',
            default => $product,
        };
    }

    /**
     * Geraet anhand des Klartext-Tokens finden.
     */
    private function findDevice(string $plainToken): ?Device
    {
        if (empty($plainToken)) {
            return null;
        }

        $devices = Device::where('is_active', true)->get();

        foreach ($devices as $device) {
            if (Hash::check($plainToken, $device->device_token_hash)) {
                return $device;
            }
        }

        return null;
    }
}
