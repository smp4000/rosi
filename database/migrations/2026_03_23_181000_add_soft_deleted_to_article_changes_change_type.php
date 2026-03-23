<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * change_type Enum um 'soft_deleted' erweitern.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE article_changes MODIFY COLUMN change_type ENUM('created', 'price_updated', 'data_updated', 'reactivated', 'soft_deleted') NOT NULL COMMENT 'Art der Aenderung'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE article_changes MODIFY COLUMN change_type ENUM('created', 'price_updated', 'data_updated', 'reactivated') NOT NULL COMMENT 'Art der Aenderung'");
    }
};
