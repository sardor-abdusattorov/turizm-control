<?php

namespace App\Filament\Resources\Contacts\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use App\Models\Contact;
use App\Models\Currency;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ContactForm
{
    public static function configure(Schema $schema, bool $inline = false): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('app.label.basic_information'))
                    ->collapsible()
                    ->schema([
                        Radio::make('type')
                            ->label(__('app.label.contact_type'))
                            ->options(Contact::getTypes())
                            ->default(Contact::TYPE_LEGAL)
                            ->required()
                            ->inline()
                            ->live(),

                        TranslatableTabs::make()
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('app.label.contact_name'))
                                    ->required()
                                    ->maxLength(255),

                                Textarea::make('address')
                                    ->label(__('app.label.address'))
                                    ->rows(2),
                            ]),

                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                TextInput::make('legal_form')
                                    ->label(__('app.label.legal_form'))
                                    ->visible(fn (Get $get) => $get('type') === Contact::TYPE_LEGAL)
                                    ->maxLength(50),

                                TextInput::make('oked')
                                    ->label(__('app.label.oked'))
                                    ->visible(fn (Get $get) => $get('type') === Contact::TYPE_LEGAL)
                                    ->helperText(__('app.helper.oked'))
                                    ->maxLength(5)
                                    ->rule('digits:5')
                                    ->extraInputAttributes(['inputmode' => 'numeric']),

                                TextInput::make('inn')
                                    ->label(__('app.label.inn'))
                                    ->visible(fn (Get $get) => $get('type') === Contact::TYPE_LEGAL)
                                    ->helperText(__('app.helper.inn'))
                                    ->maxLength(9)
                                    ->rule('digits:9')
                                    ->extraInputAttributes(['inputmode' => 'numeric'])
                                    ->unique('contacts', 'inn', ignoreRecord: true),

                                TextInput::make('pinfl')
                                    ->label(__('app.label.pinfl'))
                                    ->visible(fn (Get $get) => $get('type') === Contact::TYPE_INDIVIDUAL)
                                    ->required(fn (Get $get) => $get('type') === Contact::TYPE_INDIVIDUAL)
                                    ->helperText(__('app.helper.pinfl'))
                                    ->maxLength(14)
                                    ->rule('digits:14')
                                    ->extraInputAttributes(['inputmode' => 'numeric'])
                                    ->unique('contacts', 'pinfl', ignoreRecord: true),

                                TextInput::make('director_name')
                                    ->label(__('app.label.director_name'))
                                    ->visible(fn (Get $get) => $get('type') === Contact::TYPE_LEGAL)
                                    ->maxLength(255),

                                TextInput::make('contact_person')
                                    ->label(__('app.label.contact_person'))
                                    ->visible(fn (Get $get) => $get('type') === Contact::TYPE_LEGAL)
                                    ->maxLength(255),

                                TextInput::make('phone')
                                    ->label(__('app.label.phone'))
                                    ->tel()
                                    ->mask('+999 99 999 99 99')
                                    ->placeholder('+998 90 123 45 67')
                                    ->maxLength(50),

                                TextInput::make('email')
                                    ->label(__('app.label.email'))
                                    ->email()
                                    ->maxLength(255),

                                TextInput::make('website')
                                    ->label(__('app.label.website'))
                                    ->url()
                                    ->maxLength(255),
                            ]),

                        Toggle::make('status')
                            ->label(__('app.label.status'))
                            ->default(true),
                    ]),

                // A counterparty holds one account per currency (UZS / USD / EUR
                // / RUB); the generated contract pulls the account matching the
                // deal's currency. Shown for legal entities on both the standalone
                // form and the inline create-option.
                Section::make(__('app.label.bank_requisites'))
                    ->collapsible()
                    ->visible(fn (Get $get) => $get('type') === Contact::TYPE_LEGAL)
                    ->schema([
                        self::bankAccountsRepeater($inline),
                    ]),
            ]);
    }

    /**
     * The bank-accounts editor. On the standalone contact form it binds to the
     * HasMany relationship, so Filament persists the rows through the contact. In
     * the inline create-option (adding a counterparty while drafting a contract)
     * there is no saved contact to bind to yet, so it stays a plain repeater and
     * ContactForm::createWithBankAccounts() writes the rows by hand.
     */
    protected static function bankAccountsRepeater(bool $inline): Repeater
    {
        $repeater = Repeater::make('bankAccounts')
            ->hiddenLabel()
            ->addActionLabel(__('app.action.add_bank_account'))
            ->itemLabel(fn (array $state): ?string => $state['account_number'] ?? null)
            ->collapsible()
            ->cloneable()
            ->defaultItems(0)
            ->schema([
                Grid::make(['default' => 1, 'md' => 2])
                    ->schema([
                        Select::make('currency_id')
                            ->label(__('app.label.currency_single'))
                            ->options(Currency::getActive())
                            ->placeholder(__('app.label.bank_account_any_currency'))
                            ->native(false),

                        // No ->numeric() (Laravel would read max:N as "value <= N"
                        // and reject a 20-digit account) and no strict minimum: a
                        // foreign account can be short (RX France: 11 digits) while
                        // an Uzbek one is 20.
                        TextInput::make('account_number')
                            ->label(__('app.label.bank_account'))
                            ->helperText(__('app.helper.bank_account'))
                            ->required()
                            ->minLength(5) // a floor to catch typos, not a format
                            ->maxLength(34),

                        TextInput::make('bank_name')
                            ->label(__('app.label.bank_name'))
                            ->columnSpanFull()
                            ->maxLength(255),

                        // Foreign requisites carry the bank's own address; Uzbek
                        // ones leave it blank.
                        TextInput::make('bank_address')
                            ->label(__('app.label.bank_address'))
                            ->columnSpanFull()
                            ->maxLength(255),

                        TextInput::make('mfo')
                            ->label(__('app.label.mfo'))
                            ->helperText(__('app.helper.mfo'))
                            ->minLength(5)
                            ->maxLength(5),

                        TextInput::make('swift')
                            ->label(__('app.label.swift'))
                            ->maxLength(20),
                    ]),
            ]);

        // Only the standalone form has a persisted parent to bind the HasMany to;
        // the inline create-option stays a plain repeater (persisted by hand).
        if (! $inline) {
            $repeater->relationship()->orderColumn('sort');
        }

        return $repeater;
    }

    /**
     * Persist a counterparty created inline from the contract form together with
     * the bank accounts entered in the same modal. The inline repeater is a plain
     * one (no parent existed while it rendered), so its rows arrive as nested data
     * and are written to the HasMany here. Empty rows are dropped.
     *
     * @param  array<string, mixed>  $data
     */
    public static function createWithBankAccounts(array $data): Contact
    {
        $accounts = collect($data['bankAccounts'] ?? [])
            ->filter(fn (array $row): bool => filled($row['account_number'] ?? null))
            ->values()
            ->all();

        unset($data['bankAccounts']);

        $contact = Contact::create($data);
        $contact->bankAccounts()->createMany($accounts);

        return $contact;
    }
}
