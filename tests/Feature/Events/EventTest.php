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

    public function test_an_event_can_be_created_with_participants(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $adultUser = User::factory()->create();
        $adultMember = $this->memberFor($adultUser, $household, HouseholdRole::Adult);

        $response = $this->actingAs($owner)->postJson("/api/v1/households/{$household->id}/events", [
            'title' => 'Family trip',
            'start_at' => now()->addHour()->toIso8601String(),
            'end_at' => now()->addHours(2)->toIso8601String(),
            'visibility' => 'household',
            'participant_member_ids' => [$ownerMember->id, $adultMember->id],
        ]);

        $response->assertCreated();
        $participantIds = collect($response->json('data.participants'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$ownerMember->id, $adultMember->id], $participantIds);

        $this->assertDatabaseHas('event_participants', [
            'event_id' => $response->json('data.id'),
            'member_id' => $adultMember->id,
        ]);
    }

    public function test_updating_an_events_participants_replaces_the_previous_list(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $adultUser = User::factory()->create();
        $adultMember = $this->memberFor($adultUser, $household, HouseholdRole::Adult);

        $event = Event::factory()->create([
            'household_id' => $household->id,
            'creator_member_id' => $ownerMember->id,
            'visibility' => 'household',
        ]);
        $event->participants()->sync([$ownerMember->id]);

        $response = $this->actingAs($owner)->putJson("/api/v1/households/{$household->id}/events/{$event->id}", [
            'title' => $event->title,
            'start_at' => $event->start_at->toIso8601String(),
            'end_at' => $event->end_at->toIso8601String(),
            'visibility' => 'household',
            'participant_member_ids' => [$adultMember->id],
        ]);

        $response->assertOk();
        $participantIds = collect($response->json('data.participants'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$adultMember->id], $participantIds);

        $this->assertDatabaseMissing('event_participants', [
            'event_id' => $event->id,
            'member_id' => $ownerMember->id,
        ]);
    }

    public function test_a_participant_from_a_different_household_is_rejected(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $otherOwner = User::factory()->create();
        $otherHousehold = Household::factory()->create(['created_by_user_id' => $otherOwner->id]);
        $outsiderMember = $this->memberFor($otherOwner, $otherHousehold, HouseholdRole::Owner);

        $this->actingAs($owner)->postJson("/api/v1/households/{$household->id}/events", [
            'title' => 'Family trip',
            'start_at' => now()->addHour()->toIso8601String(),
            'end_at' => now()->addHours(2)->toIso8601String(),
            'visibility' => 'household',
            'participant_member_ids' => [$outsiderMember->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('participant_member_ids');
    }

    public function test_an_event_can_be_created_and_updated_with_a_location(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $response = $this->actingAs($owner)->postJson("/api/v1/households/{$household->id}/events", [
            'title' => 'Family dinner',
            'location' => "Grandma's House",
            'start_at' => now()->addHour()->toIso8601String(),
            'end_at' => now()->addHours(2)->toIso8601String(),
            'visibility' => 'household',
        ]);

        $response->assertCreated()->assertJsonPath('data.location', "Grandma's House");

        $eventId = $response->json('data.id');
        $this->assertDatabaseHas('events', ['id' => $eventId, 'location' => "Grandma's House"]);

        $event = Event::find($eventId);

        $updateResponse = $this->actingAs($owner)->putJson(
            "/api/v1/households/{$household->id}/events/{$eventId}",
            [
                'title' => 'Family dinner',
                'location' => 'Uncle Bob\'s Place',
                'start_at' => $event->start_at->toIso8601String(),
                'end_at' => $event->end_at->toIso8601String(),
                'visibility' => 'household',
            ],
        );

        $updateResponse->assertOk()->assertJsonPath('data.location', "Uncle Bob's Place");
    }

    public function test_a_selected_households_event_is_visible_in_its_home_and_shared_households_but_not_others(): void
    {
        $owner = User::factory()->create();
        $householdA = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $householdA, HouseholdRole::Owner);

        $householdB = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $householdB->memberships()->create(['member_id' => $member->id, 'role' => HouseholdRole::Owner]);

        $householdD = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $householdD->memberships()->create(['member_id' => $member->id, 'role' => HouseholdRole::Owner]);

        $response = $this->actingAs($owner)->postJson("/api/v1/households/{$householdA->id}/events", [
            'title' => 'Shared Event',
            'start_at' => now()->addHour()->toIso8601String(),
            'end_at' => now()->addHours(2)->toIso8601String(),
            'visibility' => 'selected_households',
            'shared_household_ids' => [$householdB->id],
        ]);

        $response->assertCreated();
        $participantIds = collect($response->json('data.shared_households'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$householdB->id], $participantIds);

        $this->actingAs($owner)->getJson("/api/v1/households/{$householdA->id}/events")
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($owner)->getJson("/api/v1/households/{$householdB->id}/events")
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($owner)->getJson("/api/v1/households/{$householdD->id}/events")
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_another_member_of_a_shared_household_can_see_the_event_too(): void
    {
        $owner = User::factory()->create();
        $householdA = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $householdA, HouseholdRole::Owner);

        $householdB = Household::factory()->create();
        $householdB->memberships()->create(['member_id' => $member->id, 'role' => HouseholdRole::Adult]);

        $bUser = User::factory()->create();
        $this->memberFor($bUser, $householdB, HouseholdRole::Owner);

        $this->actingAs($owner)->postJson("/api/v1/households/{$householdA->id}/events", [
            'title' => 'Shared Event',
            'start_at' => now()->addHour()->toIso8601String(),
            'end_at' => now()->addHours(2)->toIso8601String(),
            'visibility' => 'selected_households',
            'shared_household_ids' => [$householdB->id],
        ])->assertCreated();

        $this->actingAs($bUser)->getJson("/api/v1/households/{$householdB->id}/events")
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Shared Event');
    }

    public function test_an_all_member_households_event_is_visible_in_every_household_the_creator_belongs_to(): void
    {
        $owner = User::factory()->create();
        $householdA = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $householdA, HouseholdRole::Owner);

        $householdB = Household::factory()->create();
        $householdB->memberships()->create(['member_id' => $member->id, 'role' => HouseholdRole::Adult]);

        $this->actingAs($owner)->postJson("/api/v1/households/{$householdA->id}/events", [
            'title' => 'Everywhere Event',
            'start_at' => now()->addHour()->toIso8601String(),
            'end_at' => now()->addHours(2)->toIso8601String(),
            'visibility' => 'all_member_households',
        ])->assertCreated();

        $this->actingAs($owner)->getJson("/api/v1/households/{$householdA->id}/events")
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($owner)->getJson("/api/v1/households/{$householdB->id}/events")
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_shared_household_ids_is_required_for_selected_households_visibility(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $this->actingAs($owner)->postJson("/api/v1/households/{$household->id}/events", [
            'title' => 'Shared Event',
            'start_at' => now()->addHour()->toIso8601String(),
            'end_at' => now()->addHours(2)->toIso8601String(),
            'visibility' => 'selected_households',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('shared_household_ids');
    }

    /**
     * Regression test: the mobile client always sends shared_household_ids
     * as an explicit [] when it isn't used (not omitted), unlike these
     * tests' other requests. An earlier version of the validation rules
     * used 'min:1' directly on the field, which runs whenever the field is
     * *present* regardless of any 'required_if' condition — rejecting
     * every single save, since [] is always present. This must succeed.
     */
    public function test_an_empty_shared_household_ids_array_is_accepted_for_non_selected_households_visibility(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $this->actingAs($owner)->postJson("/api/v1/households/{$household->id}/events", [
            'title' => 'Ordinary Event',
            'start_at' => now()->addHour()->toIso8601String(),
            'end_at' => now()->addHours(2)->toIso8601String(),
            'visibility' => 'household',
            'participant_member_ids' => [],
            'shared_household_ids' => [],
        ])->assertCreated();
    }

    public function test_a_household_the_creator_does_not_belong_to_cannot_be_selected_as_shared(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $outsiderHousehold = Household::factory()->create();

        $this->actingAs($owner)->postJson("/api/v1/households/{$household->id}/events", [
            'title' => 'Shared Event',
            'start_at' => now()->addHour()->toIso8601String(),
            'end_at' => now()->addHours(2)->toIso8601String(),
            'visibility' => 'selected_households',
            'shared_household_ids' => [$outsiderHousehold->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('shared_household_ids');
    }

    public function test_an_event_cannot_be_edited_through_a_household_it_was_only_shared_into(): void
    {
        $owner = User::factory()->create();
        $householdA = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $householdA, HouseholdRole::Owner);

        $householdB = Household::factory()->create();
        $householdB->memberships()->create(['member_id' => $member->id, 'role' => HouseholdRole::Owner]);

        $response = $this->actingAs($owner)->postJson("/api/v1/households/{$householdA->id}/events", [
            'title' => 'Shared Event',
            'start_at' => now()->addHour()->toIso8601String(),
            'end_at' => now()->addHours(2)->toIso8601String(),
            'visibility' => 'selected_households',
            'shared_household_ids' => [$householdB->id],
        ]);
        $eventId = $response->json('data.id');

        $this->actingAs($owner)
            ->deleteJson("/api/v1/households/{$householdB->id}/events/{$eventId}")
            ->assertNotFound();
    }

    public function test_a_weekly_recurring_event_generates_occurrences_on_the_chosen_days(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $start = now()->next(\Carbon\Carbon::MONDAY)->setTime(9, 0);

        $response = $this->actingAs($owner)->postJson("/api/v1/households/{$household->id}/events", [
            'title' => 'Practice',
            'start_at' => $start->toIso8601String(),
            'end_at' => $start->copy()->addHour()->toIso8601String(),
            'visibility' => 'household',
            'recurrence' => [
                'frequency' => 'weekly',
                'by_day' => [1, 3], // Mon, Wed
                'occurrence_count' => 4,
            ],
        ]);

        $response->assertCreated()->assertJsonPath('data.is_recurring', true);

        $ruleId = Event::find($response->json('data.id'))->recurring_rule_id;
        $this->assertNotNull($ruleId);

        $occurrences = Event::where('recurring_rule_id', $ruleId)->orderBy('start_at')->get();
        $this->assertCount(4, $occurrences);
        $this->assertEquals([
            $start->copy()->toIso8601String(),
            $start->copy()->addDays(2)->toIso8601String(),
            $start->copy()->addDays(7)->toIso8601String(),
            $start->copy()->addDays(9)->toIso8601String(),
        ], $occurrences->map(fn ($e) => $e->start_at->toIso8601String())->all());
    }

    public function test_a_daily_recurring_event_stops_at_the_end_date(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $start = now()->addDay()->setTime(9, 0);

        $response = $this->actingAs($owner)->postJson("/api/v1/households/{$household->id}/events", [
            'title' => 'Daily check-in',
            'start_at' => $start->toIso8601String(),
            'end_at' => $start->copy()->addMinutes(15)->toIso8601String(),
            'visibility' => 'household',
            'recurrence' => [
                'frequency' => 'daily',
                'ends_at' => $start->copy()->addDays(3)->toIso8601String(),
            ],
        ]);

        $response->assertCreated();
        $ruleId = Event::find($response->json('data.id'))->recurring_rule_id;

        $this->assertCount(4, Event::where('recurring_rule_id', $ruleId)->get());
    }

    public function test_a_monthly_recurring_event_clamps_a_31st_start_to_the_last_day_of_shorter_months(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $start = \Carbon\Carbon::create(2027, 1, 31, 9, 0);

        $response = $this->actingAs($owner)->postJson("/api/v1/households/{$household->id}/events", [
            'title' => 'Monthly bill',
            'start_at' => $start->toIso8601String(),
            'end_at' => $start->copy()->addMinutes(30)->toIso8601String(),
            'visibility' => 'household',
            'recurrence' => [
                'frequency' => 'monthly',
                'occurrence_count' => 2,
            ],
        ]);

        $response->assertCreated();
        $ruleId = Event::find($response->json('data.id'))->recurring_rule_id;

        $occurrences = Event::where('recurring_rule_id', $ruleId)->orderBy('start_at')->get();
        $this->assertCount(2, $occurrences);
        $this->assertSame('2027-02-28', $occurrences[1]->start_at->toDateString());
    }

    public function test_recurrence_requires_exactly_one_of_ends_at_or_occurrence_count(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $start = now()->addHour();
        $base = [
            'title' => 'Ambiguous',
            'start_at' => $start->toIso8601String(),
            'end_at' => $start->copy()->addHour()->toIso8601String(),
            'visibility' => 'household',
        ];

        $this->actingAs($owner)->postJson("/api/v1/households/{$household->id}/events", $base + [
            'recurrence' => [
                'frequency' => 'daily',
                'ends_at' => $start->copy()->addDays(5)->toIso8601String(),
                'occurrence_count' => 3,
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('recurrence.ends_at');

        $this->actingAs($owner)->postJson("/api/v1/households/{$household->id}/events", $base + [
            'recurrence' => ['frequency' => 'daily'],
        ])->assertUnprocessable()->assertJsonValidationErrors('recurrence.ends_at');
    }

    public function test_a_recurrence_that_would_exceed_the_occurrence_cap_is_rejected(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $start = now()->addHour();

        $this->actingAs($owner)->postJson("/api/v1/households/{$household->id}/events", [
            'title' => 'Way too many',
            'start_at' => $start->toIso8601String(),
            'end_at' => $start->copy()->addHour()->toIso8601String(),
            'visibility' => 'household',
            'recurrence' => [
                'frequency' => 'daily',
                'ends_at' => $start->copy()->addDays(300)->toIso8601String(),
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('recurrence.occurrence_count');
    }

    private function createWeeklySeries(User $owner, Household $household): array
    {
        $start = now()->next(\Carbon\Carbon::MONDAY)->setTime(9, 0);

        $response = $this->actingAs($owner)->postJson("/api/v1/households/{$household->id}/events", [
            'title' => 'Practice',
            'start_at' => $start->toIso8601String(),
            'end_at' => $start->copy()->addHour()->toIso8601String(),
            'visibility' => 'household',
            'recurrence' => [
                'frequency' => 'weekly',
                'by_day' => [1, 3],
                'occurrence_count' => 4,
            ],
        ]);

        $ruleId = Event::find($response->json('data.id'))->recurring_rule_id;

        return Event::where('recurring_rule_id', $ruleId)->orderBy('start_at')->get()->all();
    }

    public function test_editing_with_this_scope_detaches_only_that_occurrence(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $occurrences = $this->createWeeklySeries($owner, $household);
        $target = $occurrences[1];

        $this->actingAs($owner)->putJson("/api/v1/households/{$household->id}/events/{$target->id}", [
            'title' => 'Rescheduled just this once',
            'start_at' => $target->start_at->toIso8601String(),
            'end_at' => $target->end_at->toIso8601String(),
            'visibility' => 'household',
            'edit_scope' => 'this',
        ])->assertOk()->assertJsonPath('data.is_recurring', false);

        $this->assertSame('Rescheduled just this once', $target->fresh()->title);
        $this->assertNull($target->fresh()->recurring_rule_id);

        foreach ([0, 2, 3] as $i) {
            $this->assertSame('Practice', $occurrences[$i]->fresh()->title);
            $this->assertNotNull($occurrences[$i]->fresh()->recurring_rule_id);
        }
    }

    public function test_editing_with_following_scope_updates_this_and_future_occurrences_only(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $occurrences = $this->createWeeklySeries($owner, $household);
        $target = $occurrences[1];

        $this->actingAs($owner)->putJson("/api/v1/households/{$household->id}/events/{$target->id}", [
            'title' => 'New location from now on',
            'start_at' => $target->start_at->toIso8601String(),
            'end_at' => $target->end_at->toIso8601String(),
            'visibility' => 'household',
            'edit_scope' => 'following',
        ])->assertOk();

        $this->assertSame('Practice', $occurrences[0]->fresh()->title);
        foreach ([1, 2, 3] as $i) {
            $fresh = $occurrences[$i]->fresh();
            $this->assertSame('New location from now on', $fresh->title);
            $this->assertNotNull($fresh->recurring_rule_id);
        }

        // Timing is never touched by a "following" edit.
        foreach ($occurrences as $occurrence) {
            $this->assertTrue($occurrence->start_at->eq($occurrence->fresh()->start_at));
        }
    }

    public function test_deleting_with_following_scope_removes_this_and_future_occurrences(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $occurrences = $this->createWeeklySeries($owner, $household);
        $ruleId = $occurrences[0]->recurring_rule_id;
        $target = $occurrences[1];

        $this->actingAs($owner)
            ->deleteJson("/api/v1/households/{$household->id}/events/{$target->id}?scope=following")
            ->assertNoContent();

        $this->assertDatabaseHas('events', ['id' => $occurrences[0]->id]);
        $this->assertDatabaseMissing('events', ['id' => $occurrences[1]->id]);
        $this->assertDatabaseMissing('events', ['id' => $occurrences[2]->id]);
        $this->assertDatabaseMissing('events', ['id' => $occurrences[3]->id]);
        $this->assertDatabaseHas('recurring_rules', ['id' => $ruleId]);
    }

    public function test_deleting_the_entire_series_prunes_the_orphaned_recurring_rule(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $occurrences = $this->createWeeklySeries($owner, $household);
        $ruleId = $occurrences[0]->recurring_rule_id;

        $this->actingAs($owner)
            ->deleteJson("/api/v1/households/{$household->id}/events/{$occurrences[0]->id}?scope=following")
            ->assertNoContent();

        $this->assertDatabaseMissing('recurring_rules', ['id' => $ruleId]);
    }
}
