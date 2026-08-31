<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // `order` is the step inside the attempt, `round` the attempt: a
            // voided round keeps its rows so the record can show that somebody
            // rejected it, it was corrected, and it went round again.
            $table->unsignedInteger('order')->default(1);
            $table->unsignedInteger('round')->default(1);
            $table->string('status', 20)->default('queued');
            // The verdict a voided row was carrying, so history still reads.
            $table->string('original_status', 20)->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();

            $table->index(['approvable_type', 'approvable_id', 'order']);
            $table->index(['user_id', 'status']);
            $table->index('due_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
