<?php

namespace Database\Factories;

use App\Models\AddOn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AddOn>
 */
class AddOnFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'cost' => fake()->randomFloat(2, 0, 100),
            'description' => fake()->sentence(),
            'abbr' => fake()->word(),
            'fixed_price' => fake()->boolean(),
        ];
    }
}
