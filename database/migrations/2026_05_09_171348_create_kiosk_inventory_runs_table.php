<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_inventory_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('gas_station_id')->nullable()->constrained('gas_stations')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('bezeichnung')->nullable();
            $table->enum('modus', ['full', 'partial'])->default('partial');
            $table->integer('stufe')->default(1);
            $table->string('mitarbeiter', 100)->nullable();
            $table->enum('status', ['open', 'closed'])->default('closed');
            $table->text('notiz')->nullable();
            $table->dateTime('scanned_at')->useCurrent();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('kiosk_inventory_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('inventory_run_id')->constrained('kiosk_inventory_runs')->cascadeOnDelete();
            $table->foreignUuid('article_id')->constrained('kiosk_articles')->cascadeOnDelete();
            $table->string('ausgabe', 10)->nullable();
            $table->integer('menge');
            $table->decimal('einzelpreis_brutto', 10, 4)->default(0);
            $table->decimal('mwst_satz', 5, 2)->default(0);
            $table->string('scanned_ean', 20)->nullable();
            $table->dateTime('scanned_at')->useCurrent();

            $table->index('inventory_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_inventory_items');
        Schema::dropIfExists('kiosk_inventory_runs');
    }
};
