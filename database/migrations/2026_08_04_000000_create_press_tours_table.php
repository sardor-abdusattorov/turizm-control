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
            // Which way the tour runs: foreign media hosted here, a domestic
            // regional tour, or local media sent abroad.
            $table->string('direction', 20)->default('local');
            $table->string('name');
            // «Страна» in the registry, but domestic tours put a region there
            // (Хорезм, Самарканд), so the column is the broader "place".
            $table->string('place')->nullable();
            // The registry never gives real dates — only «август»,
            // «июль-август», «11-18 Август» — so the period stays free text
            // and the sortable month lives beside it.
            $table->string('period')->nullable();
            $table->unsignedTinyInteger('starts_month')->nullable();
            // «Кол-во» is not always a number either: «6+11» is two groups,
            // «n/a» is unknown. The headcount is kept for sums, the raw note
            // preserves what the registry actually said.
            $table->unsignedSmallInteger('people_count')->nullable();
            $table->string('people_note')->nullable();
            // Two names share one registry cell — the owning manager and the
            // curator over the whole direction.
            $table->string('responsible')->nullable();
            $table->string('curator')->nullable();
            $table->string('foreign_partner')->nullable();
            // The registry is written a year ahead, so a tour is a plan first
            // and a fact later; the report documents are due once it is held.
            $table->string('state', 20)->default('planned');
            $table->date('held_on')->nullable();
            // The buyruq the tour rests on — the 2026 domestic tours all cite
            // приказ № 49-АФ, mirroring how a project names its basis.
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
