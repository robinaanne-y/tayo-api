<?php

namespace Tests\Feature\Trips;

use App\Enums\HouseholdRole;
use App\Models\Event;
use App\Models\Household;
use App\Models\Member;
use App\Models\Trip;
use App\Models\TripItineraryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripCalendarIntegrationTest extends TestCase
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

    public function test_a_trip_and_its_itinerary_appear_in_the_calendar_without_creating_any_event_rows(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $trip = Trip::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $ownerMember->id,
            'title' => 'Family Camping Trip',
            'start_at' => '2026-09-03',
            'end_at' => '2026-09-05',
        ]);
        $item = TripItineraryItem::factory()->create([
            'trip_id' => $trip->id,
            'created_by_member_id' => $ownerMember->id,
            'title' => 'Leave home',
            'scheduled_at' => '2026-09-03 07:00:00',
        ]);

        $this->assertSame(0, Event::count());

        $response = $this->actingAs($owner)
            ->getJson("/api/v1/households/{$household->id}/events?from=2026-09-01&to=2026-09-30")
            ->assertOk();

        $this->assertSame(0, Event::count(), 'No events row should ever be created for a trip.');

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains("trip-{$trip->id}", $ids);
        $this->assertContains("trip-itinerary-{$item->id}", $ids);

        $tripEntry = collect($response->json('data'))->firstWhere('id', "trip-{$trip->id}");
        $this->assertSame('trip', $tripEntry['type']);
        $this->assertSame('Family Camping Trip', $tripEntry['title']);
    }

    public function test_trip_entries_outside_the_requested_range_are_excluded(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        Trip::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $ownerMember->id,
            'start_at' => '2026-01-10',
            'end_at' => '2026-01-12',
        ]);

        $response = $this->actingAs($owner)
            ->getJson("/api/v1/households/{$household->id}/events?from=2026-09-01&to=2026-09-30")
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->filter(fn ($id) => str_starts_with((string) $id, 'trip-'))->isEmpty());
    }
}
