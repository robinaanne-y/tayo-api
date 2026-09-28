<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Events\StoreEventRequest;
use App\Http\Requests\Events\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\Household;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            ->with(['creator', 'participants', 'sharedHouseholds']);

        if ($request->filled('from')) {
            $query->where('end_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->where('start_at', '<=', $request->date('to'));
        }

        $events = $query->orderBy('start_at')->get();

        return response()->json([
            'data' => EventResource::collection($events),
        ]);
    }

    public function store(StoreEventRequest $request, Household $household): JsonResponse
    {
        $event = $household->events()->create([
            'creator_member_id' => $request->user()->member->id,
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'location' => $request->validated('location'),
            'start_at' => $request->validated('start_at'),
            'end_at' => $request->validated('end_at'),
            'visibility' => $request->validated('visibility'),
        ]);

        $event->participants()->sync($request->validated('participant_member_ids') ?? []);
        $event->sharedHouseholds()->sync(
            $request->validated('visibility') === 'selected_households'
                ? $request->validated('shared_household_ids')
                : [],
        );
        $event->load(['creator', 'participants', 'sharedHouseholds']);

        return response()->json([
            'data' => EventResource::make($event),
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

        $event->update([
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'location' => $request->validated('location'),
            'start_at' => $request->validated('start_at'),
            'end_at' => $request->validated('end_at'),
            'visibility' => $request->validated('visibility'),
        ]);

        $event->participants()->sync($request->validated('participant_member_ids') ?? []);
        $event->sharedHouseholds()->sync(
            $request->validated('visibility') === 'selected_households'
                ? $request->validated('shared_household_ids')
                : [],
        );
        $event->load(['creator', 'participants', 'sharedHouseholds']);

        return response()->json([
            'data' => EventResource::make($event),
        ]);
    }

    public function destroy(Request $request, Household $household, Event $event): JsonResponse
    {
        $event = $this->eventFor($household, $event);

        $this->authorize('deleteEvent', [$household, $event]);

        $event->delete();

        return response()->json(null, 204);
    }
}
