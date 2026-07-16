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

// SQLite ignores VARCHAR lengths but MySQL does not, so assert the tight
// columns fit their limits here (phone 100, legal_form 50, inn 30, oked 20;
// account_number 34, swift 20, mfo 20) — this is what a raw seed on MySQL hits.
it('keeps every seeded value within its column limits', function () {
    $this->seed(CurrencySeeder::class);
    $this->seed(ContactSeeder::class);

    Contact::all()->each(function (Contact $c) {
        expect(mb_strlen((string) $c->phone))->toBeLessThanOrEqual(100)
            ->and(mb_strlen((string) $c->legal_form))->toBeLessThanOrEqual(50)
            ->and(mb_strlen((string) $c->inn))->toBeLessThanOrEqual(30)
            ->and(mb_strlen((string) $c->oked))->toBeLessThanOrEqual(20);
    });

    BankAccount::all()->each(function (BankAccount $a) {
        expect(mb_strlen((string) $a->account_number))->toBeLessThanOrEqual(34)
            ->and(mb_strlen((string) $a->swift))->toBeLessThanOrEqual(20)
            ->and(mb_strlen((string) $a->mfo))->toBeLessThanOrEqual(20);
    });
});
