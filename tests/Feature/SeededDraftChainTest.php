<?php

use App\Models\Contract;
use Database\Seeders\ContractSeeder;
use Database\Seeders\ContractTemplateSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('seeds every draft contract with a queued approval chain', function () {
    Storage::fake('local');

    // The main seeder carries reference data + projects and only the admin
    // user. The demo ContractSeeder (runnable by hand for showcases) is what
    // produces drafts — it needs the demo staff (TestUsersSeeder) and the
    // templates, which are no longer in the main seeder, so seed them here.
    // This guards its chain-building behaviour.
    $this->seed(DatabaseSeeder::class);
    $this->seed(TestUsersSeeder::class);
    $this->seed(ContractTemplateSeeder::class);
    $this->seed(ContractSeeder::class);

    $drafts = Contract::where('status', Contract::STATUS_DRAFT)->get();

    expect($drafts)->not->toBeEmpty();

    $drafts->each(function (Contract $draft): void {
        expect($draft->activeApprovers()->count())
            ->toBeGreaterThan(0, "Draft {$draft->number} has no approval chain");
    });
});
