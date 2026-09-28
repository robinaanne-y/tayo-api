<?php

namespace Tests\Feature\Requests;

use App\Enums\HouseholdRole;
use App\Models\Event;
use App\Models\Household;
use App\Models\Member;
use App\Models\PermissionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionRequestTest extends TestCase
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

    public function test_a_household_member_can_create_a_permission_request(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $minorUser = User::factory()->create();
        $this->memberFor($owner, $household, HouseholdRole::Owner);
        $minorMember = $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $response = $this->actingAs($minorUser)->postJson("/api/v1/households/{$household->id}/requests", [
            'title' => 'Birthday Party',
            'description' => "John's House",
            'requested_start_at' => now()->addDays(3)->setTime(15, 0)->toIso8601String(),
            'requested_end_at' => now()->addDays(3)->setTime(18, 0)->toIso8601String(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Birthday Party')
            ->assertJsonPath('data.requester_name', $minorMember->name)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('permission_requests', [
            'household_id' => $household->id,
            'requester_member_id' => $minorMember->id,
            'title' => 'Birthday Party',
        ]);
    }

    public function test_a_non_member_cannot_create_a_request(): void
    {
        $household = Household::factory()->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->postJson("/api/v1/households/{$household->id}/requests", ['title' => 'Uninvited'])
            ->assertForbidden();
    }

    public function test_requested_start_and_end_must_both_be_present_or_both_absent(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $this->actingAs($owner)->postJson("/api/v1/households/{$household->id}/requests", [
            'title' => 'Half a window',
            'requested_start_at' => now()->addDay()->toIso8601String(),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('requested_end_at');

        $this->actingAs($owner)->postJson("/api/v1/households/{$household->id}/requests", [
            'title' => 'No window at all',
        ])->assertCreated();
    }

    public function test_every_household_member_can_view_household_requests(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        PermissionRequest::factory()->create([
            'household_id' => $household->id,
            'requester_member_id' => $ownerMember->id,
            'title' => 'Sleepover',
        ]);

        $this->actingAs($minorUser)
            ->getJson("/api/v1/households/{$household->id}/requests")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Sleepover');
    }

    public function test_the_requester_can_update_their_own_pending_request(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $request = PermissionRequest::factory()->create([
            'household_id' => $household->id,
            'requester_member_id' => $member->id,
            'title' => 'Original',
        ]);

        $this->actingAs($owner)
            ->putJson("/api/v1/households/{$household->id}/requests/{$request->id}", ['title' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated');
    }

    public function test_a_request_cannot_be_updated_once_responded_to(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);
        $adultUser = User::factory()->create();
        $this->memberFor($adultUser, $household, HouseholdRole::Adult);

        $request = PermissionRequest::factory()->create([
            'household_id' => $household->id,
            'requester_member_id' => $member->id,
        ]);

        $this->actingAs($adultUser)
            ->postJson("/api/v1/households/{$household->id}/requests/{$request->id}/decline")
            ->assertOk();

        $this->actingAs($owner)
            ->putJson("/api/v1/households/{$household->id}/requests/{$request->id}", ['title' => 'Too late'])
            ->assertForbidden();
    }

    public function test_only_the_requester_can_update_their_request(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $adultUser = User::factory()->create();
        $this->memberFor($adultUser, $household, HouseholdRole::Adult);

        $request = PermissionRequest::factory()->create([
            'household_id' => $household->id,
            'requester_member_id' => $member->id,
        ]);

        $this->actingAs($adultUser)
            ->putJson("/api/v1/households/{$household->id}/requests/{$request->id}", ['title' => 'Hijacked'])
            ->assertForbidden();
    }

    public function test_the_requester_can_cancel_their_own_pending_request(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $request = PermissionRequest::factory()->create([
            'household_id' => $household->id,
            'requester_member_id' => $member->id,
        ]);

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/requests/{$request->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_a_minor_cannot_approve_or_decline_a_request(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $request = PermissionRequest::factory()->create([
            'household_id' => $household->id,
            'requester_member_id' => $ownerMember->id,
        ]);

        $this->actingAs($minorUser)
            ->postJson("/api/v1/households/{$household->id}/requests/{$request->id}/approve")
            ->assertForbidden();
    }

    public function test_an_adult_cannot_approve_their_own_request(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $request = PermissionRequest::factory()->create([
            'household_id' => $household->id,
            'requester_member_id' => $member->id,
        ]);

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/requests/{$request->id}/approve")
            ->assertForbidden();
    }

    public function test_an_adult_can_approve_a_request_with_a_note(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $minorMember = $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $request = PermissionRequest::factory()->create([
            'household_id' => $household->id,
            'requester_member_id' => $minorMember->id,
        ]);

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/requests/{$request->id}/approve", [
                'response_note' => 'Have fun!',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.response_note', 'Have fun!')
            ->assertJsonPath('data.responded_by_name', $owner->member->name);
    }

    public function test_an_adult_can_decline_a_request_with_a_note(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $minorMember = $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $request = PermissionRequest::factory()->create([
            'household_id' => $household->id,
            'requester_member_id' => $minorMember->id,
        ]);

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/requests/{$request->id}/decline", [
                'response_note' => 'Not this weekend.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'declined')
            ->assertJsonPath('data.response_note', 'Not this weekend.');
    }

    public function test_a_request_cannot_be_re_approved_once_responded_to(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $minorMember = $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $request = PermissionRequest::factory()->create([
            'household_id' => $household->id,
            'requester_member_id' => $minorMember->id,
        ]);

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/requests/{$request->id}/decline")
            ->assertOk();

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/requests/{$request->id}/approve")
            ->assertForbidden();
    }

    public function test_approving_can_attach_conditions_atomically(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $minorMember = $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $request = PermissionRequest::factory()->create([
            'household_id' => $household->id,
            'requester_member_id' => $minorMember->id,
        ]);

        $response = $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/requests/{$request->id}/approve", [
                'conditions' => ['Home by 9pm', 'Call when you arrive'],
            ]);

        $response->assertOk()->assertJsonCount(2, 'data.conditions');
        $this->assertDatabaseCount('request_conditions', 2);
    }

    public function test_a_condition_can_be_added_independently_of_approve_or_decline(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $minorMember = $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $request = PermissionRequest::factory()->create([
            'household_id' => $household->id,
            'requester_member_id' => $minorMember->id,
        ]);

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/requests/{$request->id}/conditions", [
                'description' => 'Bring a jacket',
            ])
            ->assertCreated()
            ->assertJsonCount(1, 'data.conditions')
            ->assertJsonPath('data.conditions.0.description', 'Bring a jacket');
    }

    public function test_approving_with_create_event_creates_a_linked_event(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $minorMember = $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $start = now()->addDays(3)->setTime(15, 0);
        $request = PermissionRequest::factory()->create([
            'household_id' => $household->id,
            'requester_member_id' => $minorMember->id,
            'title' => 'Birthday Party',
            'requested_start_at' => $start,
            'requested_end_at' => $start->copy()->addHours(3),
        ]);

        $response = $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/requests/{$request->id}/approve", [
                'create_event' => true,
            ]);

        $response->assertOk();
        $eventId = $response->json('data.promoted_event_id');
        $this->assertNotNull($eventId);

        $event = Event::find($eventId);
        $this->assertSame('Birthday Party', $event->title);
        $this->assertSame($minorMember->id, $event->creator_member_id);
        $this->assertTrue($event->participants->pluck('id')->contains($minorMember->id));
    }

    public function test_approving_with_create_event_but_no_time_window_is_rejected(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $minorMember = $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $request = PermissionRequest::factory()->create([
            'household_id' => $household->id,
            'requester_member_id' => $minorMember->id,
            'requested_start_at' => null,
            'requested_end_at' => null,
        ]);

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/requests/{$request->id}/approve", [
                'create_event' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('create_event');
    }

    public function test_a_request_cannot_be_reached_through_a_different_household(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $otherOwner = User::factory()->create();
        $otherHousehold = Household::factory()->create(['created_by_user_id' => $otherOwner->id]);
        $this->memberFor($otherOwner, $otherHousehold, HouseholdRole::Owner);

        $request = PermissionRequest::factory()->create([
            'household_id' => $household->id,
            'requester_member_id' => $member->id,
        ]);

        $this->actingAs($otherOwner)
            ->getJson("/api/v1/households/{$otherHousehold->id}/requests/{$request->id}")
            ->assertNotFound();
    }
}
