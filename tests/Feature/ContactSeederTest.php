<?php

use App\Models\BankAccount;
use App\Models\Contact;
use App\Models\Currency;
use Database\Seeders\ContactSeeder;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds every counterparty and its bank accounts from the requisites cards', function () {
    $this->seed(CurrencySeeder::class);
    $this->seed(ContactSeeder::class);

    // 72 unique companies, 80 bank accounts across them
    expect(Contact::count())->toBe(72)
        ->and(BankAccount::count())->toBe(80);

    // ZAMIN DMC carries four currency accounts, each resolved to a currency row
    $zamin = Contact::where('inn', '311343097')->firstOrFail();
    expect($zamin->bankAccounts()->count())->toBe(4)
        ->and($zamin->bankAccounts()->whereNotNull('currency_id')->count())->toBe(4)
        ->and($zamin->bankAccountFor(Currency::where('short_name', 'USD')->value('id'))->account_number)
        ->toBe('20208840407076480001');

    // ZAMIN DESTINATION is its own company (its own INN), not merged into ZAMIN DMC
    expect(Contact::where('inn', '310442497')->value('inn'))->toBe('310442497');

    // the foreign UAE entity has no INN and is keyed by name
    $wtfi = Contact::where('name->ru', 'WTFI INVESTMENTS L.L.C')->firstOrFail();
    expect($wtfi->inn)->toBeNull()
        ->and($wtfi->bankAccounts()->first()->swift)->toBe('WIOBAEADXXX');
});

it('is idempotent — re-running creates no duplicates', function () {
    $this->seed(CurrencySeeder::class);
    $this->seed(ContactSeeder::class);
    $this->seed(ContactSeeder::class);

    expect(Contact::count())->toBe(72)
        ->and(BankAccount::count())->toBe(80);
});
