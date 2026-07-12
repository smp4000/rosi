<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ──────────────────────────────────────────────────────────────────────────
 *  T-2 (Sicherheits-Audit 07/2026): tenant_id fuer stations-gebundene Tabellen
 * ──────────────────────────────────────────────────────────────────────────
 *
 * PROBLEM vorher:
 *   Diese 9 Tabellen hatten NUR einen Stations-Bezug (gas_station_id bzw.
 *   station_id), aber KEINE tenant_id-Spalte. Der automatische Mandanten-
 *   filter (TenantScope) konnte dort also gar nicht greifen — die Isolation
 *   hing zu 100% daran, dass JEDE Query von Hand nach der Station filtert.
 *   Eine einzige vergessene Query = stille Cross-Tenant-Datenpanne.
 *
 * LOESUNG:
 *   1. tenant_id-Spalte (nullable, indiziert) ergaenzen.
 *   2. Bestand per JOIN ueber die Station befuellen (jede Station gehoert
 *      genau einem Mandanten). Vorab geprueft: alle Zeilen loesen sauber auf.
 *   3. Die zugehoerigen Models bekommen das BelongsToTenant-Trait
 *      → ab sofort filtert der TenantScope automatisch UND setzt die
 *      tenant_id beim Erstellen selbststaendig (Kontext/Session).
 *
 * nullable bewusst: Alt-Import-Werkzeuge, die tenant_id (noch) nicht setzen,
 * sollen nicht mit einem NOT-NULL-Fehler crashen. Der creating-Hook des
 * Traits fuellt die Spalte im Normalfall automatisch.
 */
return new class extends Migration
{
    /** Tabelle => Name der Stations-Spalte (historisch uneinheitlich). */
    private const TABLES = [
        'articles' => 'gas_station_id',
        'article_eans' => 'gas_station_id',
        'article_imports' => 'gas_station_id',
        'corporate_customers' => 'gas_station_id',
        'fuel_cards' => 'gas_station_id',
        'invoices' => 'gas_station_id',
        'invoice_batches' => 'gas_station_id',
        'mhds' => 'station_id',
        'voucher_redemptions' => 'station_id',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $stationColumn) {
            // 1) Spalte + Index anlegen (nur falls noch nicht vorhanden)
            if (! Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->uuid('tenant_id')->nullable()->index();
                });
            }

            // 2) Bestand befuellen: Mandant der zugehoerigen Station uebernehmen
            DB::statement("
                UPDATE {$table} x
                JOIN gas_stations g ON x.{$stationColumn} = g.id
                SET x.tenant_id = g.tenant_id
                WHERE x.tenant_id IS NULL
            ");
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            if (Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('tenant_id');
                });
            }
        }
    }
};
