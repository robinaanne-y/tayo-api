<?php

namespace Tests\Feature\Members;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberUpdateTest extends TestCase
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

    public function test_an_owner_can_update_a_members_name_birth_date_and_role(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);
        $child = Member::factory()->create(['name' => 'Ben']);
        $household->memberships()->create(['member_id' => $child->id, 'role' => HouseholdRole::Child]);

        $response = $this->actingAs($owner)->patchJson(
            "/api/v1/households/{$household->id}/members/{$child->id}",
            ['name' => 'Benjamin', 'birth_date' => '2015-04-01', 'role' => 'minor'],
        );

        $response->assertOk()
            ->assertJsonPath('data.name', 'Benjamin')
            ->assertJsonPath('data.role', 'minor');

        $this->assertDatabaseHas('members', ['id' => $child->id, 'name' => 'Benjamin']);
        $this->assertDatabaseHas('household_memberships', [
            'household_id' => $household->id,
            'member_id' => $child->id,
            'role' => 'minor',
        ]);
    }

    public function test_an_adult_can_update_a_member(): void
    {
        $owner = User::factory()->create();
        $adult = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);
        $this->memberFor($adult, $household, HouseholdRole::Adult);
        $child = Member::factory()->create();
        $household->memberships()->create(['member_id' => $child->id, 'role' => HouseholdRole::Child]);

        $this->actingAs($adult)->patchJson(
            "/api/v1/households/{$household->id}/members/{$child->id}",
            ['name' => 'Mia', 'role' => 'child'],
        )->assertOk();
    }

    public function test_a_minor_cannot_update_a_member(): void
    {
        $owner = User::factory()->create();
        $minor = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);
        $this->memberFor($minor, $household, HouseholdRole::Minor);
        $child = Member::factory()->create();
        $household->memberships()->create(['member_id' => $child->id, 'role' => HouseholdRole::Child]);

        $this->actingAs($minor)->patchJson(
            "/api/v1/households/{$household->id}/members/{$child->id}",
            ['name' => 'Mia', 'role' => 'child'],
        )->assertForbidden();
    }

    public function test_role_cannot_be_set_to_owner(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);
        $child = Member::factory()->create();
        $household->memberships()->create(['member_id' => $child->id, 'role' => HouseholdRole::Child]);

        $this->actingAs($owner)->patchJson(
            "/api/v1/households/{$household->id}/members/{$child->id}",
            ['name' => 'Ben', 'role' => 'owner'],
        )->assertUnprocessable()->assertJsonValidationErrors('role');
    }

    public function test_the_owners_own_role_cannot_be_changed(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $response = $this->actingAs($owner)->patchJson(
            "/api/v1/households/{$household->id}/members/{$ownerMember->id}",
            ['name' => $ownerMember->name, 'role' => 'adult'],
        );

        $response->assertOk();
        $this->assertDatabaseHas('household_memberships', [
            'household_id' => $household->id,
            'member_id' => $ownerMember->id,
            'role' => 'owner',
        ]);
    }

    public function test_a_member_outside_the_household_returns_not_found(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);
        $outsideMember = Member::factory()->create();

        $this->actingAs($owner)->patchJson(
            "/api/v1/households/{$household->id}/members/{$outsideMember->id}",
            ['name' => 'Ben', 'role' => 'child'],
        )->assertNotFound();
    }
}
