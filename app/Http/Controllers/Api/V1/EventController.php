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

        $query = $household->events()
            ->where(function ($q) use ($memberId) {
                $q->where('visibility', 'household')
                    ->orWhere('creator_member_id', $memberId);
            })
            ->with('creator');

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
            'start_at' => $request->validated('start_at'),
            'end_at' => $request->validated('end_at'),
            'visibility' => $request->validated('visibility'),
        ]);

        $event->load('creator');

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
            'start_at' => $request->validated('start_at'),
            'end_at' => $request->validated('end_at'),
            'visibility' => $request->validated('visibility'),
        ]);

        $event->load('creator');

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
