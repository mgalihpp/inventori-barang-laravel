<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'price' => fake()->randomFloat(2, 1000, 1000000),
            'unit' => fake()->randomElement(['pcs', 'box', 'kg', 'liter']),
            'min_stock' => fake()->numberBetween(1, 20),
            'stock' => fake()->numberBetween(0, 100),
        ];
    }
}