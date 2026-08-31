<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contact> */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => ['ru' => $name, 'uz' => $name, 'en' => $name],
            'address' => ['ru' => fake()->address(), 'uz' => fake()->address(), 'en' => fake()->address()],
            'type' => 'legal',
            'inn' => (string) fake()->randomNumber(9, true),
            'pinfl' => null,
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'contact_person' => fake()->name(),
            'status' => true,
        ];
    }
}
