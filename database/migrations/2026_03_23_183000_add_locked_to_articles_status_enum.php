<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * articles.status Enum um 'locked' erweitern.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE articles MODIFY COLUMN status ENUM('active', 'new', 'price_changed', 'inactive', 'deleted', 'locked') NOT NULL DEFAULT 'new' COMMENT 'Artikelstatus'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE articles MODIFY COLUMN status ENUM('active', 'new', 'price_changed', 'inactive', 'deleted') NOT NULL DEFAULT 'new' COMMENT 'Artikelstatus'");
    }
};
