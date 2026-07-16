<?php

use App\Models\BankAccount;
use App\Models\Contact;
use App\Models\Currency;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\ForeignPartnerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds the foreign stand/land legal entities and their accounts', function () {
    $this->seed(CurrencySeeder::class);
    $this->seed(ForeignPartnerSeeder::class);

    // 21 foreign entities, 24 accounts (20 single + EVERBLOOM's four currencies)
    expect(Contact::count())->toBe(21)
        ->and(BankAccount::count())->toBe(24);

    // foreign entity: no Uzbek INN, keyed by name, IBAN + SWIFT attached
    $ts = Contact::where('name->ru', 'Think Strawberries MENA LLC')->firstOrFail();
    expect($ts->inn)->toBeNull()
        ->and($ts->type)->toBe(Contact::TYPE_LEGAL)
        ->and($ts->bankAccounts()->first()->account_number)->toBe('AE940330000019101156207')
        ->and($ts->bankAccounts()->first()->swift)->toBe('BOMLAEAD');

    // EVERBLOOM carries four currency accounts; the ones the app knows resolve
    $eb = Contact::where('name->ru', 'ООО «EVERBLOOM PROMO»')->firstOrFail();
    expect($eb->bankAccounts()->count())->toBe(4)
        ->and($eb->inn)->toBe('240450027396')
        ->and($eb->bankAccountFor(Currency::where('short_name', 'USD')->value('id'))->account_number)
        ->toBe('KZ948562203337407044')
        // KZT is now a seeded currency, so the tenge account resolves too
        ->and($eb->bankAccountFor(Currency::where('short_name', 'KZT')->value('id'))->account_number)
        ->toBe('KZ418562203137406915');
});

it('is idempotent — re-running creates no duplicates', function () {
    $this->seed(CurrencySeeder::class);
    $this->seed(ForeignPartnerSeeder::class);
    $this->seed(ForeignPartnerSeeder::class);

    expect(Contact::count())->toBe(21)
        ->and(BankAccount::count())->toBe(24);
});
