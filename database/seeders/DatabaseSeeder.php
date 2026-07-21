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
            // stand/land legal entities, SponsorsSeeder the carriers/venues that
            // fund the national stands.
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
            // Project shells from the exhibition / local-event registries —
            // venue, dates and costs only. Participation income is entered by
            // hand as income contracts against each project.
            Exhibitions2025Seeder::class,
            InternationalProjects2026Seeder::class,
            LocalEvents2026Seeder::class,
            // Replays database/seeders/data/contracts-snapshot.json (written
            // by `php artisan contracts:snapshot` BEFORE a rebuild) so the
            // hand-entered contracts survive migrate:fresh verbatim. A no-op
            // when no snapshot file exists.
            HandEnteredContractsSeeder::class,
        ]);
    }
}
