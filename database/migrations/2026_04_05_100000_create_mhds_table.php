<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabelle existiert bereits in der Datenbank (manuell angelegt).
        // Diese Migration dient nur der Dokumentation und wird uebersprungen,
        // falls die Tabelle schon vorhanden ist.
        if (Schema::hasTable('mhds')) {
            return;
        }

        Schema::create('mhds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('station_id');
            $table->date('date_recorded');
            $table->unsignedBigInteger('tms_no');
            $table->string('ean')->nullable();
            $table->string('article_description');
            $table->string('supplier')->nullable();
            $table->date('delivery_date')->nullable();
            $table->date('mhd_date');
            $table->string('kenner')->unique();
            $table->boolean('goods_disposed')->default(false);
            $table->integer('disposed_quantity')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['station_id', 'mhd_date']);
            $table->index('kenner');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mhds');
    }
};
