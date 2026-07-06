<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsors', function (Blueprint $table) {
            $table->string('inn', 30)->nullable()->after('name');
            $table->string('contact_person')->nullable()->after('inn');
            $table->string('address')->nullable()->after('website');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->string('website')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('sponsors', function (Blueprint $table) {
            $table->dropColumn(['inn', 'contact_person', 'address']);
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('website');
        });
    }
};
