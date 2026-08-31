<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->string('number', 50)->unique();
            $table->string('scope', 20)->default('pr_center');

            $table->foreignId('basis_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();

            $table->string('file_path')->nullable();
            $table->date('issued_at')->nullable();
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('issued_at');
            $table->index('scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
