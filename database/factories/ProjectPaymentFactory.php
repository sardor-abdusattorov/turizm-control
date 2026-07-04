<?php

namespace Database\Factories;

use App\Models\ProjectParticipant;
use App\Models\ProjectPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectPayment>
 */
class ProjectPaymentFactory extends Factory
{
    protected $model = ProjectPayment::class;

    public function definition(): array
    {
        return [
            'project_participant_id' => ProjectParticipant::factory(),
            'amount' => fake()->numberBetween(1, 40) * 1000000,
            'paid_at' => fake()->dateTimeBetween('-3 months', 'now'),
            'screenshot' => 'uploads/images/project-payments/2025/01/'.fake()->uuid().'.jpg',
        ];
    }
}
