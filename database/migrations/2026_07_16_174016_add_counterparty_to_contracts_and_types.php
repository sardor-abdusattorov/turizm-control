<?php

use App\Enums\CounterpartyKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which party a contract kind faces: a Contact (default — suppliers,
        // participant fees) or a Sponsor (sponsorship). Drives the contract
        // form's counterparty picker and the project income split.
        Schema::table('contract_types', function (Blueprint $table) {
            $table->string('counterparty_kind', 20)
                ->default(CounterpartyKind::Contact->value)
                ->after('direction');
        });

        // Sponsorship contracts point at a Sponsor instead of a Contact, so
        // both counterparty FKs are now nullable — a contract carries exactly
        // one of them, chosen by its type's counterparty_kind.
        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('sponsor_id')
                ->nullable()
                ->after('contact_id')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->unsignedBigInteger('contact_id')->nullable()->change();
        });

        // Existing «Спонсорство» type (seeded) faces a Sponsor.
        DB::table('contract_types')
            ->where('title->ru', 'Спонсорство')
            ->update(['counterparty_kind' => CounterpartyKind::Sponsor->value]);
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sponsor_id');
        });

        // Restore the not-null contact requirement. Rows left without a contact
        // (sponsorship contracts) must be cleared first so the change succeeds.
        DB::table('contracts')->whereNull('contact_id')->delete();

        Schema::table('contracts', function (Blueprint $table) {
            $table->unsignedBigInteger('contact_id')->nullable(false)->change();
        });

        Schema::table('contract_types', function (Blueprint $table) {
            $table->dropColumn('counterparty_kind');
        });
    }
};
