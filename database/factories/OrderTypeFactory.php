<?php

namespace Database\Factories;

use App\Models\OrderType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderType>
 */
class OrderTypeFactory extends Factory
{
    protected $model = OrderType::class;

    public function definition(): array
    {
        $title = fake()->unique()->words(2, true);

        return [
            'title' => ['ru' => $title, 'uz' => $title, 'en' => $title],
            'description' => null,
            'sort' => 0,
            'status' => true,
        ];
    }
}
