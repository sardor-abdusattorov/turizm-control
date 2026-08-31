<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Sponsor;
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
     * A sponsorship income contract: signed with a Sponsor (no contact),
     * under a sponsorship-kind ContractType.
     */
    public function sponsorship(): static
    {
        return $this->state(fn () => [
            'contact_id' => null,
            'sponsor_id' => Sponsor::factory(),
            'contract_type_id' => ContractType::factory()->sponsorship(),
        ]);
    }

    /**
     * File one scan into the contract's dossier. Use whenever a test calls
     * submit() — the workflow refuses to send a contract for approval whose
     * dossier is empty.
     */
    public function withDossier(string $body = 'fake-scan'): static
    {
        return $this->afterCreating(function (Contract $contract) use ($body): void {
            $path = "uploads/files/contract-attachments/{$contract->id}/scan.pdf";
            Storage::disk('local')->put($path, $body);

            $contract->attachments()->create([
                'file_path' => $path,
                'original_name' => 'scan.pdf',
                'size' => strlen($body),
                'sort' => 1,
            ]);
        });
    }
}
