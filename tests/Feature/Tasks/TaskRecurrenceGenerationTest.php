<?php

namespace Tests\Feature\Tasks;

use App\Models\Household;
use App\Models\Member;
use App\Models\RecurringRule;
use App\Models\Task;
use App\Support\RecurringTaskOccurrenceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskRecurrenceGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function makeRecurringTask(array $ruleAttributes, string $firstDueAt): Task
    {
        $household = Household::factory()->create();
        $member = Member::factory()->create();

        $rule = RecurringRule::create(array_merge([
            'interval' => 1,
            'by_day' => null,
            'ends_at' => null,
            'occurrence_count' => null,
        ], $ruleAttributes));

        return Task::factory()->create([
            'household_id' => $household->id,
            'created_by_member_id' => $member->id,
            'due_at' => $firstDueAt,
            'recurring_rule_id' => $rule->id,
        ]);
    }

    public function test_a_daily_chore_generates_into_the_lookahead_window_and_not_again_on_a_second_run(): void
    {
        $task = $this->makeRecurringTask(['frequency' => 'daily'], now()->toDateString());

        $this->travelTo(now()->addDay());
        $created = app(RecurringTaskOccurrenceGenerator::class)->generate();

        // Fills the 2-day lookahead window from "today" (one day after the
        // original due date), so more than one occurrence can appear in a
        // single run once due dates are closely spaced.
        $this->assertGreaterThanOrEqual(1, $created);
        $countAfterFirstRun = Task::where('recurring_rule_id', $task->recurring_rule_id)->count();
        $this->assertSame(1 + $created, $countAfterFirstRun);
        $this->assertTrue(
            Task::where('recurring_rule_id', $task->recurring_rule_id)
                ->whereDate('due_at', now()->toDateString())
                ->exists(),
        );

        $createdAgain = app(RecurringTaskOccurrenceGenerator::class)->generate();
        $this->assertSame(0, $createdAgain);
        $this->assertSame($countAfterFirstRun, Task::where('recurring_rule_id', $task->recurring_rule_id)->count());
    }

    public function test_weekly_by_day_wraps_to_the_following_week_after_the_last_matching_day(): void
    {
        $friday = now()->next(5); // ISO Friday
        $task = $this->makeRecurringTask([
            'frequency' => 'weekly',
            'by_day' => [1, 3, 5],
        ], $friday->toDateString());

        $this->travelTo($friday->copy()->addDays(3)); // the following Monday

        app(RecurringTaskOccurrenceGenerator::class)->generate();

        $monday = $friday->copy()->addDays(3);
        $mondayOccurrence = Task::where('recurring_rule_id', $task->recurring_rule_id)
            ->whereDate('due_at', $monday->toDateString())
            ->first();

        $this->assertNotNull($mondayOccurrence, 'Expected an occurrence generated for the wrapped-to Monday.');
        $this->assertTrue($mondayOccurrence->due_at->isMonday());
    }

    public function test_weekly_every_other_week_skips_the_off_week(): void
    {
        $firstMonday = now()->startOfWeek();
        $task = $this->makeRecurringTask([
            'frequency' => 'weekly',
            'interval' => 2,
            'by_day' => [1],
        ], $firstMonday->toDateString());

        // The following week's Monday is a "skipped" week; two weeks later
        // is the next active one.
        $this->travelTo($firstMonday->copy()->addWeeks(2)->addDay());

        app(RecurringTaskOccurrenceGenerator::class)->generate();

        $nextDue = Task::where('recurring_rule_id', $task->recurring_rule_id)
            ->orderByDesc('due_at')
            ->first()
            ->due_at;

        $this->assertTrue($nextDue->isSameDay($firstMonday->copy()->addWeeks(2)));
    }

    public function test_generation_stops_once_ends_at_is_passed(): void
    {
        $start = now()->startOfDay();
        $this->makeRecurringTask([
            'frequency' => 'daily',
            'ends_at' => $start->copy()->addDays(2),
        ], $start->toDateString());

        $this->travelTo($start->copy()->addDays(10));
        app(RecurringTaskOccurrenceGenerator::class)->generate();

        $latestDue = Task::orderByDesc('due_at')->first()->due_at;

        $this->assertTrue($latestDue->lessThanOrEqualTo($start->copy()->addDays(2)));
    }

    public function test_generation_stops_once_occurrence_count_is_reached(): void
    {
        $start = now()->startOfDay();
        $task = $this->makeRecurringTask([
            'frequency' => 'daily',
            'occurrence_count' => 3,
        ], $start->toDateString());

        $this->travelTo($start->copy()->addDays(10));
        app(RecurringTaskOccurrenceGenerator::class)->generate();

        $this->assertSame(3, Task::where('recurring_rule_id', $task->recurring_rule_id)->count());
    }

    public function test_a_week_long_gap_with_nobody_opening_the_app_catches_up_every_missed_occurrence(): void
    {
        $start = now()->startOfDay();
        $task = $this->makeRecurringTask(['frequency' => 'daily'], $start->toDateString());

        $this->travelTo($start->copy()->addDays(7));
        $created = app(RecurringTaskOccurrenceGenerator::class)->generate();

        // Catch-up should produce occurrences for each missed day up to the
        // lookahead window, not just one.
        $this->assertGreaterThanOrEqual(5, $created);
        $this->assertSame(
            1 + $created,
            Task::where('recurring_rule_id', $task->recurring_rule_id)->count(),
        );

        $dueDates = Task::where('recurring_rule_id', $task->recurring_rule_id)
            ->orderBy('due_at')
            ->pluck('due_at');

        $this->assertSame($dueDates->count(), $dueDates->unique(fn ($d) => $d->toDateString())->count());
    }

    public function test_editing_the_latest_occurrence_is_what_future_generated_occurrences_inherit(): void
    {
        $task = $this->makeRecurringTask(['frequency' => 'daily'], now()->toDateString());
        $task->update(['title' => 'Updated title']);

        $this->travelTo(now()->addDay());
        app(RecurringTaskOccurrenceGenerator::class)->generate();

        $next = Task::where('recurring_rule_id', $task->recurring_rule_id)
            ->orderByDesc('due_at')
            ->first();

        $this->assertSame('Updated title', $next->title);
    }
}
