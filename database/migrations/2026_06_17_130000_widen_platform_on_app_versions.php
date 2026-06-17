<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * platform war VARCHAR(10) — zu kurz fuer "print-agent" (11 Zeichen).
 * Auf 30 verbreitern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_versions', function (Blueprint $table) {
            $table->string('platform', 30)->default('app')->change();
        });
    }

    public function down(): void
    {
        Schema::table('app_versions', function (Blueprint $table) {
            $table->string('platform', 10)->default('app')->change();
        });
    }
};
