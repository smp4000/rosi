<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_versions', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('UUID v7');
            $table->string('version', 20)->comment('z.B. 1.0.0, 1.1.0');
            $table->date('release_date')->comment('Veroeffentlichungsdatum');
            $table->json('changes')->comment('Liste der Aenderungen als JSON-Array');
            $table->boolean('is_published')->default(true)->comment('Sichtbar in der App');
            $table->timestamps();

            $table->unique('version');
            $table->index('release_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};
