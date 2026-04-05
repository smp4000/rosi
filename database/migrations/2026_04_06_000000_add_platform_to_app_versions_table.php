<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_versions', function (Blueprint $table) {
            $table->string('platform', 10)->default('app')->after('id')
                ->comment('app = POS-App, web = Web-Dashboard');
            $table->dropUnique(['version']);
            $table->unique(['platform', 'version']);
        });

        // Bestehende Eintraege als 'app' markieren
        \DB::table('app_versions')->whereNull('platform')->update(['platform' => 'app']);
    }

    public function down(): void
    {
        Schema::table('app_versions', function (Blueprint $table) {
            $table->dropUnique(['platform', 'version']);
            $table->unique('version');
            $table->dropColumn('platform');
        });
    }
};
