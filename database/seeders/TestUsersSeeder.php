<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['manager', 'legal_officer', 'financial_officer', 'accountant', 'director'];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $users = [
            [
                'name' => 'Alisher Yuldoshev',
                'email' => 'legal@test.uz',
                'department_code' => 'legal',
                'position_ru' => 'Юрист',
                'role' => 'legal_officer',
            ],
            [
                'name' => 'Dilshod Karimov',
                'email' => 'financial@test.uz',
                'department_code' => 'financial',
                'position_ru' => 'Финансовый менеджер',
                'role' => 'financial_officer',
            ],
            [
                'name' => 'Madina Saidova',
                'email' => 'accounting@test.uz',
                'department_code' => 'accounting',
                'position_ru' => 'Главный бухгалтер',
                'role' => 'accountant',
            ],
            [
                'name' => 'Rustam Nazarov',
                'email' => 'manager@test.uz',
                'department_code' => 'it',
                'position_ru' => 'Менеджер',
                'role' => 'manager',
            ],
        ];

        foreach ($users as $data) {
            $department = Department::firstWhere('code', $data['department_code']);
            $position = Position::firstWhere('name->ru', $data['position_ru']);

            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => bcrypt('123456'),
                    'email_verified_at' => now(),
                    'department_id' => $department?->id,
                    'position_id' => $position?->id,
                    'status' => true,
                ]
            );

            $user->syncRoles([$data['role']]);
        }
    }
}
