<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versand-Protokoll fuer E-Mails und Druck.
 * Jeder Versand-Versuch wird als eigener Eintrag gespeichert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignUuid('batch_id')->nullable()->constrained('invoice_batches')->nullOnDelete();
            $table->string('channel', 10)->comment('email oder print');
            $table->string('status', 20)->comment('sent, failed, printed');
            $table->string('recipient', 255)->nullable()->comment('E-Mail-Adresse bei Email-Versand');
            $table->text('error_message')->nullable()->comment('Fehlermeldung bei failed');
            $table->json('metadata')->nullable()->comment('Zusaetzliche Daten');
            $table->timestamps();

            $table->index(['tenant_id', 'channel', 'status']);
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_logs');
    }
};
