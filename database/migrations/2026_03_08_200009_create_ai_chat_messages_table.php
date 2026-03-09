<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * KI-Chat-Nachrichten erstellen.
     */
    public function up(): void
    {
        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('conversation_id', 36)->comment('UUID fuer Gespraechsgruppierung');
            $table->string('role', 20)->comment('user, assistant, system');
            $table->text('content')->comment('Nachrichteninhalt');
            $table->json('context')->nullable()->comment('Kontext-Daten');
            $table->unsignedInteger('tokens_used')->nullable()->comment('Verbrauchte Tokens');
            $table->string('model', 50)->nullable()->comment('Verwendetes KI-Modell');
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'conversation_id']);
            $table->index(['user_id', 'conversation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
    }
};
