<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Reference data + accounts — the defaults the app can't run
            // without. Orders, contracts and templates are entered by hand in
            // the UI, so their seeders stay runnable on their own for showcases.
            // Counterparties come from the contract requisites: ContactSeeder
            // holds the domestic tour agents, ForeignPartnerSeeder the foreign
            // stand/land legal entities.
            PositionSeeder::class,
            DepartmentSeeder::class,
            CurrencySeeder::class,
            OrderTypeSeeder::class,
            ContractTypeSeeder::class,
            ContactSeeder::class,
            ForeignPartnerSeeder::class,
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
