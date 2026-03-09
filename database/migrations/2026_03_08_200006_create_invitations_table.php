<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Einladungen-Tabelle erstellen.
     */
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('UUID v7');
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('email')->comment('Eingeladene E-Mail');
            $table->string('token', 64)->unique()->comment('Einladungs-Token');
            $table->string('type', 20)->default('employee')->comment('employee, manager');
            $table->unsignedBigInteger('role_id')->nullable()->comment('Vorgesehene Rolle (spatie)');
            $table->json('gas_station_ids')->nullable()->comment('Zuzuweisende Tankstellen');
            $table->foreignUuid('invited_by')->constrained('users')->cascadeOnDelete()->comment('Eingeladen von');
            $table->text('message')->nullable()->comment('Persoenliche Nachricht');
            $table->timestamp('expires_at')->comment('Ablaufdatum');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
