<?php

namespace Tests\Feature\Trips;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\Member;
use App\Models\Trip;
use App\Models\TripMemory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripMemoryTest extends TestCase
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

    public function test_any_member_including_a_minor_can_leave_a_memory(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $minorMember = $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $trip = Trip::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $ownerMember->id,
        ]);

        $this->actingAs($minorUser)
            ->postJson("/api/v1/households/{$household->id}/trips/{$trip->id}/memory", [
                'content' => 'Best trip ever!',
            ])
            ->assertOk()
            ->assertJsonPath('data.content', 'Best trip ever!')
            ->assertJsonPath('data.member_id', $minorMember->id);
    }

    public function test_posting_a_memory_again_replaces_the_members_previous_one(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $trip = Trip::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $ownerMember->id,
        ]);

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/trips/{$trip->id}/memory", [
                'content' => 'First draft',
            ])
            ->assertOk();

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/trips/{$trip->id}/memory", [
                'content' => 'Revised memory',
            ])
            ->assertOk()
            ->assertJsonPath('data.content', 'Revised memory');

        $this->assertSame(1, TripMemory::where('trip_id', $trip->id)->count());
    }

    public function test_a_member_can_delete_their_own_memory_and_an_owner_can_delete_anyones(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $minorMember = $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $otherMinorUser = User::factory()->create();
        $otherMinorMember = $this->memberFor($otherMinorUser, $household, HouseholdRole::Minor);

        $trip = Trip::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $ownerMember->id,
        ]);

        $myMemory = TripMemory::create([
            'trip_id' => $trip->id,
            'member_id' => $minorMember->id,
            'content' => 'My memory',
        ]);
        $othersMemory = TripMemory::create([
            'trip_id' => $trip->id,
            'member_id' => $otherMinorMember->id,
            'content' => "Someone else's memory",
        ]);

        $this->actingAs($minorUser)
            ->deleteJson("/api/v1/households/{$household->id}/trips/{$trip->id}/memories/{$othersMemory->id}")
            ->assertForbidden();

        $this->actingAs($minorUser)
            ->deleteJson("/api/v1/households/{$household->id}/trips/{$trip->id}/memories/{$myMemory->id}")
            ->assertNoContent();

        $this->actingAs($owner)
            ->deleteJson("/api/v1/households/{$household->id}/trips/{$trip->id}/memories/{$othersMemory->id}")
            ->assertNoContent();
    }

    public function test_a_memory_cannot_be_reached_through_a_different_trip(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $tripA = Trip::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $ownerMember->id,
        ]);
        $tripB = Trip::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $ownerMember->id,
        ]);
        $memory = TripMemory::create([
            'trip_id' => $tripA->id,
            'member_id' => $ownerMember->id,
            'content' => 'A memory',
        ]);

        $this->actingAs($owner)
            ->deleteJson("/api/v1/households/{$household->id}/trips/{$tripB->id}/memories/{$memory->id}")
            ->assertNotFound();
    }
}
