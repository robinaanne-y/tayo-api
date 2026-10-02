<?php

namespace Tests\Feature\Meals;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\MealPlanItem;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealPlanItemTest extends TestCase
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

    public function test_an_adult_can_set_a_meal_for_a_slot(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $response = $this->actingAs($owner)->postJson("/api/v1/households/{$household->id}/meal-plan-items", [
            'date' => now()->addDay()->toDateString(),
            'slot' => 'dinner',
            'title' => 'Sinigang',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Sinigang')
            ->assertJsonPath('data.slot', 'dinner');

        $this->assertDatabaseHas('meal_plan_items', [
            'household_id' => $household->id,
            'title' => 'Sinigang',
        ]);
    }

    public function test_a_minor_cannot_set_a_meal(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $this->actingAs($minorUser)->postJson("/api/v1/households/{$household->id}/meal-plan-items", [
            'date' => now()->addDay()->toDateString(),
            'slot' => 'dinner',
            'title' => 'Pizza',
        ])->assertForbidden();
    }

    public function test_posting_to_the_same_date_and_slot_again_replaces_it_instead_of_erroring(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $date = now()->addDay()->toDateString();

        $this->actingAs($owner)->postJson("/api/v1/households/{$household->id}/meal-plan-items", [
            'date' => $date,
            'slot' => 'breakfast',
            'title' => 'Eggs',
        ])->assertCreated();

        $response = $this->actingAs($owner)->postJson("/api/v1/households/{$household->id}/meal-plan-items", [
            'date' => $date,
            'slot' => 'breakfast',
            'title' => 'Pancakes',
        ]);

        $response->assertOk()->assertJsonPath('data.title', 'Pancakes');
        $this->assertDatabaseCount('meal_plan_items', 1);
    }

    public function test_index_can_be_filtered_to_a_date_range(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        MealPlanItem::factory()->create([
            'household_id' => $household->id,
            'added_by_member_id' => $member->id,
            'date' => now()->startOfMonth()->addDays(2),
            'title' => 'This month',
        ]);
        MealPlanItem::factory()->create([
            'household_id' => $household->id,
            'added_by_member_id' => $member->id,
            'date' => now()->addMonth()->startOfMonth(),
            'title' => 'Next month',
        ]);

        $query = http_build_query([
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->endOfMonth()->toDateString(),
        ]);

        $this->actingAs($owner)
            ->getJson("/api/v1/households/{$household->id}/meal-plan-items?{$query}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'This month');
    }

    public function test_any_member_can_view_the_plan_but_only_an_adult_can_delete_an_item(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $item = MealPlanItem::factory()->create([
            'household_id' => $household->id,
            'added_by_member_id' => $member->id,
        ]);

        $this->actingAs($minorUser)
            ->getJson("/api/v1/households/{$household->id}/meal-plan-items")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($minorUser)
            ->deleteJson("/api/v1/households/{$household->id}/meal-plan-items/{$item->id}")
            ->assertForbidden();

        $this->actingAs($owner)
            ->deleteJson("/api/v1/households/{$household->id}/meal-plan-items/{$item->id}")
            ->assertNoContent();
    }

    public function test_when_a_meal_approver_is_set_only_they_can_manage_the_plan_not_other_adults_or_the_owner(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $otherAdultUser = User::factory()->create();
        $this->memberFor($otherAdultUser, $household, HouseholdRole::Adult);

        $approverUser = User::factory()->create();
        $approver = $this->memberFor($approverUser, $household, HouseholdRole::Adult);

        $household->update(['meal_approver_member_id' => $approver->id]);

        $payload = [
            'date' => now()->addDay()->toDateString(),
            'slot' => 'dinner',
            'title' => 'Sinigang',
        ];

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/meal-plan-items", $payload)
            ->assertForbidden();

        $this->actingAs($otherAdultUser)
            ->postJson("/api/v1/households/{$household->id}/meal-plan-items", $payload)
            ->assertForbidden();

        $this->actingAs($approverUser)
            ->postJson("/api/v1/households/{$household->id}/meal-plan-items", $payload)
            ->assertCreated();
    }
}
