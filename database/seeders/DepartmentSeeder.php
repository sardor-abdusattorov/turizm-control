<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            [
                'code' => 'direction',
                'name' => [
                    'ru' => 'Руководство',
                    'uz' => 'Rahbariyat',
                    'en' => 'Direction',
                ],
                'sort' => 1,
                'positions' => ['Директор', 'Заместитель директора'],
            ],
            [
                'code' => 'legal',
                'name' => [
                    'ru' => 'Юридический отдел',
                    'uz' => "Yuridik bo'lim",
                    'en' => 'Legal Department',
                ],
                'sort' => 2,
                'positions' => ['Юрист', 'Специалист'],
            ],
            [
                'code' => 'financial',
                'name' => [
                    'ru' => 'Финансовый отдел',
                    'uz' => "Moliya bo'limi",
                    'en' => 'Financial Department',
                ],
                'sort' => 3,
                'positions' => ['Финансовый менеджер', 'Специалист'],
            ],
            [
                'code' => 'accounting',
                'name' => [
                    'ru' => 'Бухгалтерия',
                    'uz' => 'Buxgalteriya',
                    'en' => 'Accounting',
                ],
                'sort' => 4,
                'positions' => ['Главный бухгалтер', 'Бухгалтер'],
            ],
            [
                'code' => 'it',
                'name' => [
                    'ru' => 'IT отдел',
                    'uz' => "IT bo'lim",
                    'en' => 'IT Department',
                ],
                'sort' => 5,
                'positions' => ['Менеджер', 'Разработчик', 'Специалист'],
            ],
        ];

        foreach ($departments as $data) {
            $department = Department::firstOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'sort' => $data['sort'],
                    'status' => true,
                ]
            );

            $positionIds = Position::query()
                ->whereIn('name->ru', $data['positions'])
                ->pluck('id')
                ->toArray();

            $department->positions()->sync($positionIds);
        }
    }
}
