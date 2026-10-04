<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Events\StoreEventRequest;
use App\Http\Requests\Events\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\Household;
use App\Models\RecurringRule;
use App\Support\RecurrenceGenerator;
use App\Support\TripCalendarProjector;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    public function index(Request $request, Household $household): JsonResponse
    {
        $this->authorize('view', $household);

        $memberId = $request->user()->member->id;

        // Three ways an event can belong in this household's calendar: it
        // was created here (subject to its own visibility), it was shared
        // in explicitly (selected_households), or its creator belongs to
        // this household too and shared it with everywhere they are
        // (all_member_households).
        $query = Event::query()
            ->where(function ($q) use ($household, $memberId) {
                $q->where(function ($native) use ($household, $memberId) {
                    $native->where('household_id', $household->id)
                        ->where(function ($visible) use ($memberId) {
                            $visible->whereIn('visibility', [
                                'household',
                                'selected_households',
                                'all_member_households',
                            ])->orWhere('creator_member_id', $memberId);
                        });
                })
                    ->orWhere(function ($shared) use ($household) {
                        $shared->where('visibility', 'selected_households')
                            ->where('household_id', '!=', $household->id)
                            ->whereHas(
                                'sharedHouseholds',
                                fn ($q) => $q->where('households.id', $household->id),
                            );
                    })
                    ->orWhere(function ($sharedWithAll) use ($household) {
                        $sharedWithAll->where('visibility', 'all_member_households')
                            ->where('household_id', '!=', $household->id)
                            ->whereHas(
                                'creator.households',
                                fn ($q) => $q->where('households.id', $household->id),
                            );
                    });
            })
            ->with(['creator', 'participants', 'sharedHouseholds', 'recurringRule']);

        if ($request->filled('from')) {
            $query->where('end_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->where('start_at', '<=', $request->date('to'));
        }

        $events = $query->orderBy('start_at')->get();

        // Trips/itinerary items are projected into the same response as
        // read-only, non-persisted entries (see TripCalendarProjector) --
        // no `events` row is ever created for a trip, matching the
        // all_member_households branch above's "computed live, not
        // stored" precedent.
        $tripEntries = (new TripCalendarProjector)->project(
            $household,
            $request->filled('from') ? $request->date('from') : null,
            $request->filled('to') ? $request->date('to') : null,
        );

        return response()->json([
            'data' => array_merge(
                $events->map(fn (Event $event) => (new EventResource($event))->resolve())->all(),
                $tripEntries,
            ),
        ]);
    }

    public function store(StoreEventRequest $request, Household $household): JsonResponse
    {
        $recurrence = $request->validated('recurrence');

        $firstEvent = DB::transaction(function () use ($request, $household, $recurrence) {
            $recurringRuleId = null;
            $occurrences = [[
                'start_at' => Carbon::parse($request->validated('start_at')),
                'end_at' => Carbon::parse($request->validated('end_at')),
            ]];

            if ($recurrence !== null) {
                $rule = RecurringRule::create([
                    'frequency' => $recurrence['frequency'],
                    'interval' => $recurrence['interval'] ?? 1,
                    'by_day' => $recurrence['by_day'] ?? null,
                    'ends_at' => $recurrence['ends_at'] ?? null,
                    'occurrence_count' => $recurrence['occurrence_count'] ?? null,
                ]);
                $recurringRuleId = $rule->id;

                $occurrences = (new RecurrenceGenerator)->generate(
                    $occurrences[0]['start_at'],
                    $occurrences[0]['end_at'],
                    $rule->frequency,
                    $rule->interval,
                    $rule->by_day,
                    $rule->ends_at,
                    $rule->occurrence_count,
                );
            }

            $participantIds = $request->validated('participant_member_ids') ?? [];
            $sharedHouseholdIds = $request->validated('visibility') === 'selected_households'
                ? $request->validated('shared_household_ids')
                : [];

            $firstEvent = null;

            foreach ($occurrences as $occurrence) {
                $event = $household->events()->create([
                    'creator_member_id' => $request->user()->member->id,
                    'title' => $request->validated('title'),
                    'description' => $request->validated('description'),
                    'location' => $request->validated('location'),
                    'start_at' => $occurrence['start_at'],
                    'end_at' => $occurrence['end_at'],
                    'visibility' => $request->validated('visibility'),
                    'recurring_rule_id' => $recurringRuleId,
                ]);

                $event->participants()->sync($participantIds);
                $event->sharedHouseholds()->sync($sharedHouseholdIds);

                $firstEvent ??= $event;
            }

            return $firstEvent;
        });

        $firstEvent->load(['creator', 'participants', 'sharedHouseholds', 'recurringRule']);

        return response()->json([
            'data' => EventResource::make($firstEvent),
        ], 201);
    }

    private function eventFor(Household $household, Event $event): Event
    {
        abort_if($event->household_id !== $household->id, 404);

        return $event;
    }

    public function update(UpdateEventRequest $request, Household $household, Event $event): JsonResponse
    {
        $event = $this->eventFor($household, $event);

        $editScope = $request->validated('edit_scope', 'this');
        $participantIds = $request->validated('participant_member_ids') ?? [];
        $sharedHouseholdIds = $request->validated('visibility') === 'selected_households'
            ? $request->validated('shared_household_ids')
            : [];

        if ($editScope === 'following' && $event->recurring_rule_id !== null) {
            $affected = Event::where('recurring_rule_id', $event->recurring_rule_id)
                ->where('start_at', '>=', $event->start_at)
                ->get();

            foreach ($affected as $occurrence) {
                $occurrence->update([
                    'title' => $request->validated('title'),
                    'description' => $request->validated('description'),
                    'location' => $request->validated('location'),
                    'visibility' => $request->validated('visibility'),
                ]);
                $occurrence->participants()->sync($participantIds);
                $occurrence->sharedHouseholds()->sync($sharedHouseholdIds);
            }
        } else {
            $previousRuleId = $event->recurring_rule_id;

            $event->update([
                'title' => $request->validated('title'),
                'description' => $request->validated('description'),
                'location' => $request->validated('location'),
                'start_at' => $request->validated('start_at'),
                'end_at' => $request->validated('end_at'),
                'visibility' => $request->validated('visibility'),
                'recurring_rule_id' => null,
            ]);
            $event->participants()->sync($participantIds);
            $event->sharedHouseholds()->sync($sharedHouseholdIds);

            $this->pruneOrphanedRule($previousRuleId);
        }

        $event->load(['creator', 'participants', 'sharedHouseholds', 'recurringRule']);

        return response()->json([
            'data' => EventResource::make($event),
        ]);
    }

    public function destroy(Request $request, Household $household, Event $event): JsonResponse
    {
        $event = $this->eventFor($household, $event);

        $this->authorize('deleteEvent', [$household, $event]);

        $scope = $request->query('scope', 'this');
        abort_unless(in_array($scope, ['this', 'following'], true), 422, 'Invalid scope.');

        $recurringRuleId = $event->recurring_rule_id;

        if ($scope === 'following' && $recurringRuleId !== null) {
            Event::where('recurring_rule_id', $recurringRuleId)
                ->where('start_at', '>=', $event->start_at)
                ->delete();
        } else {
            $event->delete();
        }

        $this->pruneOrphanedRule($recurringRuleId);

        return response()->json(null, 204);
    }

    private function pruneOrphanedRule(?int $recurringRuleId): void
    {
        if ($recurringRuleId === null) {
            return;
        }

        if (! Event::where('recurring_rule_id', $recurringRuleId)->exists()) {
            RecurringRule::destroy($recurringRuleId);
        }
    }
}
