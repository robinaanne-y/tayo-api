<?php

namespace Tests\Feature\Reminders;

use App\Enums\HouseholdRole;
use App\Models\GroceryItem;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
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

    public function test_unset_categories_default_to_enabled(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $user->id]);
        $this->memberFor($user, $household, HouseholdRole::Owner);

        $response = $this->actingAs($user)->getJson('/api/v1/auth/notification-preferences')->assertOk();

        $categories = collect($response->json('data'))->pluck('enabled', 'category');
        $this->assertTrue($categories['meal_planning']);
        $this->assertTrue($categories['grocery']);
        $this->assertTrue($categories['trip_prep']);
    }

    public function test_a_member_can_disable_and_re_enable_their_own_category(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $user->id]);
        $this->memberFor($user, $household, HouseholdRole::Owner);

        $this->actingAs($user)
            ->patchJson('/api/v1/auth/notification-preferences', ['category' => 'grocery', 'enabled' => false])
            ->assertOk()
            ->assertJsonFragment(['category' => 'grocery', 'enabled' => false]);

        $this->actingAs($user)
            ->patchJson('/api/v1/auth/notification-preferences', ['category' => 'grocery', 'enabled' => true])
            ->assertOk()
            ->assertJsonFragment(['category' => 'grocery', 'enabled' => true]);
    }

    public function test_a_disabled_category_is_excluded_from_the_reminders_response(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $user->id]);
        $member = $this->memberFor($user, $household, HouseholdRole::Owner);

        // Make the grocery reminder condition true (empty list).
        GroceryItem::where('household_id', $household->id)->delete();

        $before = $this->actingAs($user)
            ->getJson("/api/v1/households/{$household->id}/reminders")
            ->assertOk();
        $this->assertTrue(collect($before->json('data'))->contains('category', 'grocery'));

        $this->actingAs($user)
            ->patchJson('/api/v1/auth/notification-preferences', ['category' => 'grocery', 'enabled' => false]);

        $after = $this->actingAs($user)
            ->getJson("/api/v1/households/{$household->id}/reminders")
            ->assertOk();
        $this->assertFalse(collect($after->json('data'))->contains('category', 'grocery'));
    }
}
