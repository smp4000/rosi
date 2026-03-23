<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('email_status', 20)->default('pending')->after('status');
            $table->string('email_error', 500)->nullable()->after('email_status');
            $table->string('print_status', 20)->default('pending')->after('email_error');
        });

        // Bestehende Rechnungen mit sent_at -> email_status = 'sent'
        DB::table('invoices')->whereNotNull('sent_at')->update(['email_status' => 'sent']);
        DB::table('invoices')->whereNotNull('printed_at')->update(['print_status' => 'printed']);
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['email_status', 'email_error', 'print_status']);
        });
    }
};
