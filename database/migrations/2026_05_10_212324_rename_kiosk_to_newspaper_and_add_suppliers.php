<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Komplettes Refactoring:
 * 1. Alle kiosk_* Tabellen werden in newspaper_* umbenannt
 * 2. Neue Tabellen: newspaper_suppliers + newspaper_supplier_stations (Pivot)
 * 3. gas_station_id zu newspaper_invoices (Auflosung ueber Kundennummer)
 *
 * Daten bleiben erhalten (RENAME TABLE).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabellen umbenennen
        $renames = [
            'kiosk_articles' => 'newspaper_articles',
            'kiosk_article_issues' => 'newspaper_article_issues',
            'kiosk_invoices' => 'newspaper_invoices',
            'kiosk_order_lines' => 'newspaper_order_lines',
            'kiosk_price_change_log' => 'newspaper_price_change_log',
            'kiosk_imports' => 'newspaper_imports',
            'kiosk_deliveries' => 'newspaper_deliveries',
            'kiosk_delivery_items' => 'newspaper_delivery_items',
            'kiosk_remi_packages' => 'newspaper_remi_packages',
            'kiosk_remi_items' => 'newspaper_remi_items',
            'kiosk_inventory_runs' => 'newspaper_inventory_runs',
            'kiosk_inventory_items' => 'newspaper_inventory_items',
        ];
        foreach ($renames as $old => $new) {
            if (Schema::hasTable($old) && ! Schema::hasTable($new)) {
                Schema::rename($old, $new);
            }
        }

        // 2. Lieferanten-Stamm
        if (! Schema::hasTable('newspaper_suppliers')) {
            Schema::create('newspaper_suppliers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('short_code', 20)->nullable()->comment('z.B. PVG, PressData');
                $table->string('vat_id', 30)->nullable();
                $table->string('email', 150)->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('address')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'is_active']);
                $table->unique(['tenant_id', 'short_code']);
            });
        }

        // 3. Lieferant <-> Tankstelle Pivot mit Kundennummer
        if (! Schema::hasTable('newspaper_supplier_stations')) {
            Schema::create('newspaper_supplier_stations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('supplier_id')->constrained('newspaper_suppliers')->cascadeOnDelete();
                $table->foreignUuid('gas_station_id')->constrained('gas_stations')->cascadeOnDelete();
                $table->string('kundennummer', 50)->comment('Beim Lieferanten hinterlegte Kunden-Nr');
                $table->timestamps();

                $table->unique(['supplier_id', 'gas_station_id'], 'uq_supplier_station');
                $table->unique(['supplier_id', 'kundennummer'], 'uq_supplier_kundennummer');
                $table->index('kundennummer');
            });
        }

        // 4. Spalten zu newspaper_invoices: supplier_id + gas_station_id
        Schema::table('newspaper_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('newspaper_invoices', 'supplier_id')) {
                $table->foreignUuid('supplier_id')->nullable()->after('tenant_id')
                    ->constrained('newspaper_suppliers')->nullOnDelete();
            }
            if (! Schema::hasColumn('newspaper_invoices', 'gas_station_id')) {
                $table->foreignUuid('gas_station_id')->nullable()->after('supplier_id')
                    ->constrained('gas_stations')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        // Foreign Keys + Spalten bei newspaper_invoices weg
        Schema::table('newspaper_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('newspaper_invoices', 'gas_station_id')) {
                $table->dropConstrainedForeignId('gas_station_id');
            }
            if (Schema::hasColumn('newspaper_invoices', 'supplier_id')) {
                $table->dropConstrainedForeignId('supplier_id');
            }
        });

        Schema::dropIfExists('newspaper_supplier_stations');
        Schema::dropIfExists('newspaper_suppliers');

        $renames = [
            'newspaper_articles' => 'kiosk_articles',
            'newspaper_article_issues' => 'kiosk_article_issues',
            'newspaper_invoices' => 'kiosk_invoices',
            'newspaper_order_lines' => 'kiosk_order_lines',
            'newspaper_price_change_log' => 'kiosk_price_change_log',
            'newspaper_imports' => 'kiosk_imports',
            'newspaper_deliveries' => 'kiosk_deliveries',
            'newspaper_delivery_items' => 'kiosk_delivery_items',
            'newspaper_remi_packages' => 'kiosk_remi_packages',
            'newspaper_remi_items' => 'kiosk_remi_items',
            'newspaper_inventory_runs' => 'kiosk_inventory_runs',
            'newspaper_inventory_items' => 'kiosk_inventory_items',
        ];
        foreach ($renames as $old => $new) {
            if (Schema::hasTable($old) && ! Schema::hasTable($new)) {
                Schema::rename($old, $new);
            }
        }
    }
};
