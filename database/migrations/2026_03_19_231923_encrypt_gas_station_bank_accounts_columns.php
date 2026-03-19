<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Crypt;

/**
 * Aendert IBAN, BIC, Bankname und Beschreibung auf TEXT
 * und verschluesselt bestehende Daten (DSGVO).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Bestehende Daten lesen (noch Klartext)
        $accounts = DB::table('gas_station_bank_accounts')->get();

        // 2. Spaltentypen aendern: varchar → text (fuer verschluesselte Werte)
        Schema::table('gas_station_bank_accounts', function (Blueprint $table) {
            $table->text('iban')->change();
            $table->text('bank_name')->nullable()->change();
            $table->text('bic')->nullable()->change();
            $table->text('description')->nullable()->change();
        });

        // 3. Bestehende Klartext-Daten verschluesseln
        foreach ($accounts as $account) {
            DB::table('gas_station_bank_accounts')
                ->where('id', $account->id)
                ->update([
                    'iban' => $account->iban ? Crypt::encryptString($account->iban) : null,
                    'bank_name' => $account->bank_name ? Crypt::encryptString($account->bank_name) : null,
                    'bic' => $account->bic ? Crypt::encryptString($account->bic) : null,
                    'description' => $account->description ? Crypt::encryptString($account->description) : null,
                ]);
        }
    }

    public function down(): void
    {
        // Entschluesseln und zurueck auf varchar
        $accounts = DB::table('gas_station_bank_accounts')->get();

        foreach ($accounts as $account) {
            try {
                DB::table('gas_station_bank_accounts')
                    ->where('id', $account->id)
                    ->update([
                        'iban' => $account->iban ? Crypt::decryptString($account->iban) : null,
                        'bank_name' => $account->bank_name ? Crypt::decryptString($account->bank_name) : null,
                        'bic' => $account->bic ? Crypt::decryptString($account->bic) : null,
                        'description' => $account->description ? Crypt::decryptString($account->description) : null,
                    ]);
            } catch (\Exception $e) {
                // Fallback: Bereits Klartext
            }
        }

        Schema::table('gas_station_bank_accounts', function (Blueprint $table) {
            $table->string('iban', 34)->change();
            $table->string('bank_name')->nullable()->change();
            $table->string('bic', 11)->nullable()->change();
            $table->string('description')->nullable()->change();
        });
    }
};
