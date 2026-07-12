<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A-2 Daten-Patch: voucher_redemptions.station_id enthielt faelschlich die
 * tenant_id (Bug in VoucherController::redeem). Korrektur auf die Station des
 * jeweiligen Gutscheins.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            UPDATE voucher_redemptions vr
            JOIN vouchers v ON vr.voucher_id = v.id
            SET vr.station_id = v.station_id
            WHERE v.station_id IS NOT NULL
              AND (vr.station_id IS NULL OR vr.station_id <> v.station_id)
        ');
    }

    public function down(): void
    {
        // Keine Rueckabwicklung (Datenkorrektur).
    }
};
