<?php

namespace Tests\Feature\Trips;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\Member;
use App\Models\Task;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripTest extends TestCase
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

    public function test_an_owner_or_adult_can_create_a_trip_but_a_minor_cannot(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $this->actingAs($minorUser)
            ->postJson("/api/v1/households/{$household->id}/trips", [
                'title' => 'Family Camping Trip',
                'start_at' => now()->addWeek()->toDateString(),
            ])
            ->assertForbidden();

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/trips", [
                'title' => 'Family Camping Trip',
                'destination' => 'Rizal',
                'start_at' => now()->addWeek()->toDateString(),
                'end_at' => now()->addWeek()->addDays(2)->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Family Camping Trip')
            ->assertJsonPath('data.status', 'planning');
    }

    public function test_a_non_member_cannot_create_a_trip(): void
    {
        $household = Household::factory()->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->postJson("/api/v1/households/{$household->id}/trips", [
                'title' => 'Beach Trip',
                'start_at' => now()->addWeek()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_any_household_member_can_view_trips(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        Trip::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $ownerMember->id,
            'title' => 'Family Camping Trip',
        ]);

        $this->actingAs($minorUser)
            ->getJson("/api/v1/households/{$household->id}/trips")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Family Camping Trip');
    }

    public function test_creating_a_trip_syncs_participants(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $adultUser = User::factory()->create();
        $adultMember = $this->memberFor($adultUser, $household, HouseholdRole::Adult);

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/trips", [
                'title' => 'Family Camping Trip',
                'start_at' => now()->addWeek()->toDateString(),
                'participant_member_ids' => [$ownerMember->id, $adultMember->id],
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'data.participants');
    }

    public function test_an_owner_or_adult_can_update_or_delete_a_trip_but_a_minor_cannot(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $trip = Trip::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $ownerMember->id,
            'title' => 'Beach Trip',
        ]);

        $this->actingAs($minorUser)
            ->putJson("/api/v1/households/{$household->id}/trips/{$trip->id}", [
                'title' => 'Updated Beach Trip',
                'start_at' => $trip->start_at->toDateString(),
            ])
            ->assertForbidden();

        $this->actingAs($minorUser)
            ->deleteJson("/api/v1/households/{$household->id}/trips/{$trip->id}")
            ->assertForbidden();

        $this->actingAs($owner)
            ->putJson("/api/v1/households/{$household->id}/trips/{$trip->id}", [
                'title' => 'Updated Beach Trip',
                'start_at' => $trip->start_at->toDateString(),
                'status' => 'confirmed',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Beach Trip')
            ->assertJsonPath('data.status', 'confirmed');

        $this->actingAs($owner)
            ->deleteJson("/api/v1/households/{$household->id}/trips/{$trip->id}")
            ->assertNoContent();
    }

    public function test_a_trip_cannot_be_reached_through_a_different_household(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $otherOwner = User::factory()->create();
        $otherHousehold = Household::factory()->create(['created_by_user_id' => $otherOwner->id]);
        $this->memberFor($otherOwner, $otherHousehold, HouseholdRole::Owner);

        $trip = Trip::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $member->id,
        ]);

        $this->actingAs($otherOwner)
            ->deleteJson("/api/v1/households/{$otherHousehold->id}/trips/{$trip->id}")
            ->assertNotFound();
    }

    public function test_deleting_a_trip_nulls_out_its_tasks_and_grocery_items_rather_than_deleting_them(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $trip = Trip::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $ownerMember->id,
        ]);

        $task = Task::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $ownerMember->id,
            'trip_id' => $trip->id,
        ]);

        $this->actingAs($owner)
            ->deleteJson("/api/v1/households/{$household->id}/trips/{$trip->id}")
            ->assertNoContent();

        $this->assertNotNull($task->fresh());
        $this->assertNull($task->fresh()->trip_id);
    }
}
