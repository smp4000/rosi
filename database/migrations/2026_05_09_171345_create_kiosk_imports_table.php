<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->nullable()->constrained('kiosk_invoices')->nullOnDelete();
            $table->string('filename')->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->enum('status', ['success', 'error', 'skipped']);
            $table->integer('articles_inserted')->default(0);
            $table->integer('articles_updated')->default(0);
            $table->integer('articles_skipped')->default(0);
            $table->text('error_message')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index('file_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_imports');
    }
};
