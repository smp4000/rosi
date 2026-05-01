<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Warenruecknahme-Gruende fuer Schichtabrechnungen.
 * Global (tenant_id=null) oder tenant-spezifisch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_return_reasons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('label')->comment('Bezeichnung des Grundes');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_return_reasons');
    }
};
