<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remember the decision a row had reached right before it was cancelled
     * by a mid-flow edit. Without this, an "approved" row that gets
     * invalidated loses its decision entirely and reads as a generic
     * "cancelled" entry — the approver's verdict (and the context around
     * their comment) disappears from the audit trail.
     */
    public function up(): void
    {
        Schema::table('contract_approvers', function (Blueprint $table) {
            $table->string('original_status')->nullable()->after('system_comment');
        });
    }

    public function down(): void
    {
        Schema::table('contract_approvers', function (Blueprint $table) {
            $table->dropColumn('original_status');
        });
    }
};
