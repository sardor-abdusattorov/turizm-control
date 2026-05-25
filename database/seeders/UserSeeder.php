<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superadmin = User::firstOrCreate(
            ['email' => 'mr.silverwind1998@gmail.com'],
            [
                'name' => 'Sardor Abdusattorov',
                'password' => bcrypt('123456'),
                'email_verified_at' => now(),
            ]
        );
        $superadmin->assignRole('super_admin');

    }
}
