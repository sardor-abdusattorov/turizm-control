<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // A payment settles exactly one of the two: a contract, as a share
            // of its total, or a project directly — project spending that never
            // went through a contract still has to land in the ledger.
            $table->foreignId('contract_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            // Contract payments carry a percent; project payments an absolute
            // sum in their own currency — there is no total to be a share of.
            $table->decimal('percent', 5, 2)->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->foreignId('currency_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('purpose')->nullable();
            $table->date('paid_at');
            // One payment can carry several proof files (screenshots, PDF
            // payment orders) — stored as an array of private-disk paths.
            $table->json('screenshots');
            $table->timestamps();

            $table->index('contract_id');
            $table->index('project_id');
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
