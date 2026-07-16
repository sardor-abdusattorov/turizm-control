<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foreign counterparties quote a bank address alongside the account (e.g.
     * RX France: "CREDIT INDUSTRIEL ET COMMERCIAL, 102 Boulevard Haussmann
     * 75008 Paris"), which Uzbek requisites never carry. An additive column so
     * a DB that already ran the create migration picks it up too.
     */
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->string('bank_address')->nullable()->after('bank_name');
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn('bank_address');
        });
    }
};
