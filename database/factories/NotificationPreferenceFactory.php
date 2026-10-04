<?php

namespace Database\Factories;

use App\Enums\ReminderCategory;
use App\Models\Member;
use App\Models\NotificationPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationPreference>
 */
class NotificationPreferenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'category' => fake()->randomElement(ReminderCategory::cases())->value,
            'enabled' => true,
        ];
    }
}
