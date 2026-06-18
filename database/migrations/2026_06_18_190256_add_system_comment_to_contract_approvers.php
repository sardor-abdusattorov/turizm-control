<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a separate column for system-generated notes (e.g. "cancelled
     * because the contract was edited") so a real approver's own comment
     * isn't overwritten when the chain is invalidated mid-flow.
     */
    public function up(): void
    {
        Schema::table('contract_approvers', function (Blueprint $table) {
            $table->text('system_comment')->nullable()->after('comment');
        });

        // Move existing invalidation notes off the user-comment column. The
        // original approver comments were already overwritten before this
        // fix, so nothing user-authored is lost here.
        DB::table('contract_approvers')
            ->where('status', 'invalidated')
            ->whereNotNull('comment')
            ->update([
                'system_comment' => DB::raw('comment'),
                'comment' => null,
            ]);
    }

    public function down(): void
    {
        Schema::table('contract_approvers', function (Blueprint $table) {
            $table->dropColumn('system_comment');
        });
    }
};
