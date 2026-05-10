<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Lieferanten + Pivot werden zu allgemeinen Stammdaten:
 * - newspaper_suppliers -> suppliers
 * - newspaper_supplier_stations -> supplier_stations
 *
 * FK von newspaper_invoices.supplier_id zeigt automatisch auf
 * die umbenannte Tabelle (MySQL behandelt RENAME als atomar).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('newspaper_suppliers') && ! Schema::hasTable('suppliers')) {
            Schema::rename('newspaper_suppliers', 'suppliers');
        }
        if (Schema::hasTable('newspaper_supplier_stations') && ! Schema::hasTable('supplier_stations')) {
            Schema::rename('newspaper_supplier_stations', 'supplier_stations');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('supplier_stations') && ! Schema::hasTable('newspaper_supplier_stations')) {
            Schema::rename('supplier_stations', 'newspaper_supplier_stations');
        }
        if (Schema::hasTable('suppliers') && ! Schema::hasTable('newspaper_suppliers')) {
            Schema::rename('suppliers', 'newspaper_suppliers');
        }
    }
};
