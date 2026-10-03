<?php

namespace App\Support;

use App\Models\RecurringRule;
use App\Models\Task;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Keeps recurring chores generating one occurrence at a time, driven by the
 * tasks:generate-occurrences scheduled command -- deliberately NOT eager
 * like Events (see RecurrenceGenerator::generate()), since a chore has no
 * natural "end of range" to bound an eager expansion against, and the whole
 * point of Phase 6 is that generation must keep happening even if nobody
 * opens the app for a week (see docs/ROADMAP.md Phase 6).
 */
class RecurringTaskOccurrenceGenerator
{
    private const LOOKAHEAD_DAYS = 2;

    private const MAX_CATCHUP_PER_RULE = 60;

    public function generate(): int
    {
        $created = 0;

        $ruleIds = RecurringRule::query()->whereHas('tasks')->pluck('id');

        foreach ($ruleIds as $ruleId) {
            try {
                $created += $this->generateForRule(RecurringRule::findOrFail($ruleId));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $created;
    }

    private function generateForRule(RecurringRule $rule): int
    {
        $firstOccurrence = Task::where('recurring_rule_id', $rule->id)->orderBy('due_at')->first();

        if ($firstOccurrence === null || $firstOccurrence->due_at === null) {
            return 0;
        }

        $latestOccurrence = Task::where('recurring_rule_id', $rule->id)->orderByDesc('due_at')->first();
        $anchorWeekStart = $firstOccurrence->due_at->copy()->startOfWeek(CarbonInterface::MONDAY);
        $occurrenceCount = Task::where('recurring_rule_id', $rule->id)->count();
        $lookaheadLimit = Carbon::today()->addDays(self::LOOKAHEAD_DAYS);
        $created = 0;

        for ($i = 0; $i < self::MAX_CATCHUP_PER_RULE; $i++) {
            $nextDue = (new RecurrenceGenerator)->nextOccurrenceDate(
                $latestOccurrence->due_at,
                $rule->frequency,
                $rule->interval,
                $rule->by_day,
                $anchorWeekStart,
            );

            if ($rule->ends_at !== null && $nextDue->greaterThan($rule->ends_at->copy()->endOfDay())) {
                break;
            }

            if ($rule->occurrence_count !== null && $occurrenceCount >= $rule->occurrence_count) {
                break;
            }

            if ($nextDue->greaterThan($lookaheadLimit)) {
                break;
            }

            $alreadyExists = Task::where('recurring_rule_id', $rule->id)
                ->whereDate('due_at', $nextDue)
                ->exists();

            if (! $alreadyExists) {
                try {
                    $latestOccurrence = DB::transaction(fn () => Task::create([
                        'household_id' => $latestOccurrence->household_id,
                        'title' => $latestOccurrence->title,
                        'description' => $latestOccurrence->description,
                        'due_at' => $nextDue,
                        'created_by_member_id' => $latestOccurrence->created_by_member_id,
                        'assigned_member_id' => $latestOccurrence->assigned_member_id,
                        'recurring_rule_id' => $rule->id,
                    ]));
                    $created++;
                } catch (QueryException) {
                    // A concurrent run already created this occurrence
                    // (withoutOverlapping() should prevent this, but the
                    // unique index is the authoritative guarantee) -- treat
                    // it as already generated and keep stepping forward.
                    $latestOccurrence = Task::where('recurring_rule_id', $rule->id)
                        ->whereDate('due_at', $nextDue)
                        ->firstOrFail();
                }
            } else {
                $latestOccurrence = Task::where('recurring_rule_id', $rule->id)
                    ->whereDate('due_at', $nextDue)
                    ->firstOrFail();
            }

            $occurrenceCount++;
        }

        return $created;
    }
}
