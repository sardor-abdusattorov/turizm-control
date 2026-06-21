<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Currency;
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
        return [
            'contact_id' => Contact::factory(),
            'currency_id' => Currency::factory(),
            'responsible_id' => User::factory(),
            'title' => fake()->sentence(),
            'amount' => fake()->randomFloat(2, 100, 1_000_000),
            'status' => Contract::STATUS_DRAFT,
            'payment_status' => PaymentStatus::NotPaid->value,
            'paid_percent' => 0,
        ];
    }

    public function inReview(): static
    {
        return $this->state(fn () => ['status' => Contract::STATUS_IN_REVIEW]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => Contract::STATUS_APPROVED,
            'signed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => Contract::STATUS_REJECTED]);
    }
}
