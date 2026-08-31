<?php

namespace Database\Factories;

use App\Enums\OrderScope;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $issued = fake()->dateTimeBetween('-6 months', 'now');

        return [
            'number' => fake()->unique()->numberBetween(1, 999).'-АФ',
            'scope' => OrderScope::PrCenter,
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'file_path' => 'uploads/files/orders/2026/06/order-'.fake()->uuid().'.docx',
            'issued_at' => $issued,
            'created_by' => User::factory(),
            'status' => true,
        ];
    }

    public function committee(): static
    {
        return $this->state(fn (): array => [
            'scope' => OrderScope::Committee,
            'basis_order_id' => null,
        ]);
    }

    public function prCenter(): static
    {
        return $this->state(fn (): array => ['scope' => OrderScope::PrCenter]);
    }
}
