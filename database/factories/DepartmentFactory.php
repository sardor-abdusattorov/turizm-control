<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'code' => fake()->unique()->slug(),
            'name' => ['ru' => $name, 'uz' => $name, 'en' => $name],
            'status' => true,
        ];
    }
}
