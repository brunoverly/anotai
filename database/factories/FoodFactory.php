<?php

namespace Database\Factories;

use App\Models\Food;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Food>
 */
class FoodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'protein_g' => fake()->randomFloat(2, 0, 30),
            'carbohydrate_g' => fake()->randomFloat(2, 0, 80),
            'fat_g' => fake()->randomFloat(2, 0, 30),
            'calories_kcal' => fake()->numberBetween(50, 400),
            'serving_size_g' => 100,
            'source' => 'local',
        ];
    }
}
