<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

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
            'number' => 'F-'.fake()->unique()->numberBetween(10000, 99999),
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

    /**
     * Drop a fake .docx on disk where Contract::documentExists() will see it.
     * Use whenever a test calls submit() — the workflow now refuses to send
     * a contract for approval without an actual document.
     */
    public function withDocument(string $body = 'fake-docx'): static
    {
        return $this->afterCreating(function (Contract $contract) use ($body): void {
            Storage::disk('local')->put($contract->documentPath(), $body);
        });
    }
}
