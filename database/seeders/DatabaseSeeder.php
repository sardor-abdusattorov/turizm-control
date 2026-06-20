<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PositionSeeder::class,
            DepartmentSeeder::class,
            CurrencySeeder::class,
            OrderTypeSeeder::class,
            ContactSeeder::class,
            UserSeeder::class,
            // RolesAndPermissions must run AFTER users (so the super_admin can be wired)
            // but BEFORE TestUsersSeeder, which assigns roles to its test accounts.
            RolesAndPermissionsSeeder::class,
            TestUsersSeeder::class,
            SettingsSeeder::class,
            OrdersSeeder::class,
        ]);
    }
}
