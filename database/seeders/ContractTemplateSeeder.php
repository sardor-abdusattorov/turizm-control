<?php

namespace Database\Seeders;

use App\Models\ContractTemplate;
use App\Models\OrderType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class ContractTemplateSeeder extends Seeder
{
    private const STUB_PATH = 'documents/template-example.docx';

    public function run(): void
    {
        $stub = public_path(self::STUB_PATH);

        if (! is_file($stub)) {
            $this->command?->warn('ContractTemplateSeeder skipped: '.self::STUB_PATH.' is missing.');

            return;
        }

        $hr = OrderType::firstWhere('title->ru', 'Кадровый');
        $financial = OrderType::firstWhere('title->ru', 'Финансовый');
        $admin = OrderType::firstWhere('title->ru', 'Хозяйственный');

        $templates = [
            ['name' => 'Договор аренды офисного помещения (RU)', 'order_type' => $admin, 'sort' => 1],
            ['name' => 'Договор оказания услуг (RU)', 'order_type' => $financial, 'sort' => 2],
            ['name' => 'Trudovoy shartnoma (UZ) — Kadrlar', 'order_type' => $hr, 'sort' => 3],
        ];

        foreach ($templates as $data) {
            if (! $data['order_type']) {
                $this->command?->warn("Skipping template {$data['name']} — order type not found.");

                continue;
            }

            $template = ContractTemplate::firstOrCreate(
                ['name' => $data['name']],
                [
                    'order_type_id' => $data['order_type']->id,
                    'sort' => $data['sort'],
                    'status' => true,
                    'template_file' => 'uploads/files/contract-templates/pending/stub.docx',
                ]
            );

            $relativePath = 'uploads/files/contract-templates/'.$template->id.'/template.docx';
            Storage::disk('local')->put($relativePath, file_get_contents($stub));

            $template->update(['template_file' => $relativePath]);
        }
    }
}
