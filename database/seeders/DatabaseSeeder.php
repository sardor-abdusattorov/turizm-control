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
            RolesAndPermissionsSeeder::class,
            TestUsersSeeder::class,
            SettingsSeeder::class,
            OrdersSeeder::class,
            ContractTemplateSeeder::class,
            ContractSeeder::class,
        ]);
    }
}
