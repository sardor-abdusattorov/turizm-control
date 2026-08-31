<?php

namespace Database\Seeders;

use App\Enums\ProjectType;
use App\Models\Project;
use Illuminate\Database\Seeder;

class LocalEvents2026Seeder extends Seeder
{
    public function run(): void
    {
        $events = [
            [
                'name' => '«Orol dengiziga poyezd» — молодёжная экологическая акция и экомарафон «Biz Orol bilan birgamiz»',
                'venue' => 'Республика Каракалпакстан',
                'starts_on' => '2026-05-20',
                'ends_on' => '2026-05-24',
                'photo_report_url' => 'https://clck.ru/3UYYzh',
                'description' => 'Приказ: 02-PA 1/1-4176 от 30.11.2025 · договоров по реестру: 8.',
            ],
            [
                'name' => '«Orol baliqlaridan 99 turdagi taomlar» — гастрономический фестиваль',
                'venue' => 'Республика Каракалпакстан',
                'starts_on' => '2026-04-23',
                'ends_on' => '2026-04-24',
                'photo_report_url' => 'https://clck.ru/3UYZcp',
                'description' => 'Приказ: 02-PA 1/1-4176 от 30.11.2025 · договоров по реестру: 10.',
            ],
            [
                'name' => 'Форум «Namangan vodiy turizm markazi inflyuenserlar nigohida» и инфотур',
                'venue' => 'Наманганская область',
                'starts_on' => '2026-05-30',
                'ends_on' => '2026-06-05',
                'photo_report_url' => null,
                'description' => 'Приказ: 98-АФ от 15.05.2026 · договоров по реестру: 9.',
            ],
            [
                'name' => 'Первый международный форум «Qoraqalpog‘iston — destinatsiya» и инфотур',
                'venue' => 'Республика Каракалпакстан',
                'starts_on' => '2026-04-16',
                'ends_on' => '2026-04-25',
                'photo_report_url' => 'https://goo.su/hS0Z',
                'description' => 'Приказ: 76-АФ от 13.04.2026 · договоров по реестру: 17.',
            ],
            [
                'name' => 'Визит экспертов «Guinness World Records»',
                'venue' => 'Наманганская область',
                'starts_on' => '2026-05-22',
                'ends_on' => '2026-05-26',
                'photo_report_url' => 'https://goo.su/h3uxQ6p',
                'description' => 'Приказ: 103-АФ от 19.05.2026 · договоров по реестру: 6.',
            ],
        ];

        foreach ($events as $data) {
            Project::firstOrCreate(
                ['type' => ProjectType::Internal->value, 'name' => $data['name']],
                [
                    'venue' => $data['venue'],
                    'starts_on' => $data['starts_on'],
                    'ends_on' => $data['ends_on'],
                    'photo_report_url' => $data['photo_report_url'],
                    'description' => $data['description'],
                    'status' => true,
                ]
            );
        }
    }
}
