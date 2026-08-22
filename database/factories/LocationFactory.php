<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'external_id' => fake()->unique()->numberBetween(1, 100000),
            'name' => fake()->city(),
            'type' => fake()->randomElement(['Planet', 'Space station', 'Dimension', 'Cluster']),
            'dimension' => fake()->randomElement(['Dimension C-137', 'Replacement Dimension', 'unknown']),
        ];
    }
}
