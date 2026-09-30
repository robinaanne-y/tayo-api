<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\Household;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\PermissionRequest>
 */
class PermissionRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'requester_member_id' => Member::factory(),
            'type' => fake()->randomElement(['outing', 'sleepover', 'purchase', null]),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'status' => RequestStatus::Pending,
        ];
    }
}
