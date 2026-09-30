<?php

namespace Tests\Feature\Announcements;

use App\Enums\HouseholdRole;
use App\Models\Announcement;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementTest extends TestCase
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

    public function test_an_owner_can_post_an_announcement(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $response = $this->actingAs($owner)->postJson("/api/v1/households/{$household->id}/announcements", [
            'content' => 'The camping trip is confirmed for next month.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.content', 'The camping trip is confirmed for next month.')
            ->assertJsonPath('data.author_name', $member->name);

        $this->assertDatabaseHas('announcements', [
            'household_id' => $household->id,
            'content' => 'The camping trip is confirmed for next month.',
        ]);
    }

    public function test_an_adult_can_post_an_announcement_but_a_minor_cannot(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $adultUser = User::factory()->create();
        $this->memberFor($adultUser, $household, HouseholdRole::Adult);

        $this->actingAs($adultUser)
            ->postJson("/api/v1/households/{$household->id}/announcements", ['content' => 'Reminder: rent is due Friday.'])
            ->assertCreated();

        $minorUser = User::factory()->create();
        $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $this->actingAs($minorUser)
            ->postJson("/api/v1/households/{$household->id}/announcements", ['content' => 'Hi'])
            ->assertForbidden();
    }

    public function test_a_non_member_cannot_post_an_announcement(): void
    {
        $household = Household::factory()->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->postJson("/api/v1/households/{$household->id}/announcements", ['content' => 'Hello'])
            ->assertForbidden();
    }

    public function test_announcement_content_is_required_and_limited_to_500_characters(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/announcements", ['content' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/announcements", ['content' => str_repeat('a', 501)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');
    }

    public function test_any_member_can_list_announcements(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        Announcement::factory()->create([
            'household_id' => $household->id,
            'author_member_id' => $member->id,
            'content' => 'Welcome to the household!',
        ]);

        $minorUser = User::factory()->create();
        $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $this->actingAs($minorUser)
            ->getJson("/api/v1/households/{$household->id}/announcements")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.content', 'Welcome to the household!');
    }

    public function test_the_author_can_delete_their_own_announcement(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $announcement = Announcement::factory()->create([
            'household_id' => $household->id,
            'author_member_id' => $member->id,
        ]);

        $this->actingAs($owner)
            ->deleteJson("/api/v1/households/{$household->id}/announcements/{$announcement->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
    }

    public function test_a_minor_cannot_delete_someone_elses_announcement(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $announcement = Announcement::factory()->create([
            'household_id' => $household->id,
            'author_member_id' => $member->id,
        ]);

        $minorUser = User::factory()->create();
        $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $this->actingAs($minorUser)
            ->deleteJson("/api/v1/households/{$household->id}/announcements/{$announcement->id}")
            ->assertForbidden();
    }

    public function test_an_announcement_cannot_be_deleted_through_a_different_household(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $otherOwner = User::factory()->create();
        $otherHousehold = Household::factory()->create(['created_by_user_id' => $otherOwner->id]);
        $this->memberFor($otherOwner, $otherHousehold, HouseholdRole::Owner);

        $announcement = Announcement::factory()->create([
            'household_id' => $household->id,
            'author_member_id' => $member->id,
        ]);

        $this->actingAs($otherOwner)
            ->deleteJson("/api/v1/households/{$otherHousehold->id}/announcements/{$announcement->id}")
            ->assertNotFound();
    }
}
