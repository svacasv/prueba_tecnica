<?php

namespace Database\Factories;

use App\Models\Episode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Episode>
 */
class EpisodeFactory extends Factory
{
    public function definition(): array
    {
        // Se usa un número único para generar también un código S00E00 único.
        $number = fake()->unique()->numberBetween(1, 9999);

        return [
            'external_id' => $number,
            'name' => fake()->sentence(3),
            'air_date' => fake()->date(),
            'code' => sprintf('S%02dE%02d', intdiv($number, 100), $number % 100),
        ];
    }
}
