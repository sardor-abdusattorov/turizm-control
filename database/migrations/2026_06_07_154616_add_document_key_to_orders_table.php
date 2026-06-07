<?php

use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('document_key')->nullable()->after('file_path');
        });

        Order::query()
            ->whereNull('document_key')
            ->cursor()
            ->each(fn (Order $order) => $order->updateQuietly([
                'document_key' => Order::generateDocumentKey(),
            ]));
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('document_key');
        });
    }
};
