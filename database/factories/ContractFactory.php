<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\OrderType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'order_type_id' => OrderType::factory(),
            'contact_id' => Contact::factory(),
            'currency_id' => Currency::factory(),
            'responsible_id' => User::factory(),
            'title' => ['ru' => $title, 'uz' => $title, 'en' => $title],
            'description' => null,
            'amount' => fake()->randomFloat(2, 100, 100000),
            'status' => Contract::STATUS_DRAFT,
            'deadline_at' => now()->addDays(30)->toDateString(),
            'data' => null,
        ];
    }
}
