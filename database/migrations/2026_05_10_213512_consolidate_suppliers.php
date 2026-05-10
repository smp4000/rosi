<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konsolidiert Lieferanten:
 * - Loescht die parallele newspaper_suppliers Tabelle (war leer)
 * - supplier_stations.supplier_id zeigt jetzt auf die allgemeine
 *   suppliers Tabelle
 * - newspaper_invoices.supplier_id ebenfalls
 * - short_code-Spalte zu suppliers hinzufuegen
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. FK von supplier_stations.supplier_id auf newspaper_suppliers droppen
        //    (FK heisst noch newspaper_supplier_stations_supplier_id_foreign weil
        //    Tabelle umbenannt wurde aber Constraint-Name beibehalten ist)
        Schema::table('supplier_stations', function (Blueprint $table) {
            $table->dropForeign('newspaper_supplier_stations_supplier_id_foreign');
        });

        Schema::table('newspaper_invoices', function (Blueprint $table) {
            $table->dropForeign('newspaper_invoices_supplier_id_foreign');
        });

        // 3. newspaper_suppliers droppen (war ohnehin leer)
        Schema::dropIfExists('newspaper_suppliers');

        // 4. short_code zu suppliers hinzufuegen wenn nicht vorhanden
        if (! Schema::hasColumn('suppliers', 'short_code')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->string('short_code', 20)->nullable()->after('supplier_number');
                $table->index(['tenant_id', 'short_code']);
            });
        }

        // 5. is_active zu suppliers hinzufuegen wenn nicht vorhanden
        if (! Schema::hasColumn('suppliers', 'is_active')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('short_code');
            });
        }

        // 6. FKs auf allgemeine suppliers Tabelle setzen
        Schema::table('supplier_stations', function (Blueprint $table) {
            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
        });

        Schema::table('newspaper_invoices', function (Blueprint $table) {
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Zurueck zu newspaper_suppliers (nur Schema, Daten gehen verloren)
        Schema::table('newspaper_invoices', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
        });
        Schema::table('supplier_stations', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
        });

        Schema::create('newspaper_suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('short_code', 20)->nullable();
            $table->string('vat_id', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
