<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * MDE-Anmeldung direkt an der Station:
     * - Jede Tankstelle bekommt einen permanenten Setup-Token (QR-Code an der Kasse).
     * - Geraete koennen "wartend" sein, wenn die GPS-Pruefung fehlschlaegt
     *   (Freigabe dann manuell im Dashboard).
     */
    public function up(): void
    {
        Schema::table('gas_stations', function (Blueprint $table) {
            // Permanenter Code fuer die Geraete-Anmeldung an dieser Station
            $table->string('device_setup_token', 16)->nullable()->unique()
                ->after('longitude')
                ->comment('Permanenter Setup-Code fuer MDE-Anmeldung (QR an der Kasse)');
        });

        Schema::table('devices', function (Blueprint $table) {
            // active = darf arbeiten, pending = wartet auf Freigabe (GPS-Abweichung)
            $table->string('approval_status', 20)->default('active')
                ->after('is_active')
                ->comment('active | pending | rejected');

            // GPS-Daten der Registrierung (fuer die Freigabe-Entscheidung im Dashboard)
            $table->unsignedInteger('registration_distance_m')->nullable()
                ->after('approval_status')
                ->comment('Distanz zur Station bei Registrierung in Metern (null = kein GPS)');
            $table->decimal('registration_latitude', 11, 8)->nullable()
                ->after('registration_distance_m');
            $table->decimal('registration_longitude', 11, 8)->nullable()
                ->after('registration_latitude');
        });

        // Bestehende Stationen bekommen sofort einen Setup-Token
        DB::table('gas_stations')->whereNull('device_setup_token')
            ->pluck('id')
            ->each(function ($id) {
                DB::table('gas_stations')->where('id', $id)->update([
                    'device_setup_token' => strtoupper(Str::random(12)),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('gas_stations', function (Blueprint $table) {
            $table->dropColumn('device_setup_token');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn([
                'approval_status',
                'registration_distance_m',
                'registration_latitude',
                'registration_longitude',
            ]);
        });
    }
};
