<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('template_file');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index('contract_type_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_templates');
    }
};
