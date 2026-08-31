<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('currency_id')->nullable()->constrained()->nullOnDelete();
            $table->string('account_number', 34);
            $table->string('bank_name')->nullable();

            $table->string('bank_address')->nullable();
            $table->string('mfo', 20)->nullable();
            $table->string('swift', 20)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index('contact_id');
        });

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
