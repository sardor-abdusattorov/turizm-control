<?php

namespace Database\Factories;

use App\Enums\PressTourDirection;
use App\Enums\PressTourState;
use App\Models\PressTour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PressTour>
 */
class PressTourFactory extends Factory
{
    protected $model = PressTour::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $month = fake()->numberBetween(1, 12);

        return [
            'direction' => PressTourDirection::Local->value,
            'name' => 'Пресс-тур '.fake()->unique()->words(2, true),
            'place' => fake()->randomElement(['Самарканд', 'Бухара', 'Хорезм', 'Наманган']),
            'period' => PressTour::monthOptions()[$month],
            'starts_month' => $month,
            'people_count' => fake()->numberBetween(6, 36),
            'people_note' => null,
            'responsible' => fake()->name(),
            'curator' => null,
            'foreign_partner' => null,
            'state' => PressTourState::Planned->value,
            'held_on' => null,
            'notes' => null,
            'status' => true,
        ];
    }

    public function held(): static
    {
        return $this->state(fn (): array => [
            'state' => PressTourState::Held->value,
            'held_on' => now()->subWeek()->toDateString(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['state' => PressTourState::Cancelled->value]);
    }

    public function inbound(): static
    {
        return $this->state(fn (): array => [
            'direction' => PressTourDirection::Inbound->value,
            'place' => fake()->randomElement(['Египет', 'Грузия', 'Швеция']),
            'foreign_partner' => 'Посольство Узбекистана',
        ]);
    }

    public function outbound(): static
    {
        return $this->state(fn (): array => [
            'direction' => PressTourDirection::Outbound->value,
            'place' => fake()->randomElement(['Азербайджан', 'ОАЭ Дубай']),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => false]);
    }
}
