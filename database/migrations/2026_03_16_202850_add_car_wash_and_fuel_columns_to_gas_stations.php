<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Neue JSON-Spalten fuer Waschanlage-Details, Kraftstoffsorten und Zusatzgeschaefte.
     */
    public function up(): void
    {
        Schema::table('gas_stations', function (Blueprint $table) {
            $table->json('car_wash_details')->nullable()->after('has_car_wash');
            $table->json('fuel_types')->nullable()->after('services');
            $table->json('additional_businesses')->nullable()->after('fuel_types');
        });
    }

    public function down(): void
    {
        Schema::table('gas_stations', function (Blueprint $table) {
            $table->dropColumn(['car_wash_details', 'fuel_types', 'additional_businesses']);
        });
    }
};
