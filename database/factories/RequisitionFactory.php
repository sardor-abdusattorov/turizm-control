<?php

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Enums\RequisitionStatus;
use App\Models\Requisition;
use App\Models\User;
use App\Services\Approvals\ApprovalChain;
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
            'status' => RequisitionStatus::Draft,
        ];
    }

    /**
     * Queue a chain of approvers, as the form does on save.
     *
     * @param  array<int, User|int>  $users
     */
    public function withChain(array $users = []): static
    {
        return $this->afterCreating(function (Requisition $requisition) use ($users): void {
            $ids = $users === []
                ? [User::factory()->create(['status' => User::STATUS_ACTIVE])->id]
                : array_map(fn ($user) => $user instanceof User ? $user->id : (int) $user, $users);

            app(ApprovalChain::class)->sync($requisition, $ids);
        });
    }

    /**
     * A chain already handed over: the first step is open, the rest queued.
     *
     * @param  array<int, User|int>  $users
     */
    public function inReview(array $users = []): static
    {
        return $this->withChain($users)->afterCreating(function (Requisition $requisition): void {
            $requisition->forceFill([
                'status' => RequisitionStatus::InReview,
                'submitted_at' => now()->subDay(),
            ])->save();

            $requisition->unsetRelation('approvals');

            app(ApprovalChain::class)
                ->nextInLine($requisition)
                ->each(fn ($approval) => $approval->startReview(Requisition::reviewDays()));

            $requisition->unsetRelation('approvals');
        });
    }

    public function overdue(array $users = []): static
    {
        return $this->inReview($users)->afterCreating(function (Requisition $requisition): void {
            $requisition->approvals()
                ->where('status', ApprovalStatus::Pending)
                ->update(['due_at' => now()->subDays(2)]);

            $requisition->unsetRelation('approvals');
        });
    }

    public function approved(array $users = []): static
    {
        return $this->inReview($users)->afterCreating(function (Requisition $requisition): void {
            $requisition->approvals()->active()->get()->each(
                fn ($approval) => $approval->approve('Согласовано.'),
            );

            $requisition->forceFill(['status' => RequisitionStatus::Approved])->save();
            $requisition->unsetRelation('approvals');
        });
    }

    public function rejected(array $users = []): static
    {
        return $this->inReview($users)->afterCreating(function (Requisition $requisition): void {
            $requisition->approvals()->active()->orderBy('order')->first()
                ?->reject('Уточните смету и приложите обоснование.');

            $requisition->forceFill(['status' => RequisitionStatus::Rejected])->save();
            $requisition->unsetRelation('approvals');
        });
    }
}
