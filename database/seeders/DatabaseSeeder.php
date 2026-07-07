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
            // Real directories the registry importers link participants to.
            RealTourAgentsSeeder::class,
            SponsorsSeeder::class,
            // Roles must exist before the user seeders assign them.
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            TestUsersSeeder::class,
            SettingsSeeder::class,
            OrdersSeeder::class,
            ContractTemplateSeeder::class,
            ContractSeeder::class,
            Exhibitions2025Seeder::class,
            InternationalProjects2026Seeder::class,
        ]);
    }
}
