<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generische Lookup-Tabelle fuer Dropdowns mit "Neu anlegen"-Funktion.
     * Kategorien: car_wash_brand, car_wash_type
     */
    public function up(): void
    {
        Schema::create('lookup_values', function (Blueprint $table) {
            $table->id();
            $table->string('category', 50)->index();
            $table->string('value', 255);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['category', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lookup_values');
    }
};
