<?php

namespace Database\Seeders;

use App\Models\OrderType;
use Illuminate\Database\Seeder;

class OrderTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'title' => [
                    'ru' => 'Кадровый',
                    'uz' => 'Kadrlar',
                    'en' => 'HR',
                ],
                'description' => [
                    'ru' => 'Приказы по кадрам: приём, увольнение, перевод, отпуск',
                    'uz' => "Kadrlar bo'yicha buyruqlar: qabul, ishdan bo'shatish, ko'chirish, ta'til",
                    'en' => 'HR orders: hiring, dismissal, transfer, vacation',
                ],
                'sort' => 1,
            ],
            [
                'title' => [
                    'ru' => 'Финансовый',
                    'uz' => 'Moliyaviy',
                    'en' => 'Financial',
                ],
                'description' => [
                    'ru' => 'Финансовые приказы: премии, штрафы, выплаты',
                    'uz' => "Moliyaviy buyruqlar: mukofotlar, jarimalar, to'lovlar",
                    'en' => 'Financial orders: bonuses, fines, payments',
                ],
                'sort' => 2,
            ],
            [
                'title' => [
                    'ru' => 'Хозяйственный',
                    'uz' => "Xo'jalik",
                    'en' => 'Administrative',
                ],
                'description' => [
                    'ru' => 'Хозяйственные распоряжения по организации',
                    'uz' => "Tashkilot bo'yicha xo'jalik buyruqlari",
                    'en' => 'Administrative orders for the organization',
                ],
                'sort' => 3,
            ],
            [
                'title' => [
                    'ru' => 'Производственный',
                    'uz' => 'Ishlab chiqarish',
                    'en' => 'Production',
                ],
                'description' => [
                    'ru' => 'Приказы по производственной деятельности',
                    'uz' => "Ishlab chiqarish faoliyati bo'yicha buyruqlar",
                    'en' => 'Production-related orders',
                ],
                'sort' => 4,
            ],
            [
                'title' => [
                    'ru' => 'Заявка на закупку',
                    'uz' => 'Xarid uchun ariza',
                    'en' => 'Purchase request',
                ],
                'description' => [
                    'ru' => 'Заявки на государственную закупку товаров и услуг',
                    'uz' => 'Davlat tomonidan mol va xizmatlar xaridiga arizalar',
                    'en' => 'Public procurement requests for goods and services',
                ],
                'sort' => 5,
            ],
        ];

        foreach ($types as $data) {
            OrderType::firstOrCreate(
                ['title->ru' => $data['title']['ru']],
                [
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'sort' => $data['sort'],
                    'status' => true,
                ]
            );
        }
    }
}
