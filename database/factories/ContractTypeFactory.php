<?php

namespace Database\Factories;

use App\Enums\ContractDirection;
use App\Enums\CounterpartyKind;
use App\Models\ContractType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContractType> */
class ContractTypeFactory extends Factory
{
    protected $model = ContractType::class;

    public function definition(): array
    {
        $title = fake()->unique()->words(2, true);

        return [
            'title' => ['ru' => $title, 'uz' => $title, 'en' => $title],
            'description' => null,
            'direction' => ContractDirection::Expense->value,
            'counterparty_kind' => CounterpartyKind::Contact->value,
            'sort' => 0,
            'status' => true,
        ];
    }

    public function income(): static
    {
        return $this->state(fn () => ['direction' => ContractDirection::Income->value]);
    }

    public function sponsorship(): static
    {
        return $this->state(fn () => [
            'direction' => ContractDirection::Income->value,
            'counterparty_kind' => CounterpartyKind::Sponsor->value,
        ]);
    }
}
