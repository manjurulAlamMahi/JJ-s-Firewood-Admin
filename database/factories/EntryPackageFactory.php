<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EntryPackage>
 */
class EntryPackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'entry_quantity' => $this->faker->numberBetween(1, 100),
            'price' => $this->faker->randomFloat(2, 5, 100),
            'status' => 1,
        ];
    }
}
