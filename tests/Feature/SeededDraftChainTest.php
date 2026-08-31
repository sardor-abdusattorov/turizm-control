<?php

use App\Models\Contract;
use Database\Seeders\ContractSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('seeds every draft contract with a queued approval chain', function () {
    Storage::fake('local');

    $this->seed(DatabaseSeeder::class);
    $this->seed(ContractSeeder::class);

    $drafts = Contract::where('status', Contract::STATUS_DRAFT)->get();

    expect($drafts)->not->toBeEmpty();

    $drafts->each(function (Contract $draft): void {
        expect($draft->activeApprovers()->count())
            ->toBeGreaterThan(0, "Draft {$draft->number} has no approval chain");
    });
});
