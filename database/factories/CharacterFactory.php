<?php

namespace Database\Factories;

use App\Models\Character;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Character>
 */
class CharacterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'external_id' => fake()->unique()->numberBetween(1, 100000),
            'name' => fake()->name(),
            'status' => fake()->randomElement(['Alive', 'Dead', 'unknown']),
            'species' => fake()->randomElement(['Human', 'Alien', 'Robot', 'Humanoid']),
            'type' => null,
            'gender' => fake()->randomElement(['Female', 'Male', 'Genderless', 'unknown']),
            'image' => fake()->imageUrl(300, 300),

            // Por defecto sin localizaciones, que es el caso más habitual en la API.
            // En los tests se asignan explícitamente cuando hacen falta.
            'origin_location_id' => null,
            'current_location_id' => null,
        ];
    }
}
