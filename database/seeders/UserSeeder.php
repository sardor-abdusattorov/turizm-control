<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $direction = Department::firstWhere('code', 'direction');
        $director = Position::firstWhere('name->ru', 'Директор');

        $superadmin = User::firstOrCreate(
            ['email' => 'mr.silverwind1998@gmail.com'],
            [
                'name' => 'Sardor Abdusattorov',
                'password' => bcrypt('123456'),
                'email_verified_at' => now(),
                'department_id' => $direction?->id,
                'position_id' => $director?->id,
                'status' => true,
            ]
        );

        $superadmin->assignRole('super_admin');

        if ($superadmin->avatar_url === null && ($avatar = DemoMedia::avatarFor($superadmin->email, $superadmin->name))) {
            $superadmin->update(['avatar_url' => $avatar]);
        }

        if ($direction && $direction->head_of_department === null) {
            $direction->update(['head_of_department' => $superadmin->id]);
        }
    }
}
