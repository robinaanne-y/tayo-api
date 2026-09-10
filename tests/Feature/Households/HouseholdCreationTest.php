<?php

namespace Tests\Feature\Households;

use App\Enums\HouseholdRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HouseholdCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_create_a_household(): void
    {
        $this->postJson('/api/v1/households', ['name' => 'Santos Household'])
            ->assertUnauthorized();
    }

    public function test_an_authenticated_user_can_create_a_household_and_becomes_owner(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/households', [
            'name' => 'Santos Household',
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'Santos Household');

        $this->assertDatabaseHas('households', ['name' => 'Santos Household']);
        $this->assertDatabaseHas('members', ['user_id' => $user->id, 'name' => $user->name]);

        $household = $user->fresh()->member->households()->first();
        $this->assertSame(HouseholdRole::Owner, $household->pivot->role);
    }

    public function test_creating_a_second_household_reuses_the_existing_member_profile(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/v1/households', ['name' => 'First Household']);
        $this->actingAs($user)->postJson('/api/v1/households', ['name' => 'Second Household']);

        $this->assertDatabaseCount('members', 1);
        $this->assertDatabaseCount('household_memberships', 2);
    }

    public function test_household_name_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/households', ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_index_lists_the_users_households_with_member_counts(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/v1/households', ['name' => 'Santos Household']);

        $household = \App\Models\Household::query()->where('name', 'Santos Household')->firstOrFail();
        $household->memberships()->create([
            'member_id' => \App\Models\Member::factory()->create()->id,
            'role' => HouseholdRole::Child,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/households');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Santos Household')
            ->assertJsonPath('data.0.member_count', 2);
    }
}
