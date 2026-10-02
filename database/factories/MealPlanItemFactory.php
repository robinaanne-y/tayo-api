<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\MealPlanItem>
 */
class MealPlanItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'date' => now()->addDay()->toDateString(),
            'slot' => fake()->randomElement(['breakfast', 'lunch', 'dinner']),
            'title' => fake()->words(2, true),
            'added_by_member_id' => Member::factory(),
        ];
    }
}
