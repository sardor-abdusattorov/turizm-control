<?php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            [
                'name' => [
                    'ru' => 'Директор',
                    'uz' => 'Direktor',
                    'en' => 'Director',
                ],
                'sort' => 1,
            ],
            [
                'name' => [
                    'ru' => 'Заместитель директора',
                    'uz' => "Direktor o'rinbosari",
                    'en' => 'Deputy Director',
                ],
                'sort' => 2,
            ],
            [
                'name' => [
                    'ru' => 'Главный бухгалтер',
                    'uz' => 'Bosh buxgalter',
                    'en' => 'Chief Accountant',
                ],
                'sort' => 3,
            ],
            [
                'name' => [
                    'ru' => 'Бухгалтер',
                    'uz' => 'Buxgalter',
                    'en' => 'Accountant',
                ],
                'sort' => 4,
            ],
            [
                'name' => [
                    'ru' => 'Юрист',
                    'uz' => 'Yurist',
                    'en' => 'Lawyer',
                ],
                'sort' => 5,
            ],
            [
                'name' => [
                    'ru' => 'Менеджер',
                    'uz' => 'Menejer',
                    'en' => 'Manager',
                ],
                'sort' => 6,
            ],
            [
                'name' => [
                    'ru' => 'Специалист',
                    'uz' => 'Mutaxassis',
                    'en' => 'Specialist',
                ],
                'sort' => 7,
            ],
            [
                'name' => [
                    'ru' => 'Завхоз',
                    'uz' => "Xo'jalik mudiri",
                    'en' => 'Facilities Manager',
                ],
                'sort' => 8,
            ],
        ];

        foreach ($positions as $data) {
            Position::firstOrCreate(
                ['name->ru' => $data['name']['ru']],
                [
                    'name' => $data['name'],
                    'sort' => $data['sort'],
                    'status' => true,
                ]
            );
        }
    }
}
