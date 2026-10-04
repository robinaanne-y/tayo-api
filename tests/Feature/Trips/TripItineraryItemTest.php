<?php

namespace Tests\Feature\Trips;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\Member;
use App\Models\Trip;
use App\Models\TripItineraryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripItineraryItemTest extends TestCase
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

    public function test_an_owner_or_adult_can_add_an_itinerary_item_but_a_minor_cannot(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $trip = Trip::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $ownerMember->id,
        ]);

        $this->actingAs($minorUser)
            ->postJson("/api/v1/households/{$household->id}/trips/{$trip->id}/itinerary", [
                'title' => 'Leave home',
                'scheduled_at' => now()->addWeek()->toDateTimeString(),
            ])
            ->assertForbidden();

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/trips/{$trip->id}/itinerary", [
                'title' => 'Leave home',
                'scheduled_at' => now()->addWeek()->toDateTimeString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Leave home');
    }

    public function test_an_owner_or_adult_can_update_or_delete_an_itinerary_item_but_a_minor_cannot(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $trip = Trip::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $ownerMember->id,
        ]);
        $item = TripItineraryItem::factory()->create([
            'trip_id' => $trip->id,
            'created_by_member_id' => $ownerMember->id,
            'title' => 'Leave home',
        ]);

        $this->actingAs($minorUser)
            ->putJson("/api/v1/households/{$household->id}/trips/{$trip->id}/itinerary/{$item->id}", [
                'title' => 'Leave home early',
                'scheduled_at' => $item->scheduled_at->toDateTimeString(),
            ])
            ->assertForbidden();

        $this->actingAs($owner)
            ->putJson("/api/v1/households/{$household->id}/trips/{$trip->id}/itinerary/{$item->id}", [
                'title' => 'Leave home early',
                'scheduled_at' => $item->scheduled_at->toDateTimeString(),
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Leave home early');

        $this->actingAs($minorUser)
            ->deleteJson("/api/v1/households/{$household->id}/trips/{$trip->id}/itinerary/{$item->id}")
            ->assertForbidden();

        $this->actingAs($owner)
            ->deleteJson("/api/v1/households/{$household->id}/trips/{$trip->id}/itinerary/{$item->id}")
            ->assertNoContent();
    }

    public function test_an_itinerary_item_cannot_be_reached_through_a_different_trip(): void
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
        $item = TripItineraryItem::factory()->create([
            'trip_id' => $tripA->id,
            'created_by_member_id' => $ownerMember->id,
        ]);

        $this->actingAs($owner)
            ->deleteJson("/api/v1/households/{$household->id}/trips/{$tripB->id}/itinerary/{$item->id}")
            ->assertNotFound();
    }
}
