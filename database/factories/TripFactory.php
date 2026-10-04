<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\Member;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'title' => fake()->city().' trip',
            'destination' => fake()->city(),
            'start_at' => fake()->dateTimeBetween('+1 week', '+1 month')->format('Y-m-d'),
            'created_by_member_id' => Member::factory(),
        ];
    }
}
