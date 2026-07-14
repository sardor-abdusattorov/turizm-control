<?php

namespace Database\Seeders;

use App\Models\ContractTemplate;
use App\Models\ContractType;
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

        $spaceRental = ContractType::firstWhere('title->ru', 'Аренда площади');
        $services = ContractType::firstWhere('title->ru', 'Оказание услуг');
        $participantFee = ContractType::firstWhere('title->ru', 'Взнос участника');

        $templates = [
            ['name' => 'Договор аренды выставочной площади (RU)', 'contract_type' => $spaceRental, 'sort' => 1],
            ['name' => 'Договор оказания услуг (RU)', 'contract_type' => $services, 'sort' => 2],
            ['name' => 'Договор взноса участника выставки (RU)', 'contract_type' => $participantFee, 'sort' => 3],
        ];

        foreach ($templates as $data) {
            if (! $data['contract_type']) {
                $this->command?->warn("Skipping template {$data['name']} — contract type not found.");

                continue;
            }

            $template = ContractTemplate::firstOrCreate(
                ['name' => $data['name']],
                [
                    'contract_type_id' => $data['contract_type']->id,
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
