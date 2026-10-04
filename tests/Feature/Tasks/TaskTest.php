<?php

namespace Tests\Feature\Tasks;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\Member;
use App\Models\RecurringRule;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
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

    public function test_an_owner_or_adult_can_create_a_task_but_a_minor_cannot(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $this->actingAs($minorUser)
            ->postJson("/api/v1/households/{$household->id}/tasks", [
                'title' => 'Take out trash',
                'due_at' => now()->addDay()->toDateString(),
            ])
            ->assertForbidden();

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/tasks", [
                'title' => 'Take out trash',
                'due_at' => now()->addDay()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Take out trash');
    }

    public function test_a_non_member_cannot_create_a_task(): void
    {
        $household = Household::factory()->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->postJson("/api/v1/households/{$household->id}/tasks", [
                'title' => 'Wash dishes',
                'due_at' => now()->addDay()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_creating_a_recurring_task_creates_exactly_one_rule_and_one_occurrence(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $this->memberFor($owner, $household, HouseholdRole::Owner);

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/tasks", [
                'title' => 'Take out trash',
                'due_at' => now()->next('Monday')->toDateString(),
                'recurrence' => [
                    'frequency' => 'weekly',
                    'by_day' => [1, 3, 5],
                    'occurrence_count' => 10,
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_recurring', true);

        $this->assertSame(1, Task::count());
        $this->assertSame(1, RecurringRule::count());
    }

    public function test_the_assignee_can_complete_and_uncomplete_their_own_task(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $minorMember = $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $task = Task::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $ownerMember->id,
            'assigned_member_id' => $minorMember->id,
        ]);

        $this->actingAs($minorUser)
            ->postJson("/api/v1/households/{$household->id}/tasks/{$task->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.completed_by_name', $minorMember->name);

        $this->actingAs($minorUser)
            ->postJson("/api/v1/households/{$household->id}/tasks/{$task->id}/uncomplete")
            ->assertOk()
            ->assertJsonPath('data.completed_at', null);
    }

    public function test_a_non_assignee_cannot_complete_someone_elses_task_but_an_owner_can(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $assigneeUser = User::factory()->create();
        $assigneeMember = $this->memberFor($assigneeUser, $household, HouseholdRole::Adult);

        $otherMinorUser = User::factory()->create();
        $this->memberFor($otherMinorUser, $household, HouseholdRole::Minor);

        $task = Task::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $ownerMember->id,
            'assigned_member_id' => $assigneeMember->id,
        ]);

        $this->actingAs($otherMinorUser)
            ->postJson("/api/v1/households/{$household->id}/tasks/{$task->id}/complete")
            ->assertForbidden();

        $this->actingAs($owner)
            ->postJson("/api/v1/households/{$household->id}/tasks/{$task->id}/complete")
            ->assertOk();
    }

    public function test_an_owner_or_adult_can_update_or_delete_a_task_but_a_minor_cannot(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $minorUser = User::factory()->create();
        $this->memberFor($minorUser, $household, HouseholdRole::Minor);

        $task = Task::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $ownerMember->id,
            'title' => 'Vacuum',
        ]);

        $this->actingAs($minorUser)
            ->putJson("/api/v1/households/{$household->id}/tasks/{$task->id}", [
                'title' => 'Vacuum the living room',
                'due_at' => now()->addDay()->toDateString(),
            ])
            ->assertForbidden();

        $this->actingAs($minorUser)
            ->deleteJson("/api/v1/households/{$household->id}/tasks/{$task->id}")
            ->assertForbidden();

        $this->actingAs($owner)
            ->putJson("/api/v1/households/{$household->id}/tasks/{$task->id}", [
                'title' => 'Vacuum the living room',
                'due_at' => now()->addDay()->toDateString(),
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Vacuum the living room');

        $this->actingAs($owner)
            ->deleteJson("/api/v1/households/{$household->id}/tasks/{$task->id}")
            ->assertNoContent();
    }

    public function test_index_can_be_filtered_by_assignee_and_status(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $ownerMember = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $assigneeUser = User::factory()->create();
        $assigneeMember = $this->memberFor($assigneeUser, $household, HouseholdRole::Adult);

        Task::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $ownerMember->id,
            'assigned_member_id' => $assigneeMember->id,
            'title' => 'Assigned pending',
        ]);
        Task::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $ownerMember->id,
            'title' => 'Unassigned, completed',
            'completed_at' => now(),
            'completed_by_member_id' => $ownerMember->id,
        ]);

        $this->actingAs($owner)
            ->getJson("/api/v1/households/{$household->id}/tasks?assignee_member_id={$assigneeMember->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Assigned pending');

        $this->actingAs($owner)
            ->getJson("/api/v1/households/{$household->id}/tasks?status=completed")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Unassigned, completed');

        $this->actingAs($owner)
            ->getJson("/api/v1/households/{$household->id}/tasks?status=pending")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Assigned pending');
    }

    public function test_a_task_cannot_be_reached_through_a_different_household(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by_user_id' => $owner->id]);
        $member = $this->memberFor($owner, $household, HouseholdRole::Owner);

        $otherOwner = User::factory()->create();
        $otherHousehold = Household::factory()->create(['created_by_user_id' => $otherOwner->id]);
        $this->memberFor($otherOwner, $otherHousehold, HouseholdRole::Owner);

        $task = Task::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $member->id,
        ]);

        $this->actingAs($otherOwner)
            ->deleteJson("/api/v1/households/{$otherHousehold->id}/tasks/{$task->id}")
            ->assertNotFound();

        $this->actingAs($otherOwner)
            ->postJson("/api/v1/households/{$otherHousehold->id}/tasks/{$task->id}/complete")
            ->assertNotFound();
    }
}
