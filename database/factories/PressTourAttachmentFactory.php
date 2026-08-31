<?php

namespace Database\Factories;

use App\Enums\PressTourAttachmentType;
use App\Models\PressTour;
use App\Models\PressTourAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PressTourAttachment> */
class PressTourAttachmentFactory extends Factory
{
    protected $model = PressTourAttachment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->slug(3).'.pdf';

        return [
            'press_tour_id' => PressTour::factory(),
            'type' => PressTourAttachmentType::Report->value,
            'file_path' => 'uploads/files/press-tours/2026/08/'.$name,
            'original_name' => $name,
            'size' => fake()->numberBetween(10_000, 5_000_000),
            'sort' => 0,
        ];
    }
}
