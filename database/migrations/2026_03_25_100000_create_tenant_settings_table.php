<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Zentrale Einstellungen pro Tenant (Key-Value).
     * Ersetzt invoice_settings und zentralisiert alle Tenant-Konfigurationen.
     */
    public function up(): void
    {
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->comment('Mandant');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('group', 50)->default('general')->comment('Einstellungsgruppe (mail, invoice, api, etc.)');
            $table->string('key', 100)->comment('Einstellungs-Schluessel');
            $table->text('value')->nullable()->comment('Wert (ggf. verschluesselt)');
            $table->boolean('is_encrypted')->default(false)->comment('Wert verschluesselt?');
            $table->timestamps();

            $table->unique(['tenant_id', 'group', 'key']);
            $table->index(['tenant_id', 'group']);
        });

        // Bestehende invoice_settings in tenant_settings migrieren
        if (Schema::hasTable('invoice_settings')) {
            $rows = \Illuminate\Support\Facades\DB::table('invoice_settings')->get();

            foreach ($rows as $row) {
                \Illuminate\Support\Facades\DB::table('tenant_settings')->insert([
                    'id' => \Illuminate\Support\Str::uuid7()->toString(),
                    'tenant_id' => $row->tenant_id,
                    'group' => 'invoice',
                    'key' => $row->key,
                    'value' => $row->value,
                    'is_encrypted' => false,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};
