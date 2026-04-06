<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zahlungsdetails: Von wem bezahlt und Zahlungsart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_thefts', function (Blueprint $table) {
            $table->string('paid_by')->nullable()
                ->after('payment_amount')
                ->comment('Von wem wurde bezahlt (Name/Halter/Versicherung)');

            $table->string('payment_method', 30)->nullable()
                ->after('paid_by')
                ->comment('Zahlungsart: bank_transfer, cash, card, insurance, collection, other');
        });
    }

    public function down(): void
    {
        Schema::table('fuel_thefts', function (Blueprint $table) {
            $table->dropColumn(['paid_by', 'payment_method']);
        });
    }
};
