<?php

namespace Tests\Feature\Trips;

use App\Enums\HouseholdRole;
use App\Models\GroceryItem;
use App\Models\Household;
use App\Models\Member;
use App\Models\Task;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripTaskGroceryFilterTest extends TestCase
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

    public function test_creating_a_task_with_a_trip_id_scopes_it_to_the_trip_and_the_filter_returns_only_it(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $trip = Trip::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $ownerMember->id,
        ]);

        Task::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $ownerMember->id,
            'title' => 'Regular household chore',
        ]);

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/tasks", [
                'title' => 'Book accommodation',
                'due_at' => now()->addWeek()->toDateString(),
                'trip_id' => $trip->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.trip_id', $trip->id);

        $this->actingAs($owner)
            ->getJson("/api/v1/households/{$household->id}/tasks?trip_id={$trip->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Book accommodation');

        $this->actingAs($owner)
            ->getJson("/api/v1/households/{$household->id}/tasks")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_a_trip_scoped_task_still_obeys_normal_task_completion_rules(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $minorMember = $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $otherMinorUser = User::factory()->create();
        $this->memberFor($otherMinorUser, $household, HouseholdRole::Minor);

        $trip = Trip::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $ownerMember->id,
        ]);
        $task = Task::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $ownerMember->id,
            'assigned_member_id' => $minorMember->id,
            'trip_id' => $trip->id,
        ]);

        $this->actingAs($otherMinorUser)
            ->postJson("/api/v1/households/{$household->id}/tasks/{$task->id}/complete")
            ->assertForbidden();

        $this->actingAs($minorUser)
            ->postJson("/api/v1/households/{$household->id}/tasks/{$task->id}/complete")
            ->assertOk();
    }

    public function test_creating_a_grocery_item_with_a_trip_id_scopes_it_to_the_trip_and_the_filter_returns_only_it(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $trip = Trip::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $ownerMember->id,
        ]);

        GroceryItem::factory()->create([
            'household_id' => $household->id,
            'added_by_member_id' => $ownerMember->id,
            'name' => 'Regular household item',
        ]);

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/grocery-items", [
                'name' => 'Sunscreen',
                'trip_id' => $trip->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.trip_id', $trip->id);

        $this->actingAs($owner)
            ->getJson("/api/v1/households/{$household->id}/grocery-items?trip_id={$trip->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Sunscreen');
    }

    public function test_a_trip_id_from_a_different_household_is_rejected(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $otherOwner = User::factory()->create();
        $otherHousehold = Household::factory()->create(['created_by_user_id' => $otherOwner->id]);
        $otherOwnerMember = $this->memberFor($otherOwner, $otherHousehold, HouseholdRole::Owner);

        $otherTrip = Trip::factory()->create([
            'household_id' => $otherHousehold->id,
            'created_by_member_id' => $otherOwnerMember->id,
        ]);

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/tasks", [
                'title' => 'Book accommodation',
                'due_at' => now()->addWeek()->toDateString(),
                'trip_id' => $otherTrip->id,
            ])
            ->assertUnprocessable();
    }
}
