<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Artikelgruppen-Tabelle erstellen.
     * Globale Stammdaten (273 System-Eintraege) + Partner-eigene Gruppen.
     */
    public function up(): void
    {
        Schema::create('article_groups', function (Blueprint $table) {
            $table->id()->comment('Auto-Increment ID (kompatibel mit altem System)');
            $table->uuid('tenant_id')->nullable()->index()->comment('NULL=System-Eintrag, UUID=Partner-eigene Gruppe');

            // Geschaeftsbereich (Ebene 1)
            $table->integer('business_area_id')->nullable()->comment('Geschaeftsbereich-ID');
            $table->string('business_area', 50)->nullable()->comment('Geschaeftsbereich-Name (Kraftstoffe, Shop, Waschen...)');

            // EKW-Konto (Ebene 2)
            $table->integer('revenue_account_id')->nullable()->comment('EKW-Konto-ID');
            $table->string('revenue_account', 50)->nullable()->comment('EKW-Konto-Bezeichnung');

            // Warengruppe (Ebene 3)
            $table->integer('product_group_id')->nullable()->comment('Warengruppe-ID');
            $table->string('product_group', 50)->nullable()->comment('Warengruppe-Bezeichnung');

            // Artikelgruppe Ebene 4
            $table->integer('article_group_04_id')->nullable()->comment('Artikelgruppe Ebene 4 ID');
            $table->string('article_group_04', 50)->nullable()->comment('Artikelgruppe Ebene 4 Name');

            // Artikelgruppe Ebene 3
            $table->integer('article_group_03_id')->nullable()->comment('Artikelgruppe Ebene 3 ID');
            $table->string('article_group_03', 50)->nullable()->comment('Artikelgruppe Ebene 3 Name');

            // Artikelgruppe Ebene 2/1
            $table->integer('article_group_02_01_id')->nullable()->comment('Artikelgruppe Ebene 2/1 ID');
            $table->string('article_group_02_01', 50)->nullable()->comment('Artikelgruppe Ebene 2/1 Name');

            // Steuer und Jugendschutz
            $table->decimal('vat_rate', 5, 2)->default(19.00)->comment('MwSt-Satz (19.00/7.00/0.00)');
            $table->enum('vat_category', ['A', 'B', 'C'])->default('A')->comment('MwSt-Kategorie (A=19%, B=7%, C=0%)');
            $table->enum('youth_protection', ['0', '16', '18'])->default('0')->comment('Jugendschutz-Alter');
            $table->decimal('lease_rate', 5, 2)->default(0.00)->comment('Pachtsatz');

            // System-Kennung
            $table->boolean('is_system')->default(false)->comment('Vordefinierte System-Gruppe (nicht loeschbar)');

            $table->timestamps();
            $table->softDeletes();

            // Foreign Key
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });

        // CHECK-Constraint: MwSt-Satz muss zur Kategorie passen
        DB::statement("ALTER TABLE `article_groups` ADD CONSTRAINT `chk_vat_rate_category` CHECK (
            (`vat_rate` = 19.00 AND `vat_category` = 'A') OR
            (`vat_rate` = 7.00 AND `vat_category` = 'B') OR
            (`vat_rate` = 0.00 AND `vat_category` = 'C')
        )");
    }

    public function down(): void
    {
        Schema::dropIfExists('article_groups');
    }
};
