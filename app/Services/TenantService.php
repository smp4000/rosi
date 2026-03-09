<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Service fuer Mandanten-Verwaltung.
 * Erstellt Mandanten, setzt Tenant-Kontext, verwaltet Trial.
 */
class TenantService
{
    /**
     * Neuen Mandanten erstellen (bei Partner-Registrierung).
     * Erstellt Mandant + setzt den User als Owner.
     */
    public function createTenant(User $owner, array $data): Tenant
    {
        return DB::transaction(function () use ($owner, $data) {
            // Slug aus Firmennamen generieren
            $slug = Tenant::generateSlug($data['name']);

            // Mandant erstellen
            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $slug,
                'owner_id' => $owner->id,
                'email' => $data['email'] ?? $owner->email,
                'phone' => $data['phone'] ?? null,
                'street' => $data['street'] ?? null,
                'zip' => $data['zip'] ?? null,
                'city' => $data['city'] ?? null,
                'country' => $data['country'] ?? 'DE',
                'trial_ends_at' => now()->addDays(14),
                'subscription_status' => 'trial',
                'is_active' => true,
                'settings' => $this->defaultSettings(),
            ]);

            // User dem Mandanten zuordnen
            $owner->update([
                'tenant_id' => $tenant->id,
                'type' => 'partner',
            ]);

            return $tenant;
        });
    }

    /**
     * Tenant-Kontext in der Session setzen.
     */
    public function setCurrentTenant(Tenant $tenant): void
    {
        session(['tenant_id' => $tenant->id]);
    }

    /**
     * Tenant-Kontext aus der Session entfernen.
     */
    public function clearCurrentTenant(): void
    {
        session()->forget('tenant_id');
    }

    /**
     * Aktuellen Mandanten aus der Session laden.
     */
    public function getCurrentTenant(): ?Tenant
    {
        $tenantId = session('tenant_id');

        if (! $tenantId) {
            return null;
        }

        return Tenant::find($tenantId);
    }

    /**
     * Standard-Einstellungen fuer neue Mandanten.
     */
    protected function defaultSettings(): array
    {
        return [
            'locale' => 'de',
            'timezone' => 'Europe/Berlin',
            'date_format' => 'd.m.Y',
            'time_format' => 'H:i',
            'currency' => 'EUR',
            'notifications' => [
                'email' => true,
                'push' => true,
                'in_app' => true,
            ],
        ];
    }

    /**
     * Mandant deaktivieren.
     */
    public function deactivateTenant(Tenant $tenant): void
    {
        $tenant->update(['is_active' => false]);

        // Alle Benutzer des Mandanten deaktivieren
        $tenant->users()->update(['is_active' => false]);
    }

    /**
     * Mandant aktivieren.
     */
    public function activateTenant(Tenant $tenant): void
    {
        $tenant->update(['is_active' => true]);

        // Owner wieder aktivieren
        if ($tenant->owner) {
            $tenant->owner->update(['is_active' => true]);
        }
    }

    /**
     * Trial-Phase verlaengern.
     */
    public function extendTrial(Tenant $tenant, int $days = 14): void
    {
        $newEnd = $tenant->trial_ends_at?->addDays($days) ?? now()->addDays($days);

        $tenant->update([
            'trial_ends_at' => $newEnd,
            'subscription_status' => 'trial',
        ]);
    }
}
