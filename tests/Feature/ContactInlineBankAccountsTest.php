<?php

use App\Filament\Resources\Contacts\Schemas\ContactForm;
use App\Models\Contact;
use App\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists bank accounts entered while creating a counterparty inline', function () {
    $uzs = Currency::factory()->create(['short_name' => 'UZS']);

    $contact = ContactForm::createWithBankAccounts([
        'type' => Contact::TYPE_LEGAL,
        'name' => ['ru' => 'ООО Тест', 'uz' => 'Test MChJ', 'en' => 'Test LLC'],
        'inn' => '123456789',
        'bankAccounts' => [
            'row-a' => [
                'currency_id' => $uzs->id,
                'account_number' => '20208000900000000001',
                'bank_name' => 'Тестбанк',
                'mfo' => '00440',
                'swift' => 'TESTUZ22',
            ],
        ],
    ]);

    expect($contact->bankAccounts)->toHaveCount(1)
        ->and($contact->bankAccountFor($uzs->id)?->account_number)->toBe('20208000900000000001')
        ->and($contact->bankAccountFor($uzs->id)?->contact_id)->toBe($contact->id);
});

it('drops empty bank-account rows on inline create', function () {
    $contact = ContactForm::createWithBankAccounts([
        'type' => Contact::TYPE_LEGAL,
        'name' => ['ru' => 'ООО Пусто'],
        'inn' => '987654321',
        'bankAccounts' => [
            'row-a' => ['currency_id' => null, 'account_number' => null, 'bank_name' => null],
        ],
    ]);

    expect($contact->bankAccounts)->toHaveCount(0);
});

it('creates an inline counterparty with no bank block at all', function () {
    $contact = ContactForm::createWithBankAccounts([
        'type' => Contact::TYPE_LEGAL,
        'name' => ['ru' => 'ООО Без счетов'],
        'inn' => '456789123',
    ]);

    expect($contact->exists)->toBeTrue()
        ->and($contact->bankAccounts)->toHaveCount(0);
});
