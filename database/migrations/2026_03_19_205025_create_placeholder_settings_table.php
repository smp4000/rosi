<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('placeholder_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete()
                ->comment('Mandant dem dieser Platzhalter gehoert');
            $table->string('key', 100)->comment('Platzhalter-Schluessel, z.B. geschaeftsfuehrer_name');
            $table->string('label')->comment('Anzeigename, z.B. Name des Geschaeftsfuehrers');
            $table->text('value')->nullable()->comment('Der eingesetzte Wert');
            $table->string('group', 50)->default('custom')->comment('Gruppe: company, legal, custom');
            $table->string('type', 20)->default('text')->comment('Feldtyp: text, textarea, date, number');
            $table->text('description')->nullable()->comment('Hilfetext fuer den Benutzer');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_system')->default(false)->comment('System-Platzhalter (nicht loeschbar)');
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
            $table->index(['tenant_id', 'group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('placeholder_settings');
    }
};
