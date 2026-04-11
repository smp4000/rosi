<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')
                ->nullable()
                ->constrained('gas_stations')
                ->nullOnDelete()
                ->comment('NULL = globales Default-Template');
            $table->string('slug', 50)->comment('z.B. tankbetrug, testdruck, adresse');
            $table->string('name')->comment('Anzeigename');
            $table->text('xml_template')->comment('DYMO Label-XML mit {{platzhalter}}');
            $table->json('placeholders')->nullable()->comment('Verfuegbare Platzhalter mit Beschreibung');
            $table->decimal('width', 5, 2)->default(3.98)->comment('Breite in Zoll');
            $table->decimal('height', 5, 2)->default(2.13)->comment('Hoehe in Zoll');
            $table->string('orientation', 20)->default('Portrait');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_templates');
    }
};
