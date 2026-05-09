<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('gas_station_id')->nullable()->constrained('gas_stations')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('lieferschein_nr', 50)->nullable();
            $table->date('lieferschein_datum')->nullable();
            $table->string('mitarbeiter', 100)->nullable();
            $table->enum('status', ['open', 'closed'])->default('closed');
            $table->text('notiz')->nullable();
            $table->dateTime('scanned_at')->useCurrent();
            $table->timestamps();

            $table->index(['tenant_id', 'lieferschein_datum']);
        });

        Schema::create('kiosk_delivery_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('delivery_id')->constrained('kiosk_deliveries')->cascadeOnDelete();
            $table->foreignUuid('article_id')->constrained('kiosk_articles')->cascadeOnDelete();
            $table->string('ausgabe', 10)->nullable();
            $table->integer('menge');
            $table->decimal('einzelpreis_brutto', 10, 4)->default(0);
            $table->decimal('mwst_satz', 5, 2)->default(0);
            $table->string('scanned_ean', 20)->nullable();
            $table->dateTime('scanned_at')->useCurrent();

            $table->index('delivery_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_delivery_items');
        Schema::dropIfExists('kiosk_deliveries');
    }
};
