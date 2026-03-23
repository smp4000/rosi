<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * is_locked Flag fuer voruebergehend gesperrte Artikel.
     * Gesperrte Artikel koennen nicht ausgewaehlt/verwendet werden.
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)
                ->after('status')
                ->comment('Artikel voruebergehend gesperrt (Kenner Sperren)');
            $table->dateTime('locked_at')->nullable()
                ->after('is_locked')
                ->comment('Zeitpunkt der Sperrung');
        });

        Schema::table('article_eans', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)
                ->after('sales_packaging')
                ->comment('EAN voruebergehend gesperrt (mit Artikel gesperrt)');
        });

        // change_type Enum um 'locked' + 'unlocked' erweitern
        DB::statement("ALTER TABLE article_changes MODIFY COLUMN change_type ENUM('created', 'price_updated', 'data_updated', 'reactivated', 'soft_deleted', 'locked', 'unlocked') NOT NULL COMMENT 'Art der Aenderung'");

        // articles_locked Spalte fuer Import-Statistik
        Schema::table('article_imports', function (Blueprint $table) {
            $table->unsignedInteger('articles_locked')->default(0)
                ->after('articles_not_found')
                ->comment('Gesperrte Artikel (Sperrlisten-Import)');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['is_locked', 'locked_at']);
        });

        Schema::table('article_eans', function (Blueprint $table) {
            $table->dropColumn('is_locked');
        });

        DB::statement("ALTER TABLE article_changes MODIFY COLUMN change_type ENUM('created', 'price_updated', 'data_updated', 'reactivated', 'soft_deleted') NOT NULL COMMENT 'Art der Aenderung'");

        Schema::table('article_imports', function (Blueprint $table) {
            $table->dropColumn('articles_locked');
        });
    }
};
