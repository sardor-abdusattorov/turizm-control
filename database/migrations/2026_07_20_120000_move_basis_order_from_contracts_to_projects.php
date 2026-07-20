<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transition for environments that ran the original create_* migrations
 * before the basis order moved to the project: adds projects.order_id,
 * carries every contract's basis over (the most frequent order per project
 * wins) and drops contracts.order_id. Freshly built databases already get
 * the final schema from the create_* migrations, so both guards no-op there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('projects', 'order_id')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->foreignId('order_id')->nullable()->after('name')->constrained()->nullOnDelete();
            });
        }

        if (Schema::hasColumn('contracts', 'order_id')) {
            DB::table('contracts')
                ->whereNotNull('order_id')
                ->whereNotNull('project_id')
                ->select('project_id', 'order_id', DB::raw('COUNT(*) as weight'))
                ->groupBy('project_id', 'order_id')
                ->orderByDesc('weight')
                ->get()
                ->unique('project_id')
                ->each(function ($row): void {
                    DB::table('projects')
                        ->where('id', $row->project_id)
                        ->whereNull('order_id')
                        ->update(['order_id' => $row->order_id]);
                });

            Schema::table('contracts', function (Blueprint $table) {
                $table->dropConstrainedForeignId('order_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('contracts', 'order_id')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        if (Schema::hasColumn('projects', 'order_id')) {
            DB::table('projects')->whereNotNull('order_id')->get(['id', 'order_id'])
                ->each(function ($project): void {
                    DB::table('contracts')
                        ->where('project_id', $project->id)
                        ->update(['order_id' => $project->order_id]);
                });

            Schema::table('projects', function (Blueprint $table) {
                $table->dropConstrainedForeignId('order_id');
            });
        }
    }
};
