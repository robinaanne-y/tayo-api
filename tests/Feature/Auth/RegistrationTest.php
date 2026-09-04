<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Anna Santos',
            'email' => 'anna@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'anna@example.com')
            ->assertJsonStructure(['data' => ['id', 'name', 'email'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'anna@example.com']);
    }

    public function test_registration_requires_a_unique_email(): void
    {
        User::factory()->create(['email' => 'anna@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Anna Santos',
            'email' => 'anna@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Anna Santos',
            'email' => 'anna@example.com',
            'password' => 'password123',
            'password_confirmation' => 'nope',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('password');
    }
}
