<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Announcement>
 */
class AnnouncementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'author_member_id' => Member::factory(),
            'content' => fake()->sentence(),
        ];
    }
}
