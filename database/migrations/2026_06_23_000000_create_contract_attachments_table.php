<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            // Kind of paper inside the dossier (contract scan, invoice, SWIFT,
            // act…). Nullable on purpose: uploading must stay one drag-and-drop,
            // categorising can happen later or never.
            $table->string('type', 30)->nullable();
            $table->string('file_path');
            $table->string('original_name');
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['contract_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_attachments');
    }
};
