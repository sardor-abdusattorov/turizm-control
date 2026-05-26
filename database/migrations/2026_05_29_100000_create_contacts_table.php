<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('legal');
            $table->json('name');
            $table->string('inn', 30)->nullable()->unique();
            $table->string('pinfl', 30)->nullable()->unique();
            $table->json('address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('contact_person')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index('status');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
