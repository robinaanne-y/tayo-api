<?php

namespace Tests\Feature\Auth;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateDefaultHouseholdTest extends TestCase
{
    use RefreshDatabase;

    private function memberFor(User $user, Household $household, HouseholdRole $role): Member
    {
        $member = Member::factory()->create(['user_id' => $user->id]);

        $household->memberships()->create([
            'member_id' => $member->id,
            'role' => $role,
        ]);

        return $member;
    }

    public function test_guests_cannot_set_a_default_household(): void
    {
        $this->patchJson('/api/v1/auth/default-household', ['household_id' => 1])
            ->assertUnauthorized();
    }

    public function test_a_user_can_set_their_default_household_to_one_they_belong_to(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $user->id]);
        $this->memberFor($user, $household, HouseholdRole::Owner);

        $this->actingAs($user)
            ->patchJson('/api/v1/auth/default-household', ['household_id' => $household->id])
            ->assertOk()
            ->assertJsonPath('data.default_household_id', $household->id);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'default_household_id' => $household->id,
        ]);
    }

    public function test_a_user_cannot_set_a_household_they_do_not_belong_to_as_default(): void
    {
        $user = User::factory()->create();
        $otherOwner = User::factory()->create();
        $otherHousehold = Household::factory()->create(['created_by_user_id' => $otherOwner->id]);
        $this->memberFor($otherOwner, $otherHousehold, HouseholdRole::Owner);

        $this->actingAs($user)
            ->patchJson('/api/v1/auth/default-household', ['household_id' => $otherHousehold->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('household_id');
    }

    public function test_a_default_household_can_be_cleared(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $user->id]);
        $this->memberFor($user, $household, HouseholdRole::Owner);
        $user->update(['default_household_id' => $household->id]);

        $this->actingAs($user)
            ->patchJson('/api/v1/auth/default-household', ['household_id' => null])
            ->assertOk()
            ->assertJsonPath('data.default_household_id', null);
    }
}
