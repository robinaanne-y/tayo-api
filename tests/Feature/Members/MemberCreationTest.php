<?php

namespace Tests\Feature\Members;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberCreationTest extends TestCase
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

    public function test_an_owner_can_add_a_placeholder_member_without_an_account(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $response = $this->actingAs($owner)->postJson("/api/v1/households/{$household->id}/members", [
            'name' => 'Ben',
            'birth_date' => '2015-04-01',
            'role' => 'child',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Ben')
            ->assertJsonPath('data.is_placeholder', true);

        $this->assertDatabaseHas('members', ['name' => 'Ben', 'user_id' => null]);
    }

    public function test_an_adult_can_add_a_member(): void
    {
        $owner = User::factory()->create();
        $adult = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);
        $this->memberFor($adult, $household, HouseholdRole::Adult);

        $this->actingAs($adult)->postJson("/api/v1/households/{$household->id}/members", [
            'name' => 'Mia',
            'role' => 'minor',
        ])->assertCreated();
    }

    public function test_a_minor_cannot_add_a_member(): void
    {
        $owner = User::factory()->create();
        $minor = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);
        $this->memberFor($minor, $household, HouseholdRole::Minor);

        $this->actingAs($minor)->postJson("/api/v1/households/{$household->id}/members", [
            'name' => 'Mia',
            'role' => 'minor',
        ])->assertForbidden();
    }

    public function test_birth_date_is_optional(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $this->actingAs($owner)->postJson("/api/v1/households/{$household->id}/members", [
            'name' => 'Ben',
            'role' => 'child',
        ])->assertCreated();
    }

    public function test_role_must_be_a_valid_household_role(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $this->actingAs($owner)->postJson("/api/v1/households/{$household->id}/members", [
            'name' => 'Ben',
            'role' => 'grandparent',
        ])->assertUnprocessable()->assertJsonValidationErrors('role');
    }

    public function test_a_non_member_cannot_add_members_to_a_household(): void
    {
        $household = Household::factory()->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->postJson("/api/v1/households/{$household->id}/members", [
            'name' => 'Ben',
            'role' => 'child',
        ])->assertForbidden();
    }
}
