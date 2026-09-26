<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $startAt = now()->addDay();

        return [
            'household_id' => Household::factory(),
            'creator_member_id' => Member::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'start_at' => $startAt,
            'end_at' => $startAt->copy()->addHour(),
            'visibility' => 'household',
        ];
    }
}
