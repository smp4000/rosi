<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix: tenant_id verweist auf tenants-Tabelle, nicht gas_stations.
 * station_id wird nullable (Gutscheine koennen ohne Station-Zuordnung existieren).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            // tenant_id: FK von gas_stations auf tenants umbiegen
            $table->dropForeign(['tenant_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            // station_id: nullable machen + FK droppen (optional zuweisen)
            $table->dropForeign(['station_id']);
            $table->uuid('station_id')->nullable()->change();
            $table->foreign('station_id')->references('id')->on('gas_stations')->nullOnDelete();
        });

        Schema::table('voucher_redemptions', function (Blueprint $table) {
            // station_id auch nullable
            $table->dropForeign(['station_id']);
            $table->uuid('station_id')->nullable()->change();
            $table->foreign('station_id')->references('id')->on('gas_stations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->foreign('tenant_id')->references('id')->on('gas_stations')->cascadeOnDelete();
            $table->dropForeign(['station_id']);
            $table->uuid('station_id')->nullable(false)->change();
            $table->foreign('station_id')->references('id')->on('gas_stations')->cascadeOnDelete();
        });

        Schema::table('voucher_redemptions', function (Blueprint $table) {
            $table->dropForeign(['station_id']);
            $table->uuid('station_id')->nullable(false)->change();
            $table->foreign('station_id')->references('id')->on('gas_stations')->cascadeOnDelete();
        });
    }
};
