<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Systemweite Einstellungen (Admin-Bereich).
     * Unabhaengig von Tenants — fuer Admin-SMTP, API-Keys, etc.
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('group', 50)->default('general')->comment('Einstellungsgruppe');
            $table->string('key', 100)->comment('Einstellungs-Schluessel');
            $table->text('value')->nullable()->comment('Wert (ggf. verschluesselt)');
            $table->boolean('is_encrypted')->default(false)->comment('Wert verschluesselt?');
            $table->timestamps();

            $table->unique(['group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
