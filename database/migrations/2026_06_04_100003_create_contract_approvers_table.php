<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_approvers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->string('status', 20)->default('queued');
            $table->text('comment')->nullable();
            // System-generated note (e.g. "cancelled after edit"), kept separate
            // so a real approver's own comment is never overwritten.
            $table->text('system_comment')->nullable();
            // The verdict a row held right before a mid-flow edit invalidated it,
            // so the audit trail keeps "Approved (cancelled)" instead of a blank.
            $table->string('original_status')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'order']);
            $table->index('status');
            $table->index('user_id');
            $table->index('due_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_approvers');
    }
};
