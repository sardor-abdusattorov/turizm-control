<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index('status');
        });

        Schema::table('project_participants', function (Blueprint $table) {
            $table->foreignId('sponsor_id')->nullable()->after('contact_id')->constrained('sponsors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_participants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sponsor_id');
        });

        Schema::dropIfExists('sponsors');
    }
};
