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
            // Real directories the registry importers link participants to.
            RealTourAgentsSeeder::class,
            SponsorsSeeder::class,
            // Roles must exist before the user seeders assign them.
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            TestUsersSeeder::class,
            SettingsSeeder::class,
            ContractTemplateSeeder::class,
            // Real data only: the 2025/2026 exhibition registries, the five
            // filled local events and the 26 scanned dossiers with their
            // buyruqs and foreign contractors. The demo seeders
            // (OrdersSeeder, ContractSeeder) stay runnable by hand.
            Exhibitions2025Seeder::class,
            InternationalProjects2026Seeder::class,
            LocalEvents2026Seeder::class,
            RealDossiers2025Seeder::class,
        ]);
    }
}
