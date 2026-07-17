<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // The bell reads by notifiable id first — an id-leading index on
            // top of the (type, id) one morphs() already creates.
            $table->index(['notifiable_id', 'notifiable_type'], 'notifications_notifiable_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
