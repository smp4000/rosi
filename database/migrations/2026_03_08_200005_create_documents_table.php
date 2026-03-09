<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dokumente und Dokumentenvorlagen erstellen.
     */
    public function up(): void
    {
        // Dokumentenvorlagen
        Schema::create('document_templates', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('UUID v7');
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete()->comment('NULL = globale Systemvorlage');
            $table->string('type', 50)->comment('Dokumententyp');
            $table->string('category', 20)->default('hr')->comment('hr, operations, other');
            $table->string('name')->comment('Vorlagenname');
            $table->text('description')->nullable()->comment('Beschreibung der Vorlage');
            $table->longText('content')->comment('HTML/Blade Template mit Platzhaltern');
            $table->json('variables')->nullable()->comment('Verfuegbare Platzhalter mit Beschreibung');
            $table->longText('header_html')->nullable()->comment('Briefkopf');
            $table->longText('footer_html')->nullable()->comment('Fusszeile');
            $table->boolean('is_default')->default(false)->comment('Standard-Vorlage fuer diesen Typ');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'type']);
        });

        // Dokumente
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('UUID v7');
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete()->comment('Bezug zum Mitarbeiter');
            $table->foreignUuid('gas_station_id')->nullable()->constrained('gas_stations')->nullOnDelete()->comment('Bezug zur Tankstelle');
            $table->foreignUuid('template_id')->nullable()->constrained('document_templates')->nullOnDelete()->comment('Verwendete Vorlage');

            // --- Dokument ---
            $table->string('type', 50)->comment('employment_contract, termination, warning, etc.');
            $table->string('category', 20)->default('hr')->comment('hr, operations, other');
            $table->string('title')->comment('Dokumenttitel');
            $table->longText('content')->nullable()->comment('Generierter HTML-Inhalt');
            $table->string('file_path')->nullable()->comment('PDF-Dateipfad');
            $table->unsignedBigInteger('file_size')->nullable()->comment('Dateigroesse in Bytes');

            // --- Signatur ---
            $table->boolean('requires_signature')->default(false)->comment('Unterschrift erforderlich');
            $table->timestamp('signed_at')->nullable()->comment('Unterschrieben am');
            $table->longText('signature_data')->nullable()->comment('Signatur-Daten (Base64)');
            $table->string('signed_by_name')->nullable()->comment('Name des Unterzeichners');
            $table->string('signed_by_ip', 45)->nullable()->comment('IP bei Unterschrift');
            $table->timestamp('counter_signed_at')->nullable()->comment('Gegenzeichnung (Partner)');
            $table->longText('counter_signature_data')->nullable();

            // --- Versand ---
            $table->timestamp('sent_at')->nullable()->comment('Per E-Mail gesendet am');
            $table->string('sent_to_email')->nullable()->comment('Empfaenger-E-Mail');

            // --- Status ---
            $table->string('status', 30)->default('draft')->comment('draft, pending_signature, sent, signed, counter_signed, archived, revoked');
            $table->unsignedInteger('version')->default(1)->comment('Dokumentversion');
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete()->comment('Erstellt von');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_templates');
    }
};
