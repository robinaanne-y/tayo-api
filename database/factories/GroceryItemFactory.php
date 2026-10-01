<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\GroceryItem>
 */
class GroceryItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'name' => fake()->word(),
            'quantity' => (string) fake()->numberBetween(1, 5),
            'unit' => fake()->randomElement(['pcs', 'lbs', 'bunch', null]),
            'category' => fake()->randomElement(['Produce', 'Dairy', 'Pantry', null]),
            'added_by_member_id' => Member::factory(),
        ];
    }
}
