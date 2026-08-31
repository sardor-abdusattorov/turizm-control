<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('press_tours', function (Blueprint $table) {
            $table->id();

            $table->string('direction', 20)->default('local');
            $table->string('name');

            $table->string('place')->nullable();

            $table->string('period')->nullable();
            $table->unsignedTinyInteger('starts_month')->nullable();

            $table->unsignedSmallInteger('people_count')->nullable();
            $table->string('people_note')->nullable();

            $table->string('responsible')->nullable();
            $table->string('curator')->nullable();
            $table->string('foreign_partner')->nullable();

            $table->string('state', 20)->default('planned');
            $table->date('held_on')->nullable();

            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('direction');
            $table->index('state');
            $table->index('status');
            $table->index('starts_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('press_tours');
    }
};
