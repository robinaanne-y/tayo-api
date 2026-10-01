<?php

namespace Tests\Feature\Groceries;

use App\Enums\HouseholdRole;
use App\Models\GroceryItem;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroceryItemTest extends TestCase
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

    public function test_any_household_member_can_add_an_item(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $minorMember = $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $response = $this->actingAs($minorUser)->postJson("/api/v1/households/{$household->id}/grocery-items", [
            'name' => 'Milk',
            'quantity' => '2',
            'unit' => 'gallons',
            'category' => 'Dairy',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Milk')
            ->assertJsonPath('data.added_by_name', $minorMember->name);
    }

    public function test_a_non_member_cannot_add_an_item(): void
    {
        $household = Household::factory()->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->postJson("/api/v1/households/{$household->id}/grocery-items", ['name' => 'Bread'])
            ->assertForbidden();
    }

    public function test_any_member_can_mark_an_item_purchased_and_unpurchase_it(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $item = GroceryItem::factory()->create([
            'household_id' => $household->id,
            'added_by_member_id' => $ownerMember->id,
        ]);

        $this->actingAs($minorUser)
            ->postJson("/api/v1/households/{$household->id}/grocery-items/{$item->id}/purchase")
            ->assertOk()
            ->assertJsonPath('data.purchased_by_name', fn ($name) => $name !== null);

        $this->actingAs($minorUser)
            ->postJson("/api/v1/households/{$household->id}/grocery-items/{$item->id}/unpurchase")
            ->assertOk()
            ->assertJsonPath('data.purchased_at', null);
    }

    public function test_index_can_be_filtered_by_purchased_status(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        GroceryItem::factory()->create([
            'household_id' => $household->id,
            'added_by_member_id' => $member->id,
            'name' => 'Unpurchased item',
        ]);
        GroceryItem::factory()->create([
            'household_id' => $household->id,
            'added_by_member_id' => $member->id,
            'name' => 'Purchased item',
            'purchased_at' => now(),
            'purchased_by_member_id' => $member->id,
        ]);

        $this->actingAs($owner)
            ->getJson("/api/v1/households/{$household->id}/grocery-items?purchased=0")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Unpurchased item');

        $this->actingAs($owner)
            ->getJson("/api/v1/households/{$household->id}/grocery-items?purchased=1")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Purchased item');
    }

    public function test_any_member_can_update_or_delete_an_item_regardless_of_who_added_it(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $item = GroceryItem::factory()->create([
            'household_id' => $household->id,
            'added_by_member_id' => $ownerMember->id,
            'name' => 'Eggs',
        ]);

        $this->actingAs($minorUser)
            ->putJson("/api/v1/households/{$household->id}/grocery-items/{$item->id}", [
                'name' => 'Eggs (free range)',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Eggs (free range)');

        $this->actingAs($minorUser)
            ->deleteJson("/api/v1/households/{$household->id}/grocery-items/{$item->id}")
            ->assertNoContent();
    }

    public function test_an_item_cannot_be_reached_through_a_different_household(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $otherOwner = User::factory()->create();
        $otherHousehold = Household::factory()->create(['created_by_user_id' => $otherOwner->id]);
        $this->memberFor($otherOwner, $otherHousehold, HouseholdRole::Owner);

        $item = GroceryItem::factory()->create([
            'household_id' => $household->id,
            'added_by_member_id' => $member->id,
        ]);

        $this->actingAs($otherOwner)
            ->deleteJson("/api/v1/households/{$otherHousehold->id}/grocery-items/{$item->id}")
            ->assertNotFound();
    }
}
