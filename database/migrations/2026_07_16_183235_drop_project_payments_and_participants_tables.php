<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the manual participant-fee mechanism: project income now derives from
 * income-direction contracts, so the project_participants / project_payments
 * tables are gone. No data migration — the rows are discarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Child first — project_payments holds the FK onto project_participants.
        Schema::dropIfExists('project_payments');
        Schema::dropIfExists('project_participants');
    }

    public function down(): void
    {
        // Parent first, then the child that references it — bodies mirror the
        // original create migrations.
        Schema::create('project_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('sponsor_id')->nullable()->constrained('sponsors')->nullOnDelete();
            $table->string('role', 20)->default('participant');
            $table->string('name');
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->restrictOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'sort']);
            $table->index(['project_id', 'role']);
        });

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
    }
};
