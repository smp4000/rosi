<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat-Konversationen zwischen Partner und Mitarbeiter.
 * UUID als PK, mandantenfaehig.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('participant_one_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('participant_two_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'participant_one_id']);
            $table->index(['tenant_id', 'participant_two_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
