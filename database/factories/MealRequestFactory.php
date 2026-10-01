<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\Household;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\MealRequest>
 */
class MealRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'requester_member_id' => Member::factory(),
            'requested_date' => now()->addDay()->toDateString(),
            'requested_slot' => fake()->randomElement(['breakfast', 'lunch', 'dinner']),
            'title' => fake()->words(2, true),
            'status' => RequestStatus::Pending,
        ];
    }
}
