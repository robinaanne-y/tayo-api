<?php

namespace Tests\Feature\Events;

use App\Enums\HouseholdRole;
use App\Models\Event;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
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

    public function test_a_household_member_can_create_a_household_visible_event(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $response = $this->actingAs($owner)->postJson("/api/v1/households/{$household->id}/events", [
            'title' => 'Family dinner',
            'start_at' => now()->addHour()->toIso8601String(),
            'end_at' => now()->addHours(2)->toIso8601String(),
            'visibility' => 'household',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Family dinner')
            ->assertJsonPath('data.creator_name', $member->name)
            ->assertJsonPath('data.visibility', 'household');

        $this->assertDatabaseHas('events', [
            'household_id' => $household->id,
            'title' => 'Family dinner',
        ]);
    }

    public function test_a_non_member_cannot_create_an_event(): void
    {
        $household = Household::factory()->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->postJson("/api/v1/households/{$household->id}/events", [
                'title' => 'Uninvited',
                'start_at' => now()->addHour()->toIso8601String(),
                'end_at' => now()->addHours(2)->toIso8601String(),
                'visibility' => 'household',
            ])
            ->assertForbidden();
    }

    public function test_title_start_and_end_are_required_and_end_must_not_precede_start(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/events", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'start_at', 'end_at', 'visibility']);

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/events", [
                'title' => 'Backwards',
                'start_at' => now()->addHours(2)->toIso8601String(),
                'end_at' => now()->addHour()->toIso8601String(),
                'visibility' => 'household',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_at');
    }

    public function test_a_private_event_is_hidden_from_other_members_but_visible_to_its_creator(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $adultUser = User::factory()->create();
        $this->memberFor($adultUser, $household, HouseholdRole::Adult);

        Event::factory()->create([
            'household_id' => $household->id,
            'creator_member_id' => $ownerMember->id,
            'title' => 'Doctor appointment',
            'visibility' => 'private',
        ]);

        $this->actingAs($owner)
            ->getJson("/api/v1/households/{$household->id}/events")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Doctor appointment');

        $this->actingAs($adultUser)
            ->getJson("/api/v1/households/{$household->id}/events")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_a_household_visible_event_is_seen_by_every_member(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        Event::factory()->create([
            'household_id' => $household->id,
            'creator_member_id' => $ownerMember->id,
            'title' => 'Family trip',
            'visibility' => 'household',
        ]);

        $this->actingAs($minorUser)
            ->getJson("/api/v1/households/{$household->id}/events")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Family trip');
    }

    public function test_index_can_be_filtered_to_a_date_range(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        Event::factory()->create([
            'household_id' => $household->id,
            'creator_member_id' => $member->id,
            'title' => 'This month',
            'start_at' => now()->startOfMonth()->addDays(2),
            'end_at' => now()->startOfMonth()->addDays(2)->addHour(),
            'visibility' => 'household',
        ]);
        Event::factory()->create([
            'household_id' => $household->id,
            'creator_member_id' => $member->id,
            'title' => 'Next month',
            'start_at' => now()->addMonth()->startOfMonth(),
            'end_at' => now()->addMonth()->startOfMonth()->addHour(),
            'visibility' => 'household',
        ]);

        $query = http_build_query([
            'from' => now()->startOfMonth()->toIso8601String(),
            'to' => now()->endOfMonth()->toIso8601String(),
        ]);

        $response = $this->actingAs($owner)->getJson(
            "/api/v1/households/{$household->id}/events?{$query}",
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'This month');
    }

    public function test_the_creator_can_update_their_own_event(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $event = Event::factory()->create([
            'household_id' => $household->id,
            'creator_member_id' => $member->id,
            'title' => 'Original title',
            'visibility' => 'household',
        ]);

        $this->actingAs($owner)
            ->putJson("/api/v1/households/{$household->id}/events/{$event->id}", [
                'title' => 'Updated title',
                'start_at' => $event->start_at->toIso8601String(),
                'end_at' => $event->end_at->toIso8601String(),
                'visibility' => 'household',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated title');
    }

    public function test_the_creator_can_delete_their_own_event(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $event = Event::factory()->create([
            'household_id' => $household->id,
            'creator_member_id' => $member->id,
        ]);

        $this->actingAs($owner)
            ->deleteJson("/api/v1/households/{$household->id}/events/{$event->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_an_owner_can_manage_someone_elses_household_visible_event_but_a_minor_cannot(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $minorMember = $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $event = Event::factory()->create([
            'household_id' => $household->id,
            'creator_member_id' => $minorMember->id,
            'visibility' => 'household',
        ]);

        $secondMinorUser = User::factory()->create();
        $this->memberFor($secondMinorUser, $household, HouseholdRole::Minor);

        $this->actingAs($secondMinorUser)
            ->deleteJson("/api/v1/households/{$household->id}/events/{$event->id}")
            ->assertForbidden();

        $this->actingAs($owner)
            ->deleteJson("/api/v1/households/{$household->id}/events/{$event->id}")
            ->assertNoContent();
    }

    public function test_an_owner_cannot_delete_someone_elses_private_event(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $adultUser = User::factory()->create();
        $adultMember = $this->memberFor($adultUser, $household, HouseholdRole::Adult);

        $event = Event::factory()->create([
            'household_id' => $household->id,
            'creator_member_id' => $adultMember->id,
            'visibility' => 'private',
        ]);

        $this->actingAs($owner)
            ->deleteJson("/api/v1/households/{$household->id}/events/{$event->id}")
            ->assertForbidden();
    }

    public function test_an_event_cannot_be_reached_through_a_different_household(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $otherOwner = User::factory()->create();
        $otherHousehold = Household::factory()->create(['created_by_user_id' => $otherOwner->id]);
        $this->memberFor($otherOwner, $otherHousehold, HouseholdRole::Owner);

        $event = Event::factory()->create([
            'household_id' => $household->id,
            'creator_member_id' => $member->id,
        ]);

        $this->actingAs($otherOwner)
            ->deleteJson("/api/v1/households/{$otherHousehold->id}/events/{$event->id}")
            ->assertNotFound();
    }
}
