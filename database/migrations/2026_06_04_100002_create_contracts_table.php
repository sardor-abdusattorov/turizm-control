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
            $table->foreignId('contract_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->foreignId('responsible_id')->constrained('users')->cascadeOnDelete();
            $table->string('language', 2)->default('ru');
            $table->string('title');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('signing_place')->nullable();
            $table->string('status', 30)->default('draft');
            $table->date('deadline_at')->nullable();
            $table->date('signed_at')->nullable();

            $table->string('document_file')->nullable();
            $table->string('document_key')->nullable();
            $table->string('pdf_file')->nullable();

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
