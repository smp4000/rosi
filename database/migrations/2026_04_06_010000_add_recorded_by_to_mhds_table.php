<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mhds', function (Blueprint $table) {
            $table->string('recorded_by')->nullable()->after('date_recorded')
                ->comment('Name des Mitarbeiters der den Eintrag erfasst hat');
        });
    }

    public function down(): void
    {
        Schema::table('mhds', function (Blueprint $table) {
            $table->dropColumn('recorded_by');
        });
    }
};
