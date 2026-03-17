<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat-Nachrichten — unveraenderlich (kein updated_at).
 * Bigint Auto-Increment statt UUID (hohe Menge).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignUuid('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('content');
            $table->string('type', 20)->default('text'); // text, document, image, system
            $table->json('metadata')->nullable(); // document_id, file_path etc.
            $table->string('channel', 20)->default('app'); // app, telegram, whatsapp
            $table->string('external_message_id')->nullable(); // Telegram/WhatsApp Message-ID
            $table->timestamp('read_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('created_at')->nullable();
            // Kein updated_at — Nachrichten sind unveraenderlich

            $table->index(['conversation_id', 'created_at']);
            $table->index(['sender_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
