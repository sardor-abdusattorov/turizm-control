<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index the columns the dashboard widgets and bot lookups actually
     * filter on. None of these changes the data model — purely speed.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_id', 'notifiable_type'], 'notifications_notifiable_index');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->index(['responsible_id', 'status'], 'contracts_responsible_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_notifiable_index');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex('contracts_responsible_status_index');
        });
    }
};
