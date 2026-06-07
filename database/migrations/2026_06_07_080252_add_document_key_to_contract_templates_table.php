<?php

use App\Models\ContractTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_templates', function (Blueprint $table) {
            $table->string('document_key')->nullable()->after('template_file');
        });

        ContractTemplate::query()
            ->whereNull('document_key')
            ->cursor()
            ->each(fn (ContractTemplate $template) => $template->update([
                'document_key' => ContractTemplate::generateDocumentKey(),
            ]));
    }

    public function down(): void
    {
        Schema::table('contract_templates', function (Blueprint $table) {
            $table->dropColumn('document_key');
        });
    }
};
