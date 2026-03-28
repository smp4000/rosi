<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PIN fuer POS-App Login.
     * Mitarbeiter meldet sich an der POS-App mit einer 4-6 stelligen PIN an.
     * Gespeichert wird nur der Hash (wie beim Passwort - niemand kann die PIN lesen).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pin_hash')->nullable()->after('password')->comment('Gehashte PIN fuer POS-App Login');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pin_hash');
        });
    }
};
