<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->uuid('tenant_id')->index();
            $table->string('type')->default('Gehalt')->comment('Verwendungszweck: Gehalt, VWL, etc.');
            $table->text('account_holder')->nullable()->comment('Verschluesselt');
            $table->text('iban')->comment('Verschluesselt');
            $table->text('bic')->nullable()->comment('Verschluesselt');
            $table->text('bank_name')->nullable()->comment('Verschluesselt');
            $table->boolean('is_primary')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_bank_accounts');
    }
};
