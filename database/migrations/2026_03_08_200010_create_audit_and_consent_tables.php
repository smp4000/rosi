<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DSGVO-Tabellen: Audit-Logs, Einwilligungen, Loeschantraege erstellen.
     */
    public function up(): void
    {
        // Audit-Logs (DSGVO)
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->char('tenant_id', 36)->nullable()->index()->comment('Mandanten-Zuordnung');
            $table->char('user_id', 36)->nullable()->index()->comment('Handelnder Benutzer');
            $table->string('user_type', 50)->nullable()->comment('super_admin, partner, employee');
            $table->string('action', 30)->comment('create, read, update, delete, export, login, logout, permission_change');
            $table->string('auditable_type')->nullable()->comment('Polymorph: welches Model');
            $table->char('auditable_id', 36)->nullable()->comment('UUID des betroffenen Datensatzes');
            $table->text('old_values')->nullable()->comment('Alte Werte JSON (verschluesselt)');
            $table->text('new_values')->nullable()->comment('Neue Werte JSON (verschluesselt)');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->text('reason')->nullable()->comment('Begruendung (Pflicht bei Super-Admin Level 3)');
            $table->timestamp('created_at')->nullable();

            $table->index(['auditable_type', 'auditable_id']);
        });

        // Einwilligungs-Log (DSGVO)
        Schema::create('consent_logs', function (Blueprint $table) {
            $table->id();
            $table->char('user_id', 36)->index()->comment('UUID des Users');
            $table->char('tenant_id', 36)->nullable()->index();
            $table->string('type', 30)->comment('privacy_policy, terms, data_processing, cookies, marketing');
            $table->string('version', 20)->comment('Version der Erklaerung');
            $table->timestamp('accepted_at')->comment('Eingewilligt am');
            $table->timestamp('revoked_at')->nullable()->comment('Widerruf am');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // Loeschantraege (DSGVO)
        Schema::create('data_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->char('user_id', 36)->index()->comment('UUID des Antragstellers');
            $table->char('tenant_id', 36)->nullable()->index();
            $table->string('type', 30)->comment('full_deletion, data_export, rectification');
            $table->string('status', 20)->default('pending')->comment('pending, processing, completed, rejected');
            $table->text('description')->nullable()->comment('Beschreibung des Antrags');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->char('processed_by', 36)->nullable()->comment('UUID des Bearbeiters');
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_deletion_requests');
        Schema::dropIfExists('consent_logs');
        Schema::dropIfExists('audit_logs');
    }
};
