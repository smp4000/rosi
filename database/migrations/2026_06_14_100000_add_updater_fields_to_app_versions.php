<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * In-App-Updater: app_versions um die Felder fuer den APK-Download erweitern.
     * - version_code: technische Versionsnummer (Android versionCode) zum Vergleich
     * - apk_path:     Pfad zur hochgeladenen APK in storage/app/public
     * - apk_size:     Dateigroesse in Bytes (Anzeige im Download-Dialog)
     * - is_mandatory: Pflicht-Update (App erzwingt die Installation, kein "Spaeter")
     */
    public function up(): void
    {
        Schema::table('app_versions', function (Blueprint $table) {
            $table->unsignedInteger('version_code')->nullable()->after('version')
                ->comment('Android versionCode zum Vergleich (z.B. 12)');
            $table->string('apk_path')->nullable()->after('version_code')
                ->comment('Pfad zur APK in storage/app/public');
            $table->unsignedBigInteger('apk_size')->nullable()->after('apk_path')
                ->comment('APK-Groesse in Bytes');
            $table->boolean('is_mandatory')->default(false)->after('is_published')
                ->comment('Pflicht-Update: App erzwingt die Installation');
        });
    }

    public function down(): void
    {
        Schema::table('app_versions', function (Blueprint $table) {
            $table->dropColumn(['version_code', 'apk_path', 'apk_size', 'is_mandatory']);
        });
    }
};
