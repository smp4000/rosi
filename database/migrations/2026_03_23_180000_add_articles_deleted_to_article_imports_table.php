<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spalte fuer geloeschte Artikel beim Loeschlisten-Import.
     */
    public function up(): void
    {
        Schema::table('article_imports', function (Blueprint $table) {
            $table->unsignedInteger('articles_deleted')->default(0)
                ->after('articles_unchanged')
                ->comment('Soft-deleted Artikel (Loeschlisten-Import)');

            $table->unsignedInteger('articles_not_found')->default(0)
                ->after('articles_deleted')
                ->comment('Artikel aus CSV nicht in DB gefunden');
        });
    }

    public function down(): void
    {
        Schema::table('article_imports', function (Blueprint $table) {
            $table->dropColumn(['articles_deleted', 'articles_not_found']);
        });
    }
};
