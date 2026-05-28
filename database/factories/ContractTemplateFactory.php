<?php

namespace Database\Factories;

use App\Models\ContractTemplate;
use App\Models\OrderType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractTemplate>
 */
class ContractTemplateFactory extends Factory
{
    protected $model = ContractTemplate::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);
        $body = '<p>Contract {{contract_number}} — {{contact_name}}, {{amount}} {{currency_code}}</p>';

        return [
            'order_type_id' => OrderType::factory(),
            'name' => ['ru' => $name, 'uz' => $name, 'en' => $name],
            'content' => ['ru' => $body, 'uz' => $body, 'en' => $body],
            'fields' => [],
            'sort' => 0,
            'status' => true,
        ];
    }
}
