<?php

namespace Database\Factories;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\HouseholdMembership>
 */
class HouseholdMembershipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'member_id' => Member::factory(),
            'role' => HouseholdRole::Adult,
            'status' => 'active',
            'joined_at' => now(),
        ];
    }
}
