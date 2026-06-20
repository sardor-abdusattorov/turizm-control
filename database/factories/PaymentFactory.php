<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'created_by' => User::factory(),
            'percent' => $this->faker->randomFloat(2, 1, 100),
            'paid_at' => $this->faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'screenshot' => 'payments/dummy/'.$this->faker->uuid().'.png',
        ];
    }

    public function percent(float $percent): self
    {
        return $this->state(['percent' => $percent]);
    }

    public function forContract(Contract $contract): self
    {
        return $this->state(['contract_id' => $contract->id]);
    }
}
