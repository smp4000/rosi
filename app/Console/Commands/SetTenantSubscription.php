<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Artisan-Befehl zum Setzen des Abo-Status eines Mandanten.
 *
 * Nutzung:
 *   php artisan tenant:set-subscription              → Interaktiv
 *   php artisan tenant:set-subscription --status=active --plan=premium
 *   php artisan tenant:set-subscription --all         → Alle Tenants auf active
 */
class SetTenantSubscription extends Command
{
    protected $signature = 'tenant:set-subscription
                            {--status=active : Abo-Status (trial, active, expired, cancelled)}
                            {--plan=premium : Tarif-Name}
                            {--tenant= : Tenant-ID (UUID). Ohne Angabe: alle Tenants}
                            {--all : Alle Tenants aktualisieren}';

    protected $description = 'Abo-Status eines Mandanten setzen (z.B. Trial auf Active)';

    public function handle(): int
    {
        $status = $this->option('status');
        $plan = $this->option('plan');
        $tenantId = $this->option('tenant');
        $all = $this->option('all');

        $validStatuses = ['trial', 'active', 'expired', 'cancelled'];
        if (! in_array($status, $validStatuses)) {
            $this->error("Ungueltiger Status: {$status}. Erlaubt: " . implode(', ', $validStatuses));

            return self::FAILURE;
        }

        if ($tenantId) {
            $tenants = Tenant::where('id', $tenantId)->get();
        } elseif ($all) {
            $tenants = Tenant::all();
        } else {
            // Alle Tenants anzeigen und auswaehlen lassen
            $tenants = Tenant::all();
            if ($tenants->isEmpty()) {
                $this->error('Keine Mandanten gefunden.');

                return self::FAILURE;
            }

            $this->table(
                ['ID', 'Name', 'Status', 'Plan', 'Trial endet', 'Aktiv'],
                $tenants->map(fn ($t) => [
                    $t->id,
                    $t->name,
                    $t->subscription_status,
                    $t->subscription_plan ?? '-',
                    $t->trial_ends_at?->format('d.m.Y') ?? '-',
                    $t->is_active ? 'Ja' : 'Nein',
                ])
            );

            // Bei nur einem Tenant direkt diesen nehmen
            if ($tenants->count() === 1) {
                $tenants = $tenants;
            } else {
                $this->info('Nutze --tenant=UUID oder --all um fortzufahren.');

                return self::SUCCESS;
            }
        }

        if ($tenants->isEmpty()) {
            $this->error('Mandant nicht gefunden.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $oldStatus = $tenant->subscription_status;
            $tenant->update([
                'subscription_status' => $status,
                'subscription_plan' => $plan,
            ]);
            $this->info("✓ {$tenant->name}: {$oldStatus} → {$status} (Plan: {$plan})");
        }

        $this->newLine();
        $this->info('Fertig! Abo-Status aktualisiert.');

        return self::SUCCESS;
    }
}
