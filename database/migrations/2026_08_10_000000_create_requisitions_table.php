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
            // Generated on create — a requisition is internal paperwork and
            // nobody hands out numbers for it the way they do for buyruqs.
            $table->string('number', 30)->unique();
            $table->string('title');
            $table->text('description');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            // Stamped on submit from settings.requisition.review_days, so a
            // later change to the setting never moves a live deadline.
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comment')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['reviewer_id', 'status']);
            $table->index(['author_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisitions');
    }
};
