<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_participants', function (Blueprint $table) {
            $table->string('role', 20)->default('participant')->after('contact_id');
            $table->index(['project_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('project_participants', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'role']);
            $table->dropColumn('role');
        });
    }
};
