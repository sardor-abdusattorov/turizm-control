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
            ContractTypeSeeder::class,
            ContactSeeder::class,
            ForeignPartnerSeeder::class,
            SponsorsSeeder::class,
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            TestUsersSeeder::class,
            SettingsSeeder::class,

            Exhibitions2025Seeder::class,
            InternationalProjects2026Seeder::class,
            LocalEvents2026Seeder::class,

            PressTours2026Seeder::class,

            HandEnteredContractsSeeder::class,
        ]);
    }
}
