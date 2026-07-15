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
            ContractTypeSeeder::class,
            ContactSeeder::class,
            RealTourAgentsSeeder::class,
            SponsorsSeeder::class,
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            TestUsersSeeder::class,
            SettingsSeeder::class,
            ContractTemplateSeeder::class,
            Exhibitions2025Seeder::class,
            InternationalProjects2026Seeder::class,
            LocalEvents2026Seeder::class,
            RealDossiers2025Seeder::class,
        ]);
    }
}
