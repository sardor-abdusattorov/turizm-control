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
        $roles = ['manager', 'legal_officer', 'accountant', 'director', 'supply_manager'];

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
                'name' => 'Madina Saidova',
                'email' => 'accounting@test.uz',
                'department_code' => 'accounting',
                'position_ru' => 'Главный бухгалтер',
                'role' => 'accountant',
            ],
            [
                'name' => 'Rustam Nazarov',
                'email' => 'manager@test.uz',
                'department_code' => 'external',
                'position_ru' => 'Менеджер',
                'role' => 'manager',
            ],
            [
                'name' => 'Bekzod Yusupov',
                'email' => 'director@test.uz',
                'department_code' => 'direction',
                'position_ru' => 'Директор',
                'role' => 'director',
            ],
            [
                'name' => 'Jasur Karimov',
                'email' => 'supply@test.uz',
                'department_code' => 'supply',
                'position_ru' => 'Завхоз',
                'role' => 'supply_manager',
            ],
        ];

        foreach ($users as $data) {
            $department = $data['department_code']
                ? Department::firstWhere('code', $data['department_code'])
                : null;
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

            $backfill = [];

            if ($user->department_id === null && $department) {
                $backfill['department_id'] = $department->id;
            }

            if ($user->position_id === null && $position) {
                $backfill['position_id'] = $position->id;
            }

            if ($user->avatar_url === null && ($avatar = DemoMedia::avatarFor($user->email, $user->name))) {
                $backfill['avatar_url'] = $avatar;
            }

            if ($backfill !== []) {
                $user->update($backfill);
            }

            $user->syncRoles([$data['role']]);
        }

        $this->wireDefaultRecipients();
    }

    protected function wireDefaultRecipients(): void
    {
        $legal = User::firstWhere('email', 'legal@test.uz');
        $accounting = User::firstWhere('email', 'accounting@test.uz');

        $reviewers = collect([$legal, $accounting])->filter()->pluck('id')->all();

        User::firstWhere('email', 'manager@test.uz')?->defaultRecipients()->sync($reviewers);
    }
}
