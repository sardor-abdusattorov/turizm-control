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
            // Order register number, e.g. "ПРК-2026-001". Auto-generated in the
            // model's creating() hook but kept nullable so existing flows that
            // bulk-insert without it don't break.
            $table->string('number', 50)->nullable()->unique();
            $table->foreignId('order_type_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('document_key')->nullable();
            // Issued_at — the day the order was actually signed; the source of
            // truth for which yearly register the order belongs to. deadline_at
            // stays around for orders that imply a follow-up task.
            $table->date('issued_at')->nullable();
            $table->date('deadline_at')->nullable();
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('issued_at');
            $table->index('deadline_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
