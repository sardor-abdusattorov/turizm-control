<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('press_tour_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('press_tour_id')->constrained()->cascadeOnDelete();
            // Report, media coverage, photos, programme, participant list, act.
            $table->string('type', 30)->nullable();
            $table->string('file_path');
            $table->string('original_name');
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['press_tour_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('press_tour_attachments');
    }
};
