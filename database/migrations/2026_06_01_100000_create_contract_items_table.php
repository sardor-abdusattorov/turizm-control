<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->json('specification');
            $table->decimal('amount', 15, 2)->default(0);
            $table->json('counterparty')->nullable();
            $table->string('agreement_ref')->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_items');
    }
};
