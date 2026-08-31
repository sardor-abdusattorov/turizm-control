<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisitions', function (Blueprint $table) {
            $table->id();

            $table->string('number', 30)->unique();
            $table->string('title');
            $table->text('description');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['author_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisitions');
    }
};
