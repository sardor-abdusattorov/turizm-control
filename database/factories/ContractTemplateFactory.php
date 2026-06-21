<?php

namespace Database\Factories;

use App\Models\ContractTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractTemplate>
 */
class ContractTemplateFactory extends Factory
{
    protected $model = ContractTemplate::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'template_file' => 'uploads/files/contract-templates/template-'.fake()->uuid().'.docx',
            'sort' => 0,
            'status' => true,
        ];
    }
}
