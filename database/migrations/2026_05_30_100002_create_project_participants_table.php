<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('sponsor_id')->nullable()->constrained('sponsors')->nullOnDelete();
            $table->string('role', 20)->default('participant');
            $table->string('name');
            $table->decimal('amount', 15, 2)->default(0);
            // Cached aggregate of the participant's installments, kept in sync
            // by ProjectPaymentObserver — the analogue of contracts.paid_percent.
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->restrictOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'sort']);
            $table->index(['project_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_participants');
    }
};
