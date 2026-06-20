<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // Denormalised summary of the contract's payment progress, kept in
            // sync by Payment's saved/deleted observers. Stored (rather than
            // recomputed on the fly) so the contracts list can index and
            // filter by it cheaply.
            $table->string('payment_status', 20)->default('not_paid')->after('status');
            $table->decimal('paid_percent', 5, 2)->default(0)->after('payment_status');

            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropColumn(['payment_status', 'paid_percent']);
        });
    }
};
