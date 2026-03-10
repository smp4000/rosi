<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Erstellt die brands-Tabelle (Tankstellen-Marken) und fuegt brand_id
 * als Fremdschluessel zur gas_stations-Tabelle hinzu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('logo')->nullable()->comment('Pfad zum Markenlogo');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Fremdschluessel auf gas_stations (ersetzt das alte brand-Textfeld)
        Schema::table('gas_stations', function (Blueprint $table) {
            $table->foreignId('brand_id')
                ->nullable()
                ->after('name')
                ->constrained('brands')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gas_stations', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropColumn('brand_id');
        });

        Schema::dropIfExists('brands');
    }
};
