<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Member>
 */
class MemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'birth_date' => fake()->date(),
            'user_id' => null,
        ];
    }
}
