<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Reference data + accounts — the defaults the app can't run
            // without. Contacts, orders, contracts and templates are entered
            // by hand in the UI, so their seeders are deliberately absent here
            // (ContactSeeder carries a single example row, ContractSeeder /
            // OrdersSeeder stay runnable by hand for showcases).
            PositionSeeder::class,
            DepartmentSeeder::class,
            CurrencySeeder::class,
            OrderTypeSeeder::class,
            ContractTypeSeeder::class,
            ContactSeeder::class,
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            TestUsersSeeder::class,
            SettingsSeeder::class,
            // Projects, filled from the exhibition / local-event registries
            // with their participants (fees) and the Uzbekistan Airways
            // sponsor rows — the one data set seeded ready to use.
            Exhibitions2025Seeder::class,
            InternationalProjects2026Seeder::class,
            LocalEvents2026Seeder::class,
        ]);
    }
}
