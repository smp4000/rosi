<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-Connect fuer den ROSI-Print-Agent:
 *  - print_agents: install_id (Maschinen-GUID), hostname, status, claim_secret;
 *    tenant_id/station_id werden NULLBAR (Self-Register: noch keine Station).
 *  - gas_stations: enrollment_token fuer den Stations-Installer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_agents', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropForeign(['station_id']);
        });

        Schema::table('print_agents', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->change();
            $table->uuid('station_id')->nullable()->change();

            $table->string('install_id')->nullable()->after('id')->comment('Windows MachineGuid - ein PC = ein Agent');
            $table->string('hostname')->nullable()->after('name');
            $table->string('status')->default('active')->after('is_active')
                ->comment('pending | active | blocked');
            $table->string('claim_secret', 64)->nullable()->comment('Self-Register: Geheimnis zum Token-Abholen');

            $table->index('install_id');

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('station_id')->references('id')->on('gas_stations')->cascadeOnDelete();
        });

        Schema::table('gas_stations', function (Blueprint $table) {
            $table->string('enrollment_token', 64)->nullable()->unique()
                ->comment('Token fuer den Stations-Installer des Print-Agents');
        });
    }

    public function down(): void
    {
        Schema::table('print_agents', function (Blueprint $table) {
            $table->dropIndex(['install_id']);
            $table->dropColumn(['install_id', 'hostname', 'status', 'claim_secret']);
        });

        Schema::table('gas_stations', function (Blueprint $table) {
            $table->dropUnique(['enrollment_token']);
            $table->dropColumn('enrollment_token');
        });
    }
};
