<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $issued = fake()->dateTimeBetween('-6 months', 'now');

        return [
            'order_type_id' => OrderType::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'file_path' => 'uploads/files/orders/2026/06/order-'.fake()->uuid().'.docx',
            'issued_at' => $issued,
            'created_by' => User::factory(),
            'status' => true,
        ];
    }
}
