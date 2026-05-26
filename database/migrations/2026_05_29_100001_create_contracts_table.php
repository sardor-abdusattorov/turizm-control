<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->unique();
            $table->foreignId('order_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->foreignId('responsible_id')->constrained('users')->cascadeOnDelete();
            $table->json('title');
            $table->json('description')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status', 30)->default('draft');
            $table->date('deadline_at')->nullable();
            $table->date('signed_at')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('deadline_at');
            $table->index('order_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
