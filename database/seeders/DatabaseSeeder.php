<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Datenbank mit Testdaten fuellen.
     */
    public function run(): void
    {
        // Stammdaten und Rollen/Permissions zuerst
        $this->call(BrandSeeder::class);
        $this->call(LookupValueSeeder::class);
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(DocumentTemplateSeeder::class);
        $this->call(DepreciationReasonSeeder::class);
        $this->call(GasStationSeeder::class);

        // Globale Team-ID fuer Super-Admin Rollen-Zuweisung
        app(PermissionRegistrar::class)->setPermissionsTeamId(RolesAndPermissionsSeeder::GLOBAL_TEAM_ID);

        // --- Super-Admin (ohne Mandant) ---
        $superAdmin = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin@rosi.de',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'type' => 'super_admin',
            'is_active' => true,
            'locale' => 'de',
        ]);

        // --- Test-Partner mit Mandant ---
        $partner = User::create([
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'partner@rosi.de',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'type' => 'partner',
            'is_active' => true,
            'locale' => 'de',
        ]);

        // Mandant fuer den Partner erstellen
        $tenant = Tenant::create([
            'name' => 'Mustermann Tankstellen GmbH',
            'slug' => 'mustermann-tankstellen-gmbh',
            'owner_id' => $partner->id,
            'email' => 'partner@rosi.de',
            'street' => 'Musterstrasse 1',
            'zip' => '12345',
            'city' => 'Musterstadt',
            'country' => 'DE',
            'trial_ends_at' => now()->addDays(14),
            'subscription_status' => 'trial',
            'is_active' => true,
            'settings' => [
                'locale' => 'de',
                'timezone' => 'Europe/Berlin',
                'date_format' => 'd.m.Y',
                'time_format' => 'H:i',
                'currency' => 'EUR',
            ],
        ]);

        // Super-Admin bekommt Level 3 (Voller Zugriff fuer Entwicklung)
        $superAdmin->assignRole('super_admin_level_3');

        // Partner dem Mandanten zuordnen
        $partner->update(['tenant_id' => $tenant->id]);

        // Partner-Rollen fuer diesen Mandanten erstellen
        RolesAndPermissionsSeeder::createTenantRoles($tenant->id);

        // Partner bekommt 'partner' Rolle unter eigenem Mandanten
        $partner->assignRole('partner');

        // --- Test-Mitarbeiter ---
        $employee = User::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Erika',
            'last_name' => 'Musterfrau',
            'email' => 'mitarbeiter@rosi.de',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'type' => 'employee',
            'is_active' => true,
            'locale' => 'de',
        ]);

        // Mitarbeiter bekommt 'mitarbeiter' Rolle unter Mandant
        $employee->assignRole('mitarbeiter');

        $this->command->info('Testdaten erstellt:');
        $this->command->info('  Super-Admin: admin@rosi.de / password');
        $this->command->info('  Partner:     partner@rosi.de / password');
        $this->command->info('  Mitarbeiter: mitarbeiter@rosi.de / password');
    }
}
