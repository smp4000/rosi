<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('job_type', 50)->default('voucher_labels'); // z.B. voucher_labels, mhd_labels
            $table->string('reference', 100)->nullable(); // z.B. Gutschein-Gruppe "4567"
            $table->longText('payload'); // JSON mit Label-XMLs
            $table->string('status', 20)->default('pending'); // pending, printing, done, failed
            $table->string('created_by')->nullable(); // Mitarbeiter-Name
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};
