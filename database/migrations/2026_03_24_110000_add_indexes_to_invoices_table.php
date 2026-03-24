<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance-Indizes fuer haeufig gefilterte Spalten in invoices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // status hat bereits einen Index (enum-Spalte)
            $table->index('import_status');
            $table->index('email_status');
            $table->index('print_status');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['import_status']);
            $table->dropIndex(['email_status']);
            $table->dropIndex(['print_status']);
        });
    }
};
