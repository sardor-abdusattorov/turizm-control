<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_message_logs', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id')->nullable()->index();
            $table->string('method', 40);
            $table->text('text')->nullable();
            $table->boolean('ok')->default(false);
            $table->unsignedSmallInteger('status')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_message_logs');
    }
};
