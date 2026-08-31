<?php

namespace Database\Factories;

use App\Enums\RequisitionStatus;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Requisition>
 */
class RequisitionFactory extends Factory
{
    protected $model = Requisition::class;

    public function definition(): array
    {
        return [
            'number' => 'ЗВ-'.now()->year.'-'.fake()->unique()->numberBetween(100, 999),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'author_id' => User::factory(),
            'reviewer_id' => User::factory(),
            'status' => RequisitionStatus::Draft,
        ];
    }

    public function inReview(): static
    {
        return $this->state(fn (): array => [
            'status' => RequisitionStatus::InReview,
            'submitted_at' => now()->subDay(),
            'due_at' => now()->addDays(2),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (): array => [
            'status' => RequisitionStatus::InReview,
            'submitted_at' => now()->subDays(5),
            'due_at' => now()->subDays(2),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => RequisitionStatus::Approved,
            'submitted_at' => now()->subDays(3),
            'due_at' => now()->subDay(),
            'reviewed_at' => now()->subDay(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => RequisitionStatus::Rejected,
            'submitted_at' => now()->subDays(3),
            'reviewed_at' => now()->subDay(),
            'review_comment' => 'Уточните смету и приложите обоснование.',
        ]);
    }
}
