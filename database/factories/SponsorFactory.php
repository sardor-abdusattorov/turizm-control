<?php

namespace Database\Factories;

use App\Models\Sponsor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sponsor>
 */
class SponsorFactory extends Factory
{
    protected $model = Sponsor::class;

    public function definition(): array
    {
        return [
            'name' => strtoupper(fake()->unique()->company()),
            'phone' => fake()->numerify('+998 ## ### ## ##'),
            'email' => fake()->companyEmail(),
            'website' => 'https://'.fake()->domainName(),
            'status' => true,
        ];
    }
}
