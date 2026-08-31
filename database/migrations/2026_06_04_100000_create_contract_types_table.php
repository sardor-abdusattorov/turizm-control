<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_types', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->json('description')->nullable();

            $table->string('direction', 10)->default('expense');

            $table->string('counterparty_kind', 20)->default('contact');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_types');
    }
};
