<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('international');
            $table->string('name');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->decimal('area_sqm', 8, 2)->nullable();
            $table->decimal('area_cost', 15, 2)->nullable();
            $table->boolean('area_is_free')->default(false);
            $table->foreignId('area_currency_id')->nullable()->constrained('currencies')->restrictOnDelete();
            $table->decimal('stand_cost', 15, 2)->nullable();
            $table->foreignId('stand_currency_id')->nullable()->constrained('currencies')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->json('gallery')->nullable();
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('type');
            $table->index('status');
            $table->index('starts_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
