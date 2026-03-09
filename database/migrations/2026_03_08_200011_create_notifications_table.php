<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notifications-Tabelle erstellen (Laravel Standard, erweitert).
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('tenant_id', 36)->nullable()->index();
            $table->string('type')->comment('Notification-Klasse');
            $table->string('notifiable_type');
            $table->char('notifiable_id', 36)->comment('UUID des Users');
            $table->text('data')->comment('Notification-Daten JSON');
            $table->json('channels')->nullable()->comment('Gesendete Kanaele (email, database, push)');
            $table->timestamp('read_at')->nullable()->comment('Gelesen am');
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
