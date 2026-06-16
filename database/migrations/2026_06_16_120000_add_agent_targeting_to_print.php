<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mehrere Drucker pro Station: Ein Druckjob zielt jetzt auf einen bestimmten
     * Agenten (Standort "Kasse"/"Buero"). Der Agent holt nur noch SEINE Jobs.
     *
     * - print_jobs.target_agent_id : gewuenschter Ziel-Agent (null = Default-Agent)
     * - print_jobs.user_id         : wer hat ausgeloest (Audit, v.a. fuer Nachdrucke)
     * - print_agents.is_default     : Standard-Agent der Station (zieht ziellose Jobs)
     */
    public function up(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->uuid('target_agent_id')->nullable()->after('agent_id');
            $table->uuid('user_id')->nullable()->after('created_by');

            $table->foreign('target_agent_id')->references('id')->on('print_agents')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->index('target_agent_id');
        });

        Schema::table('print_agents', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_active')
                ->comment('Standard-Agent der Station (druckt Jobs ohne Ziel)');
        });
    }

    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropForeign(['target_agent_id']);
            $table->dropForeign(['user_id']);
            $table->dropIndex(['target_agent_id']);
            $table->dropColumn(['target_agent_id', 'user_id']);
        });

        Schema::table('print_agents', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
