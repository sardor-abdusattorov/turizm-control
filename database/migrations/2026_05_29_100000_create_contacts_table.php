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
            $table->string('legal_form', 50)->nullable();
            $table->json('name');
            $table->string('inn', 30)->nullable()->unique();
            $table->string('pinfl', 30)->nullable()->unique();
            $table->string('oked', 20)->nullable();
            $table->json('address')->nullable();
            $table->string('phone', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('director_name')->nullable();
            $table->string('bank_account', 50)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('mfo', 20)->nullable();
            $table->string('swift', 20)->nullable();
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
