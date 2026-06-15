<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Druck-Agenten: die "ROSI Print"-Tray-App auf einem Stations-PC.
     * Authentifiziert sich per Token (sha256-Hash gespeichert), meldet die
     * lokal sichtbaren DYMO-Drucker und einen Heartbeat (last_seen_at).
     */
    public function up(): void
    {
        Schema::create('print_agents', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('station_id');
            $table->foreign('station_id')->references('id')->on('gas_stations')->cascadeOnDelete();

            $table->string('name')->comment('Anzeigename, z.B. "Kassen-PC"');
            $table->string('token_hash', 64)->unique()->comment('sha256 des Agent-Tokens');

            $table->json('printers')->nullable()->comment('Vom Agent gemeldete Drucker-Namen');
            $table->string('app_version')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['tenant_id', 'station_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_agents');
    }
};
