<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Telegram-Verknuepfung zwischen ROSI-User und Telegram-Account.
 * Token-basiert: Mitarbeiter klickt /start {token} im Bot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_links', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('telegram_chat_id')->nullable()->index();
            $table->string('telegram_username')->nullable();
            $table->string('telegram_first_name')->nullable();
            $table->string('link_token', 64)->unique();
            $table->timestamp('link_token_expires_at')->nullable();
            $table->timestamp('linked_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_links');
    }
};
