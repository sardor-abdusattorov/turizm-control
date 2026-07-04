<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('paid_at');
            $table->string('screenshot')->nullable();
            $table->timestamps();

            $table->index('paid_at');
        });

        // Cached aggregate of the participant's installments, kept in sync by
        // ProjectPaymentObserver — the direct analogue of contracts.paid_percent.
        Schema::table('project_participants', function (Blueprint $table) {
            $table->decimal('paid_amount', 15, 2)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('project_participants', function (Blueprint $table) {
            $table->dropColumn('paid_amount');
        });

        Schema::dropIfExists('project_payments');
    }
};
