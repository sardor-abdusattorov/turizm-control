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
            $table->string('number', 50)->nullable()->unique();
            $table->foreignId('order_type_id')->constrained()->restrictOnDelete();
            $table->string('scope', 20)->default('internal');
            $table->string('title');
            $table->text('description')->nullable();
            // Nullable: real buyruqs sometimes reach the registry before their
            // scan does (the annual 74-АФ exists only as copies inside dossiers).
            $table->string('file_path')->nullable();
            $table->string('document_key')->nullable();
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
