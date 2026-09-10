<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_update_a_profile(): void
    {
        $this->patchJson('/api/v1/auth/me', ['name' => 'New Name', 'email' => 'new@example.com'])
            ->assertUnauthorized();
    }

    public function test_a_user_can_update_their_name_and_email(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);

        $response = $this->actingAs($user)->patchJson('/api/v1/auth/me', [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.email', 'new@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);
    }

    public function test_email_must_be_unique_excluding_the_current_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($user)
            ->patchJson('/api/v1/auth/me', ['name' => $user->name, 'email' => 'taken@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        // Keeping your own current email is fine — not a conflict with yourself.
        $this->actingAs($other)
            ->patchJson('/api/v1/auth/me', ['name' => $other->name, 'email' => 'taken@example.com'])
            ->assertOk();
    }

    public function test_name_and_email_are_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson('/api/v1/auth/me', ['name' => '', 'email' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);
    }
}
