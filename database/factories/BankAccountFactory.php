<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BankAccount> */
class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'currency_id' => null,
            'account_number' => (string) fake()->numerify('202080########0001'),
            'bank_name' => fake()->company().' Bank',
            'mfo' => (string) fake()->numberBetween(10000, 99999),
            'swift' => null,
            'sort' => 0,
        ];
    }
}
