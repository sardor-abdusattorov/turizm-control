<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractItem>
 */
class ContractItemFactory extends Factory
{
    protected $model = ContractItem::class;

    public function definition(): array
    {
        $spec = fake()->sentence(4);
        $cp = fake()->company();

        return [
            'contract_id' => Contract::factory(),
            'sort' => 0,
            'specification' => ['ru' => $spec, 'uz' => $spec, 'en' => $spec],
            'counterparty' => ['ru' => $cp, 'uz' => $cp, 'en' => $cp],
            'amount' => fake()->randomFloat(2, 100, 50000),
            'agreement_ref' => fake()->bothify('Договор №###'),
        ];
    }
}
