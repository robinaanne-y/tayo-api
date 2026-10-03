<?php

namespace App\Support;

use App\Exceptions\RecurrenceTooLargeException;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class RecurrenceGenerator
{
    public const MAX_OCCURRENCES = 260;

    /**
     * @param  array<int>|null  $byDay  ISO weekdays (1 = Monday .. 7 = Sunday), weekly only.
     * @return array<int, array{start_at: Carbon, end_at: Carbon}> First element is always exactly [$startAt, $endAt].
     *
     * @throws RecurrenceTooLargeException
     */
    public function generate(
        Carbon $startAt,
        Carbon $endAt,
        string $frequency,
        int $interval,
        ?array $byDay,
        ?Carbon $endsAt,
        ?int $occurrenceCount,
    ): array {
        $durationSeconds = $startAt->diffInSeconds($endAt);
        // An end-date picker means "through the end of that day," not the
        // literal midnight instant -- otherwise a same-day event with a
        // time-of-day after midnight would never match its own end date.
        $effectiveEndsAt = $endsAt?->copy()->endOfDay();

        return match ($frequency) {
            'daily' => $this->generateStepped(
                $startAt,
                $durationSeconds,
                fn (int $i) => $startAt->copy()->addDays($i * $interval),
                $effectiveEndsAt,
                $occurrenceCount,
            ),
            'monthly' => $this->generateStepped(
                $startAt,
                $durationSeconds,
                fn (int $i) => $startAt->copy()->addMonthsNoOverflow($i * $interval),
                $effectiveEndsAt,
                $occurrenceCount,
            ),
            'weekly' => empty($byDay)
                ? $this->generateStepped(
                    $startAt,
                    $durationSeconds,
                    fn (int $i) => $startAt->copy()->addWeeks($i * $interval),
                    $effectiveEndsAt,
                    $occurrenceCount,
                )
                : $this->generateWeeklyByDay($startAt, $durationSeconds, $interval, $byDay, $effectiveEndsAt, $occurrenceCount),
            default => throw new \InvalidArgumentException("Unknown recurrence frequency: {$frequency}"),
        };
    }

    /**
     * @param  callable(int): Carbon  $nth  Given an occurrence index, returns that occurrence's start.
     * @return array<int, array{start_at: Carbon, end_at: Carbon}>
     */
    private function generateStepped(Carbon $startAt, int $durationSeconds, callable $nth, ?Carbon $endsAt, ?int $occurrenceCount): array
    {
        $occurrences = [];

        for ($i = 0; ; $i++) {
            if ($i >= self::MAX_OCCURRENCES) {
                throw new RecurrenceTooLargeException;
            }

            $occurrenceStart = $nth($i);

            if ($endsAt !== null && $occurrenceStart->greaterThan($endsAt)) {
                break;
            }

            $occurrences[] = [
                'start_at' => $occurrenceStart,
                'end_at' => $occurrenceStart->copy()->addSeconds($durationSeconds),
            ];

            if ($occurrenceCount !== null && count($occurrences) >= $occurrenceCount) {
                break;
            }
        }

        return $occurrences;
    }

    /**
     * @param  array<int>  $byDay
     * @return array<int, array{start_at: Carbon, end_at: Carbon}>
     */
    private function generateWeeklyByDay(Carbon $startAt, int $durationSeconds, int $interval, array $byDay, ?Carbon $endsAt, ?int $occurrenceCount): array
    {
        // The occurrence the user actually created must always be included,
        // even if they didn't tick its own weekday in the picker.
        $byDay = array_unique([...$byDay, $startAt->dayOfWeekIso]);

        $occurrences = [];
        $firstWeekStart = $startAt->copy()->startOfWeek(CarbonInterface::MONDAY);
        $day = $startAt->copy()->startOfDay();

        // Generous upper bound on days scanned -- covers the validated max
        // of interval (12) * occurrence count (260) weeks plus slack,
        // without risking a runaway loop if $byDay never lines up.
        $maxDaysToScan = self::MAX_OCCURRENCES * 12 * 7 + 366;

        for ($scanned = 0; $scanned < $maxDaysToScan; $scanned++, $day = $day->copy()->addDay()) {
            if ($endsAt !== null && $day->greaterThan($endsAt)) {
                break;
            }

            $weekStart = $day->copy()->startOfWeek(CarbonInterface::MONDAY);
            $weeksSinceFirst = (int) ($firstWeekStart->diffInDays($weekStart) / 7);

            if ($weeksSinceFirst % $interval !== 0 || ! in_array($day->dayOfWeekIso, $byDay, true)) {
                continue;
            }

            if (count($occurrences) >= self::MAX_OCCURRENCES) {
                throw new RecurrenceTooLargeException;
            }

            $occurrenceStart = $day->copy()->setTime($startAt->hour, $startAt->minute, $startAt->second);

            $occurrences[] = [
                'start_at' => $occurrenceStart,
                'end_at' => $occurrenceStart->copy()->addSeconds($durationSeconds),
            ];

            if ($occurrenceCount !== null && count($occurrences) >= $occurrenceCount) {
                break;
            }
        }

        if ($occurrenceCount !== null && count($occurrences) < $occurrenceCount) {
            throw new RecurrenceTooLargeException;
        }

        return $occurrences;
    }

    /**
     * Computes the single next occurrence date after $after, for the
     * backend-generated (one-row-at-a-time) recurrence used by Tasks --
     * unlike generate() above, which eagerly expands a whole bounded range
     * up front for Events. $anchorWeekStart must be the rule's *first*
     * occurrence's week start (not $after's own week) so that an interval
     * greater than 1 combined with $byDay correctly distinguishes an
     * "active" week from a "skipped" one -- stepping relative to $after
     * alone can't tell those apart once by-day and interval > 1 combine.
     *
     * @param  array<int>|null  $byDay  ISO weekdays (1 = Monday .. 7 = Sunday), weekly only.
     */
    public function nextOccurrenceDate(
        Carbon $after,
        string $frequency,
        int $interval,
        ?array $byDay,
        Carbon $anchorWeekStart,
    ): Carbon {
        return match ($frequency) {
            'daily' => $after->copy()->addDays($interval),
            'monthly' => $after->copy()->addMonthsNoOverflow($interval),
            'weekly' => empty($byDay)
                ? $after->copy()->addWeeks($interval)
                : $this->nextWeeklyByDay($after, $interval, $byDay, $anchorWeekStart),
            default => throw new \InvalidArgumentException("Unknown recurrence frequency: {$frequency}"),
        };
    }

    /**
     * @param  array<int>  $byDay
     */
    private function nextWeeklyByDay(Carbon $after, int $interval, array $byDay, Carbon $anchorWeekStart): Carbon
    {
        $day = $after->copy()->startOfDay()->addDay();
        $maxDaysToScan = $interval * 7 * 10 + 14;

        for ($scanned = 0; $scanned < $maxDaysToScan; $scanned++, $day = $day->copy()->addDay()) {
            $weekStart = $day->copy()->startOfWeek(CarbonInterface::MONDAY);
            $weeksSinceAnchor = (int) ($anchorWeekStart->diffInDays($weekStart) / 7);

            if ($weeksSinceAnchor % $interval === 0 && in_array($day->dayOfWeekIso, $byDay, true)) {
                return $day;
            }
        }

        throw new RecurrenceTooLargeException;
    }
}
