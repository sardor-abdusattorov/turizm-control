<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Currency> */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->lexify('???'));

        return [
            'name' => ['ru' => $code, 'uz' => $code, 'en' => $code],
            'short_name' => $code,
            'status' => true,
        ];
    }
}
