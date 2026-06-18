<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Before the queued/pending split, approvers on a draft contract were
     * created as 'pending'. Demote those to 'queued' so the chain reflects
     * that nothing is actively under review until the contract is submitted.
     */
    public function up(): void
    {
        DB::table('contract_approvers')
            ->where('status', 'pending')
            ->whereIn('contract_id', fn ($query) => $query
                ->select('id')
                ->from('contracts')
                ->where('status', 'draft'))
            ->update(['status' => 'queued']);
    }

    public function down(): void
    {
        DB::table('contract_approvers')
            ->where('status', 'queued')
            ->update(['status' => 'pending']);
    }
};
