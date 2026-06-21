<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FCM-Push-Token pro Geraet (fuer App-Push-Benachrichtigungen, z.B. Temperatur-Stoerungen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('push_token', 255)->nullable()->after('app_version');
            $table->timestamp('push_token_updated_at')->nullable()->after('push_token');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['push_token', 'push_token_updated_at']);
        });
    }
};
