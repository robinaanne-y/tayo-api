<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\Member;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'title' => fake()->sentence(3),
            'due_at' => fake()->dateTimeBetween('now', '+1 week')->format('Y-m-d'),
            'created_by_member_id' => Member::factory(),
        ];
    }
}
