<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Country-Spalte vergroessern: War string(2) fuer ISO-Codes,
 * braucht aber mehr Platz da der Onboarding-Wizard Freitext erlaubt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->string('country', 100)->default('DE')->change();
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->string('country', 2)->default('DE')->change();
        });
    }
};
