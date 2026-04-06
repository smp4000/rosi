<?php

namespace Database\Seeders;

use App\Models\GasStation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
        $this->call(AppVersionSeeder::class);
        $this->call(ArticleGroupSeeder::class);

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
            'name' => 'Christian Welle Tankstekllen',
            'slug' => 'christian-welle-tankstekllen',
            'owner_id' => $partner->id,
            'email' => 'partner@rosi.de',
            'street' => 'Petersberger Str. 101',
            'zip' => '36100',
            'city' => 'Petersberg',
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
            'pin_hash' => Hash::make('1234'),
            'scan_code' => strtoupper(Str::random(12)),
            'email_verified_at' => now(),
            'type' => 'employee',
            'is_active' => true,
            'locale' => 'de',
        ]);

        // Mitarbeiter bekommt 'mitarbeiter' Rolle unter Mandant
        $employee->assignRole('mitarbeiter');

        // --- Reale Benutzer: Familie Welle ---
        $christianWelle = User::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Christian',
            'last_name' => 'Welle',
            'email' => 'sv.welle@aral-welle.de',            'password' => Hash::make('password'),
            'pin_hash' => Hash::make('1234'),
            'scan_code' => strtoupper(Str::random(12)),
            'email_verified_at' => now(),
            'type' => 'partner',
            'is_active' => true,
            'locale' => 'de',
        ]);
        $christianWelle->assignRole('partner');

        $laraSophieWelle = User::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Lara Sophie',
            'last_name' => 'Welle',
            'email' => 'smp4000@me.com',
            'password' => Hash::make('password'),
            'pin_hash' => Hash::make('1234'),
            'scan_code' => strtoupper(Str::random(12)),
            'email_verified_at' => now(),
            'type' => 'employee',
            'is_active' => true,
            'locale' => 'de',
        ]);
        $laraSophieWelle->assignRole('mitarbeiter');

        $alexandraWelle = User::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Alexandra',
            'last_name' => 'Welle',
            'email' => 'monor5000@gmail.com',
            'password' => Hash::make('password'),
            'pin_hash' => Hash::make('1234'),
            'scan_code' => strtoupper(Str::random(12)),
            'email_verified_at' => now(),
            'type' => 'employee',
            'is_active' => true,
            'locale' => 'de',
        ]);
        $alexandraWelle->assignRole('mitarbeiter');

        // Tankstellen nach Tenant-Erstellung seeden
        $this->call(GasStationSeeder::class);

        // Alle Welle-Mitarbeiter beiden Tankstellen zuordnen
        $stations = GasStation::where('tenant_id', $tenant->id)->get();

        foreach ([$christianWelle, $laraSophieWelle, $alexandraWelle] as $user) {
            foreach ($stations as $station) {
                $user->gasStations()->attach($station->id, [
                    'station_role' => $user->type === 'partner' ? 'manager' : 'employee',
                    'is_primary' => true,
                    'assigned_at' => now(),
                ]);
            }
        }

        $this->command->info('Testdaten erstellt:');
        $this->command->info('  Super-Admin:    admin@rosi.de / password');
        $this->command->info('  Partner (Test): partner@rosi.de / password');
        $this->command->info('  Mitarbeiter:    mitarbeiter@rosi.de / password');
        $this->command->info('  ---');
        $this->command->info('  Partner:        Christian.welle@live.de / password');
        $this->command->info('  Mitarbeiterin:  smp4000@me.com / password');
        $this->command->info('  Mitarbeiterin:  monor5000@gmail.com / password');
    }
}
