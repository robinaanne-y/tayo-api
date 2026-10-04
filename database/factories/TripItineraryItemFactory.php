<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Trip;
use App\Models\TripItineraryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripItineraryItem>
 */
class TripItineraryItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'title' => fake()->sentence(3),
            'scheduled_at' => fake()->dateTimeBetween('+1 week', '+1 month'),
            'created_by_member_id' => Member::factory(),
        ];
    }
}
