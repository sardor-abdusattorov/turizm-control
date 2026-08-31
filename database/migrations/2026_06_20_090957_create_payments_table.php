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

            $table->foreignId('contract_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->decimal('percent', 5, 2)->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->foreignId('currency_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('purpose')->nullable();
            $table->date('paid_at');

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
