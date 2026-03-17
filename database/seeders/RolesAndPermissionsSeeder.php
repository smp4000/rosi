<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Erstellt die Super-Admin Zugriffsstufen (Level 1-3) als spatie-Rollen.
 * Jede Stufe ist kumulativ (Level 3 enthaelt alle Permissions von Level 1+2).
 *
 * Globale (Super-Admin) Rollen nutzen GLOBAL_TEAM_ID als tenant_id,
 * da die PK-Constraint in model_has_roles kein NULL erlaubt.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Spezielle Team-ID fuer globale (Super-Admin) Rollen/Permissions.
     * Wird benoetigt, weil tenant_id Teil des Composite Primary Key ist.
     */
    public const GLOBAL_TEAM_ID = '00000000-0000-0000-0000-000000000000';

    public function run(): void
    {
        // Cache zuruecksetzen
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Globale Permissions (Zero-UUID als Team-ID fuer Super-Admin)
        app(PermissionRegistrar::class)->setPermissionsTeamId(self::GLOBAL_TEAM_ID);

        // --- Alle Admin-Permissions erstellen ---
        $permissions = [
            // Level 1 - Standard
            'admin.dashboard.view',
            'admin.tenants.list',
            'admin.tenants.view-stats',
            'admin.system.view',

            // Level 2 - Support
            'admin.tenants.view',
            'admin.tenants.edit',
            'admin.users.list',
            'admin.users.view',
            'admin.audit-logs.view',

            // Level 3 - Notfall
            'admin.tenants.create',
            'admin.tenants.delete',
            'admin.users.edit',
            'admin.users.create',
            'admin.audit-logs.view-values',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Cache nach Permission-Erstellung erneut leeren
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // --- Rollen erstellen (kumulativ) ---

        // Level 1: Standard - Uebersicht und Statistiken
        $level1 = Role::findOrCreate('super_admin_level_1', 'web');
        $level1->syncPermissions([
            'admin.dashboard.view',
            'admin.tenants.list',
            'admin.tenants.view-stats',
            'admin.system.view',
        ]);

        // Level 2: Support - Level 1 + Stammdaten & Benutzer
        $level2 = Role::findOrCreate('super_admin_level_2', 'web');
        $level2->syncPermissions([
            // Level 1
            'admin.dashboard.view',
            'admin.tenants.list',
            'admin.tenants.view-stats',
            'admin.system.view',
            // Level 2
            'admin.tenants.view',
            'admin.tenants.edit',
            'admin.users.list',
            'admin.users.view',
            'admin.audit-logs.view',
        ]);

        // Level 3: Notfall - Voller Zugriff (erfordert 2FA + Begruendung)
        $level3 = Role::findOrCreate('super_admin_level_3', 'web');
        $level3->syncPermissions($permissions);

        // --- Partner-Permissions erstellen (global, Rollen werden pro Mandant erstellt) ---
        $partnerPermissions = self::getPartnerPermissions();

        foreach ($partnerPermissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Cache erneut leeren nach Partner-Permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('Rollen und Permissions erstellt:');
        $this->command->info('  Level 1 (Standard): ' . $level1->permissions->count() . ' Permissions');
        $this->command->info('  Level 2 (Support):  ' . $level2->permissions->count() . ' Permissions');
        $this->command->info('  Level 3 (Notfall):  ' . $level3->permissions->count() . ' Permissions');
        $this->command->info('  Partner-Permissions: ' . count($partnerPermissions) . ' (Rollen pro Mandant)');
    }

    /**
     * Liste aller Partner-Permissions.
     */
    public static function getPartnerPermissions(): array
    {
        return [
            'partner.dashboard.view',
            'partner.gas-stations.list',
            'partner.gas-stations.view',
            'partner.gas-stations.create',
            'partner.gas-stations.edit',
            'partner.gas-stations.delete',
            'partner.employees.list',
            'partner.employees.view',
            'partner.employees.create',
            'partner.employees.edit',
            'partner.employees.invite',
            'partner.invitations.list',
            'partner.invitations.create',
            'partner.invitations.delete',
            'partner.customers.list',
            'partner.customers.view',
            'partner.customers.create',
            'partner.customers.edit',
            'partner.documents.list',
            'partner.documents.view',
            'partner.documents.create',
            'partner.documents.edit',
            'partner.documents.delete',
            'partner.documents.sign',
            'partner.documents.send',
            'partner.templates.list',
            'partner.templates.view',
            'partner.templates.create',
            'partner.templates.edit',
            'partner.templates.delete',
        ];
    }

    /**
     * Erstellt die Partner-Rollen fuer einen spezifischen Mandanten.
     * Muss unter dem Team-Scope des Mandanten aufgerufen werden.
     */
    public static function createTenantRoles(string $tenantId): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $allPartner = self::getPartnerPermissions();

        // Partner: Voller Zugriff
        $partnerRole = Role::findOrCreate('partner', 'web');
        $partnerRole->syncPermissions($allPartner);

        // Stationsleiter: Tankstellen lesen, Mitarbeiter verwalten, Kunden CRUD, Dokumente
        $stationsleiterRole = Role::findOrCreate('stationsleiter', 'web');
        $stationsleiterRole->syncPermissions([
            'partner.dashboard.view',
            'partner.gas-stations.list',
            'partner.gas-stations.view',
            'partner.employees.list',
            'partner.employees.view',
            'partner.employees.invite',
            'partner.invitations.list',
            'partner.invitations.create',
            'partner.customers.list',
            'partner.customers.view',
            'partner.customers.create',
            'partner.customers.edit',
            'partner.documents.list',
            'partner.documents.view',
            'partner.documents.create',
            'partner.documents.send',
            'partner.templates.list',
            'partner.templates.view',
        ]);

        // Mitarbeiter: Nur lesen
        $mitarbeiterRole = Role::findOrCreate('mitarbeiter', 'web');
        $mitarbeiterRole->syncPermissions([
            'partner.dashboard.view',
            'partner.gas-stations.list',
            'partner.gas-stations.view',
            'partner.customers.list',
            'partner.customers.view',
            'partner.documents.list',
            'partner.documents.view',
        ]);
    }
}
