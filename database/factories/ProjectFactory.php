<?php

namespace Database\Factories;

use App\Enums\ProjectType;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $starts = fake()->dateTimeBetween('-6 months', '+6 months');

        return [
            'type' => fake()->randomElement(ProjectType::cases())->value,
            'name' => strtoupper(fake()->unique()->lexify('EXPO-????')).'-2025',
            'starts_on' => $starts,
            'ends_on' => (clone $starts)->modify('+3 days'),
            'area_sqm' => fake()->randomFloat(2, 20, 200),
            'area_cost' => fake()->randomFloat(2, 5000, 90000),
            'area_is_free' => false,
            'stand_cost' => fake()->randomFloat(2, 10000, 120000),
            'status' => true,
        ];
    }

    public function internal(): static
    {
        return $this->state(['type' => ProjectType::Internal->value]);
    }

    public function international(): static
    {
        return $this->state(['type' => ProjectType::International->value]);
    }
}
