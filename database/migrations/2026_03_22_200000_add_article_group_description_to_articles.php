<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('article_group_description', 255)
                ->nullable()
                ->after('article_group_id')
                ->comment('Artikelgruppen-Bezeichnung aus CSV (denormalisiert)');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('article_group_description');
        });
    }
};
