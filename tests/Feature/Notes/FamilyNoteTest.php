<?php

namespace Tests\Feature\Notes;

use App\Enums\HouseholdRole;
use App\Models\FamilyNote;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyNoteTest extends TestCase
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

    public function test_a_household_member_can_leave_a_note(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $response = $this->actingAs($owner)->postJson("/api/v1/households/{$household->id}/notes", [
            'content' => 'Pizza tonight!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.content', 'Pizza tonight!')
            ->assertJsonPath('data.author_name', $member->name);

        $this->assertDatabaseHas('family_notes', [
            'household_id' => $household->id,
            'content' => 'Pizza tonight!',
        ]);
    }

    public function test_a_minor_can_also_leave_a_note(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $this->actingAs($minorUser)
            ->postJson("/api/v1/households/{$household->id}/notes", ['content' => 'Good luck, Anna!'])
            ->assertCreated();
    }

    public function test_a_non_member_cannot_leave_a_note(): void
    {
        $household = Household::factory()->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->postJson("/api/v1/households/{$household->id}/notes", ['content' => 'Hello'])
            ->assertForbidden();
    }

    public function test_note_content_is_required_and_limited_to_280_characters(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/notes", ['content' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/notes", ['content' => str_repeat('a', 281)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');
    }

    public function test_index_lists_active_notes_and_excludes_expired_ones(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        FamilyNote::factory()->create([
            'household_id' => $household->id,
            'author_member_id' => $member->id,
            'content' => 'Still active',
            'expires_at' => now()->addHours(2),
        ]);
        FamilyNote::factory()->create([
            'household_id' => $household->id,
            'author_member_id' => $member->id,
            'content' => 'Already expired',
            'expires_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($owner)->getJson("/api/v1/households/{$household->id}/notes");

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.content', 'Still active');
    }

    public function test_the_author_can_delete_their_own_note(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $note = FamilyNote::factory()->create([
            'household_id' => $household->id,
            'author_member_id' => $member->id,
        ]);

        $this->actingAs($owner)
            ->deleteJson("/api/v1/households/{$household->id}/notes/{$note->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('family_notes', ['id' => $note->id]);
    }

    public function test_an_owner_can_delete_someone_elses_note_but_a_minor_cannot(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $minorMember = $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $note = FamilyNote::factory()->create([
            'household_id' => $household->id,
            'author_member_id' => $minorMember->id,
        ]);

        $this->actingAs($owner)
            ->deleteJson("/api/v1/households/{$household->id}/notes/{$note->id}")
            ->assertNoContent();

        $anotherNote = FamilyNote::factory()->create([
            'household_id' => $household->id,
            'author_member_id' => $minorMember->id,
        ]);

        $secondMinorUser = User::factory()->create();
        $this->memberFor($secondMinorUser, $household, HouseholdRole::Minor);

        $this->actingAs($secondMinorUser)
            ->deleteJson("/api/v1/households/{$household->id}/notes/{$anotherNote->id}")
            ->assertForbidden();
    }

    public function test_a_note_cannot_be_deleted_through_a_different_household(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $otherOwner = User::factory()->create();
        $otherHousehold = Household::factory()->create(['created_by_user_id' => $otherOwner->id]);
        $this->memberFor($otherOwner, $otherHousehold, HouseholdRole::Owner);

        $note = FamilyNote::factory()->create([
            'household_id' => $household->id,
            'author_member_id' => $member->id,
        ]);

        $this->actingAs($otherOwner)
            ->deleteJson("/api/v1/households/{$otherHousehold->id}/notes/{$note->id}")
            ->assertNotFound();
    }
}
