<?php

namespace Tests\Feature\Meals;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\MealPlanItem;
use App\Models\MealRequest;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealRequestTest extends TestCase
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

    public function test_a_household_member_can_request_a_meal(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $minorMember = $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $response = $this->actingAs($minorUser)->postJson("/api/v1/households/{$household->id}/meal-requests", [
            'requested_date' => now()->addDay()->toDateString(),
            'requested_slot' => 'lunch',
            'title' => 'Chicken Adobo',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Chicken Adobo')
            ->assertJsonPath('data.requester_name', $minorMember->name)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_a_minor_cannot_approve_or_decline_a_meal_request(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $request = MealRequest::factory()->create([
            'household_id' => $household->id,
            'requester_member_id' => $ownerMember->id,
        ]);

        $this->actingAs($minorUser)
            ->postJson("/api/v1/households/{$household->id}/meal-requests/{$request->id}/approve")
            ->assertForbidden();
    }

    public function test_an_adult_cannot_approve_their_own_meal_request(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $request = MealRequest::factory()->create([
            'household_id' => $household->id,
            'requester_member_id' => $member->id,
        ]);

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/meal-requests/{$request->id}/approve")
            ->assertForbidden();
    }

    public function test_approving_creates_a_meal_plan_item_on_the_requested_date_and_slot(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $minorMember = $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $date = now()->addDays(2)->toDateString();
        $request = MealRequest::factory()->create([
            'household_id' => $household->id,
            'requester_member_id' => $minorMember->id,
            'requested_date' => $date,
            'requested_slot' => 'dinner',
            'title' => 'Spaghetti',
        ]);

        $response = $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/meal-requests/{$request->id}/approve");

        $response->assertOk()->assertJsonPath('data.status', 'approved');
        $itemId = $response->json('data.meal_plan_item_id');
        $this->assertNotNull($itemId);

        $item = MealPlanItem::find($itemId);
        $this->assertSame('Spaghetti', $item->title);
        $this->assertSame($date, $item->date->toDateString());
        $this->assertSame('dinner', $item->slot->value);
    }

    public function test_approving_with_a_date_override_moves_it_to_that_day(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $minorMember = $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $request = MealRequest::factory()->create([
            'household_id' => $household->id,
            'requester_member_id' => $minorMember->id,
            'requested_date' => now()->addDay()->toDateString(),
            'requested_slot' => 'lunch',
        ]);

        $movedDate = now()->addDays(5)->toDateString();

        $response = $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/meal-requests/{$request->id}/approve", [
                'date' => $movedDate,
            ]);

        $response->assertOk();
        $item = MealPlanItem::find($response->json('data.meal_plan_item_id'));
        $this->assertSame($movedDate, $item->date->toDateString());
        $this->assertSame('lunch', $item->slot->value);
    }

    public function test_declining_records_a_response_note(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $minorMember = $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $request = MealRequest::factory()->create([
            'household_id' => $household->id,
            'requester_member_id' => $minorMember->id,
        ]);

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/meal-requests/{$request->id}/decline", [
                'response_note' => 'Already have dinner planned.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'declined')
            ->assertJsonPath('data.response_note', 'Already have dinner planned.');
    }

    public function test_the_requester_can_cancel_their_own_pending_request(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $request = MealRequest::factory()->create([
            'household_id' => $household->id,
            'requester_member_id' => $member->id,
        ]);

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/meal-requests/{$request->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_needs_requester_attention_clears_on_acknowledge(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $minorMember = $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $request = MealRequest::factory()->create([
            'household_id' => $household->id,
            'requester_member_id' => $minorMember->id,
        ]);

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/meal-requests/{$request->id}/approve")
            ->assertOk();

        $this->actingAs($minorUser)
            ->getJson("/api/v1/households/{$household->id}/meal-requests/{$request->id}")
            ->assertJsonPath('data.needs_requester_attention', true);

        $this->actingAs($minorUser)
            ->postJson("/api/v1/households/{$household->id}/meal-requests/{$request->id}/acknowledge")
            ->assertOk()
            ->assertJsonPath('data.needs_requester_attention', false);
    }

    public function test_when_a_meal_approver_is_set_only_they_can_act_on_a_request_not_other_adults_or_the_owner(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $otherAdultUser = User::factory()->create();
        $this->memberFor($otherAdultUser, $household, HouseholdRole::Adult);

        $approverUser = User::factory()->create();
        $approver = $this->memberFor($approverUser, $household, HouseholdRole::Adult);

        $requesterUser = User::factory()->create();
        $requesterMember = $this->memberFor($requesterUser, $household, HouseholdRole::Minor);

        $household->update(['meal_approver_member_id' => $approver->id]);

        $request = MealRequest::factory()->create([
            'household_id' => $household->id,
            'requester_member_id' => $requesterMember->id,
        ]);

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/meal-requests/{$request->id}/approve")
            ->assertForbidden();

        $this->actingAs($otherAdultUser)
            ->postJson("/api/v1/households/{$household->id}/meal-requests/{$request->id}/approve")
            ->assertForbidden();

        $this->actingAs($approverUser)
            ->postJson("/api/v1/households/{$household->id}/meal-requests/{$request->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }
}
