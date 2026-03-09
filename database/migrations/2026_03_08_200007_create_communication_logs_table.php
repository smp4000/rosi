<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CRM-Kommunikationshistorie erstellen.
     */
    public function up(): void
    {
        Schema::create('communication_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            // Polymorph: Customer oder Supplier
            $table->string('communicable_type')->comment('App\\Models\\Customer oder App\\Models\\Supplier');
            $table->char('communicable_id', 36)->comment('UUID des Kunden/Lieferanten');
            $table->string('type', 20)->comment('email, call, note, meeting, task');
            $table->string('direction', 20)->default('internal')->comment('incoming, outgoing, internal');
            $table->string('subject')->nullable()->comment('Betreff');
            $table->text('content')->comment('Inhalt/Notiz');
            $table->json('attachments')->nullable()->comment('Dateianhaenge');
            $table->timestamp('scheduled_at')->nullable()->comment('Geplanter Termin');
            $table->timestamp('completed_at')->nullable()->comment('Erledigt am');
            $table->timestamp('reminder_at')->nullable()->comment('Erinnerung');
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['communicable_type', 'communicable_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_logs');
    }
};
