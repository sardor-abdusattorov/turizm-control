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
            $table->foreignId('contract_type_id')->nullable()->constrained()->nullOnDelete();
            // The buyruq this contract was concluded under («на основании
            // приказа № 74-АФ»); many contracts may share one order.
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->foreignId('responsible_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status', 30)->default('draft');

            $table->string('payment_status', 20)->default('not_paid');
            $table->decimal('paid_percent', 5, 2)->default(0);

            $table->date('signed_at')->nullable();

            $table->string('document_file')->nullable();
            $table->string('document_key')->nullable();
            $table->string('pdf_file')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('payment_status');
            $table->index('contract_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
