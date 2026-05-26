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
            ClientSeeder::class,
            UserSeeder::class,
            TestUsersSeeder::class,
            OrdersSeeder::class,
        ]);
    }
}
