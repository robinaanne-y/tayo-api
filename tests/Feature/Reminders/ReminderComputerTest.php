<?php

namespace Tests\Feature\Reminders;

use App\Models\GroceryItem;
use App\Models\Household;
use App\Models\MealPlanItem;
use App\Models\Member;
use App\Models\Task;
use App\Models\Trip;
use App\Support\ReminderComputer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderComputerTest extends TestCase
{
    use RefreshDatabase;

    private function householdWithMember(): array
    {
        $household = Household::factory()->create();
        $member = Member::factory()->create();
        $household->memberships()->create(['member_id' => $member->id, 'role' => 'owner']);

        return [$household, $member];
    }

    public function test_meal_planning_reminder_fires_on_or_after_thursday_when_next_week_is_empty(): void
    {
        [$household, $member] = $this->householdWithMember();

        $thursday = now()->startOfWeek()->addDays(3); // Carbon's default week start is Monday
        $this->travelTo($thursday->setTime(9, 0));

        $reminders = (new ReminderComputer)->forMember($household, $member);

        $this->assertTrue(collect($reminders)->contains('category', 'meal_planning'));
    }

    public function test_meal_planning_reminder_does_not_fire_before_thursday(): void
    {
        [$household, $member] = $this->householdWithMember();

        $monday = now()->startOfWeek(); // Carbon's default week start is Monday
        $this->travelTo($monday->setTime(9, 0));

        $reminders = (new ReminderComputer)->forMember($household, $member);

        $this->assertFalse(collect($reminders)->contains('category', 'meal_planning'));
    }

    public function test_meal_planning_reminder_does_not_fire_once_next_week_has_a_meal(): void
    {
        [$household, $member] = $this->householdWithMember();

        $thursday = now()->startOfWeek()->addDays(3);
        $this->travelTo($thursday->copy()->setTime(9, 0));

        MealPlanItem::factory()->create([
            'household_id' => $household->id,
            'date' => $thursday->copy()->addWeek()->toDateString(),
        ]);

        $reminders = (new ReminderComputer)->forMember($household, $member);

        $this->assertFalse(collect($reminders)->contains('category', 'meal_planning'));
    }

    public function test_grocery_reminder_fires_when_the_list_is_empty(): void
    {
        [$household, $member] = $this->householdWithMember();

        $reminders = (new ReminderComputer)->forMember($household, $member);

        $this->assertTrue(collect($reminders)->contains('category', 'grocery'));
    }

    public function test_grocery_reminder_does_not_fire_when_there_are_unpurchased_items(): void
    {
        [$household, $member] = $this->householdWithMember();

        GroceryItem::factory()->create(['household_id' => $household->id]);

        $reminders = (new ReminderComputer)->forMember($household, $member);

        $this->assertFalse(collect($reminders)->contains('category', 'grocery'));
    }

    public function test_grocery_reminder_fires_when_everything_is_purchased_and_stale(): void
    {
        [$household, $member] = $this->householdWithMember();

        $this->travelTo(now()->subDays(10));
        GroceryItem::factory()->create([
            'household_id' => $household->id,
            'purchased_at' => now(),
        ]);
        $this->travelBack();

        $reminders = (new ReminderComputer)->forMember($household, $member);

        $this->assertTrue(collect($reminders)->contains('category', 'grocery'));
    }

    public function test_trip_prep_reminder_fires_for_an_upcoming_trip_with_an_incomplete_checklist(): void
    {
        [$household, $member] = $this->householdWithMember();

        $trip = Trip::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $member->id,
            'start_at' => now()->addDays(3)->toDateString(),
        ]);
        Task::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $member->id,
            'trip_id' => $trip->id,
        ]);

        $reminders = (new ReminderComputer)->forMember($household, $member);

        $tripPrep = collect($reminders)->firstWhere('category', 'trip_prep');
        $this->assertNotNull($tripPrep);
        $this->assertSame($trip->id, $tripPrep['trip_id']);
    }

    public function test_trip_prep_reminder_does_not_fire_once_the_checklist_is_complete(): void
    {
        [$household, $member] = $this->householdWithMember();

        $trip = Trip::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $member->id,
            'start_at' => now()->addDays(3)->toDateString(),
        ]);
        Task::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $member->id,
            'trip_id' => $trip->id,
            'completed_at' => now(),
            'completed_by_member_id' => $member->id,
        ]);

        $reminders = (new ReminderComputer)->forMember($household, $member);

        $this->assertFalse(collect($reminders)->contains('category', 'trip_prep'));
    }

    public function test_trip_prep_reminder_does_not_fire_for_a_trip_outside_the_prep_window(): void
    {
        [$household, $member] = $this->householdWithMember();

        $trip = Trip::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $member->id,
            'start_at' => now()->addDays(30)->toDateString(),
        ]);
        Task::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $member->id,
            'trip_id' => $trip->id,
        ]);

        $reminders = (new ReminderComputer)->forMember($household, $member);

        $this->assertFalse(collect($reminders)->contains('category', 'trip_prep'));
    }
}
