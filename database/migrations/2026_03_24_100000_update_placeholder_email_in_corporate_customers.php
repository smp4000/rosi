<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bestehende Platzhalter-Email smp4000@me.com durch neue
 * ungueltige Domain ersetzen (keine-email@platzhalter.local).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('corporate_customers')
            ->where('email', 'smp4000@me.com')
            ->update(['email' => 'keine-email@platzhalter.local']);

        // Default-Wert in DB-Schema aendern
        DB::statement("ALTER TABLE corporate_customers ALTER COLUMN email SET DEFAULT 'keine-email@platzhalter.local'");

        // InvoiceSetting Key-Value Store aktualisieren
        DB::table('invoice_settings')
            ->where('key', 'default_customer_email')
            ->where('value', 'smp4000@me.com')
            ->update(['value' => 'keine-email@platzhalter.local']);
    }

    public function down(): void
    {
        DB::table('corporate_customers')
            ->where('email', 'keine-email@platzhalter.local')
            ->update(['email' => 'smp4000@me.com']);

        DB::statement("ALTER TABLE corporate_customers ALTER COLUMN email SET DEFAULT 'smp4000@me.com'");
    }
};
