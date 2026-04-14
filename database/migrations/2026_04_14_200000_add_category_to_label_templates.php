<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_templates', function (Blueprint $table) {
            $table->string('category', 50)
                ->after('slug')
                ->index()
                ->comment('Gruppierung: tankbetrug, testdruck, adresse');
        });

        // Bestehende Templates: category = slug
        DB::table('label_templates')
            ->whereNull('deleted_at')
            ->update(['category' => DB::raw('slug')]);
    }

    public function down(): void
    {
        Schema::table('label_templates', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
