<?php

namespace Tests\Feature\Households;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HouseholdAuthorizationTest extends TestCase
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

    public function test_a_non_member_cannot_view_another_households_data(): void
    {
        $household = Household::factory()->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->getJson("/api/v1/households/{$household->id}")
            ->assertForbidden();
    }

    public function test_a_member_can_view_their_household(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $this->actingAs($owner)
            ->getJson("/api/v1/households/{$household->id}")
            ->assertOk();
    }

    public function test_only_the_owner_can_update_the_household(): void
    {
        $owner = User::factory()->create();
        $adult = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);
        $this->memberFor($adult, $household, HouseholdRole::Adult);

        $this->actingAs($adult)
            ->patchJson("/api/v1/households/{$household->id}", ['name' => 'New Name'])
            ->assertForbidden();

        $this->actingAs($owner)
            ->patchJson("/api/v1/households/{$household->id}", ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');
    }
}
