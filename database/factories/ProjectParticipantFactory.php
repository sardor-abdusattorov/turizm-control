<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectParticipant>
 */
class ProjectParticipantFactory extends Factory
{
    protected $model = ProjectParticipant::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => 'OOO "'.strtoupper(fake()->unique()->word()).' TRAVEL"',
            'amount' => fake()->numberBetween(1, 40) * 1000000,
            'sort' => 0,
        ];
    }
}
