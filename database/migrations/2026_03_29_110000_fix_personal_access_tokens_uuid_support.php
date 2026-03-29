<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * tokenable_id war bigint (morphs), muss aber UUID sein (uuidMorphs),
     * weil das User-Model HasUuids nutzt.
     */
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            // Alten Index + Spalten entfernen
            $table->dropMorphs('tokenable');
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            // Neue UUID-kompatible Spalten erstellen
            $table->uuidMorphs('tokenable');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropMorphs('tokenable');
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->morphs('tokenable');
        });
    }
};
