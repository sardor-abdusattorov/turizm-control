<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A counterparty keeps one bank account per currency (a real contract lists
     * separate UZS / USD / EUR / RUB accounts in the same bank), so the single
     * flat set of columns on `contacts` moves into its own table. Any account
     * already entered is carried across as one currency-agnostic row, then the
     * flat columns are dropped.
     */
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('currency_id')->nullable()->constrained()->nullOnDelete();
            $table->string('account_number', 34); // IBAN is up to 34 chars
            $table->string('bank_name')->nullable();
            // Foreign requisites carry the bank's own address (e.g. RX
            // France); Uzbek ones leave it blank.
            $table->string('bank_address')->nullable();
            $table->string('mfo', 20)->nullable();
            $table->string('swift', 20)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index('contact_id');
        });

        // Carry every existing flat account across before the columns vanish.
        if (Schema::hasColumn('contacts', 'bank_account')) {
            DB::table('contacts')
                ->whereNotNull('bank_account')
                ->where('bank_account', '!=', '')
                ->orderBy('id')
                ->each(function ($contact): void {
                    DB::table('bank_accounts')->insert([
                        'contact_id' => $contact->id,
                        'currency_id' => null,
                        'account_number' => $contact->bank_account,
                        'bank_name' => $contact->bank_name,
                        'mfo' => $contact->mfo,
                        'swift' => $contact->swift,
                        'sort' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });

            Schema::table('contacts', function (Blueprint $table) {
                $table->dropColumn(['bank_account', 'bank_name', 'mfo', 'swift']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('bank_account', 50)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('mfo', 20)->nullable();
            $table->string('swift', 20)->nullable();
        });

        // Fold the primary (lowest-sort) account back onto the contact.
        DB::table('bank_accounts')
            ->orderBy('contact_id')
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->groupBy('contact_id')
            ->each(function ($accounts, $contactId): void {
                $primary = $accounts->first();

                DB::table('contacts')->where('id', $contactId)->update([
                    'bank_account' => $primary->account_number,
                    'bank_name' => $primary->bank_name,
                    'mfo' => $primary->mfo,
                    'swift' => $primary->swift,
                ]);
            });

        Schema::dropIfExists('bank_accounts');
    }
};
