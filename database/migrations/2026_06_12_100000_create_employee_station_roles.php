<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rollenbasiertes Sicherheitssystem:
     * - roles.is_system: System-Rollen sind anpassbar, aber nicht loeschbar
     * - employee_station_roles: Rolle eines Mitarbeiters PRO Tankstelle
     *   (gas_station_id NULL = Rolle gilt im ganzen Betrieb)
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)
                ->after('guard_name')
                ->comment('System-Rolle: anpassbar, nicht loeschbar');
        });

        Schema::create('employee_station_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('tenant_id')->comment('Mandant');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('user_id')->comment('Mitarbeiter');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // Spatie-Rolle (roles.id ist BIGINT)
            $table->unsignedBigInteger('role_id');
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();

            // NULL = gilt an allen Tankstellen des Betriebs
            $table->uuid('gas_station_id')->nullable()->comment('NULL = ganzer Betrieb');
            $table->foreign('gas_station_id')->references('id')->on('gas_stations')->cascadeOnDelete();

            $table->timestamps();

            // Ein Mitarbeiter kann dieselbe Rolle nicht doppelt an derselben Station haben
            $table->unique(['user_id', 'role_id', 'gas_station_id'], 'esr_unique');
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_station_roles');

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });
    }
};
