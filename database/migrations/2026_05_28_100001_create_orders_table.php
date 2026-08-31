<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // Every real buyruq has an official number (06-АФ, 119-АФ…) —
            // entered by hand, never generated.
            $table->string('number', 50)->unique();
            $table->string('scope', 20)->default('pr_center');
            // A PR-centre buyruq is issued on the strength of a committee one:
            // the committee decides, the centre implements. Nullable because a
            // committee order has no basis above it, and the centre sometimes
            // acts on its own.
            $table->foreignId('basis_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            // Nullable: real buyruqs sometimes reach the registry before their
            // scan does (the annual 74-АФ exists only as copies inside dossiers).
            $table->string('file_path')->nullable();
            $table->date('issued_at')->nullable();
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('issued_at');
            $table->index('scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
