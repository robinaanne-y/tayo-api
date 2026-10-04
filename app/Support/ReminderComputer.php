<?php

namespace App\Support;

use App\Enums\ReminderCategory;
use App\Enums\TripStatus;
use App\Models\GroceryItem;
use App\Models\Household;
use App\Models\MealPlanItem;
use App\Models\Member;
use App\Models\Task;
use App\Models\Trip;
use Carbon\Carbon;

/**
 * Computes which reminder categories currently apply for a member, live
 * from existing data -- no `reminders` row is ever stored. Same "computed
 * at read time" technique used elsewhere in this codebase (e.g. the
 * all_member_households calendar visibility branch, overdue-task
 * detection) rather than a background job writing notification rows.
 */
class ReminderComputer
{
    /**
     * Only nag about next week's meal plan starting Thursday -- showing it
     * Monday through Wednesday would be noise for a plan that's usually
     * filled in later in the week.
     */
    private const MEAL_PLANNING_TRIGGER_ISO_WEEKDAY = 4;

    /**
     * No grocery activity (nothing added) in this many days.
     */
    private const GROCERY_STALE_DAYS = 7;

    /**
     * A trip within this many days still counts as "coming up soon".
     */
    private const TRIP_PREP_WINDOW_DAYS = 7;

    /**
     * @return array<int, array{category: string, title: string, message: string}>
     */
    public function forMember(Household $household, Member $member): array
    {
        $enabledCategories = $this->enabledCategories($member);
        $reminders = [];

        if ($enabledCategories[ReminderCategory::MealPlanning->value]
            && $this->needsMealPlanning($household)) {
            $reminders[] = [
                'category' => ReminderCategory::MealPlanning->value,
                'title' => "Plan next week's meals",
                'message' => "Next week doesn't have any meals planned yet.",
            ];
        }

        if ($enabledCategories[ReminderCategory::Grocery->value]
            && $this->groceryListIsStale($household)) {
            $reminders[] = [
                'category' => ReminderCategory::Grocery->value,
                'title' => 'Check the grocery list',
                'message' => "The grocery list hasn't been touched in a while.",
            ];
        }

        foreach ($this->tripsNeedingPrep($household) as $trip) {
            $reminders[] = [
                'category' => ReminderCategory::TripPrep->value,
                'title' => "Finish prepping for {$trip->title}",
                'message' => 'There are still unchecked items on the checklist.',
            ];
        }

        return $reminders;
    }

    /**
     * @return array<string, bool>
     */
    private function enabledCategories(Member $member): array
    {
        $overrides = $member->notificationPreferences()
            ->get()
            ->keyBy(fn ($pref) => $pref->category->value)
            ->map(fn ($pref) => $pref->enabled);

        $result = [];
        foreach (ReminderCategory::cases() as $category) {
            // Unset categories default to enabled -- these are low-stakes
            // informational nudges, not consent-sensitive data, so
            // "always notify" is the right implicit default (matches
            // every earlier phase's assumption before notification
            // preferences existed at all).
            $result[$category->value] = $overrides->get($category->value, true);
        }

        return $result;
    }

    private function needsMealPlanning(Household $household): bool
    {
        $today = Carbon::today();

        if ($today->isoWeekday() < self::MEAL_PLANNING_TRIGGER_ISO_WEEKDAY) {
            return false;
        }

        $nextMonday = $today->copy()->next(Carbon::MONDAY);
        $nextSunday = $nextMonday->copy()->addDays(6);

        return MealPlanItem::where('household_id', $household->id)
            ->whereBetween('date', [$nextMonday->toDateString(), $nextSunday->toDateString()])
            ->doesntExist();
    }

    private function groceryListIsStale(Household $household): bool
    {
        $hasUnpurchasedItems = GroceryItem::where('household_id', $household->id)
            ->whereNull('purchased_at')
            ->exists();

        if ($hasUnpurchasedItems) {
            return false;
        }

        $mostRecentAddition = GroceryItem::where('household_id', $household->id)
            ->max('created_at');

        if ($mostRecentAddition === null) {
            return true;
        }

        return Carbon::parse($mostRecentAddition)->lt(Carbon::now()->subDays(self::GROCERY_STALE_DAYS));
    }

    /**
     * @return array<int, Trip>
     */
    private function tripsNeedingPrep(Household $household): array
    {
        $windowEnd = Carbon::today()->addDays(self::TRIP_PREP_WINDOW_DAYS);

        $trips = $household->trips()
            ->whereNotIn('status', [TripStatus::Completed->value, TripStatus::Cancelled->value])
            ->whereBetween('start_at', [Carbon::today()->toDateString(), $windowEnd->toDateString()])
            ->get();

        return $trips->filter(
            fn ($trip) => Task::where('trip_id', $trip->id)->whereNull('completed_at')->exists(),
        )->values()->all();
    }
}
