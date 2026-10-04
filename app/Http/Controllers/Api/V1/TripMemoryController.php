<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trips\StoreTripMemoryRequest;
use App\Http\Resources\TripMemoryResource;
use App\Models\Household;
use App\Models\Trip;
use App\Models\TripMemory;
use Illuminate\Http\JsonResponse;

class TripMemoryController extends Controller
{
    /**
     * Always writes the caller's own memory -- member_id is never
     * client-supplied, so there's no way to post on someone else's
     * behalf. A second call just replaces the first (one memory per
     * member per trip, enforced by the table's unique constraint).
     */
    public function store(StoreTripMemoryRequest $request, Household $household, Trip $trip): JsonResponse
    {
        $trip = $this->tripFor($household, $trip);

        $memory = TripMemory::updateOrCreate(
            ['trip_id' => $trip->id, 'member_id' => $request->user()->member->id],
            ['content' => $request->validated('content')],
        );

        $memory->load('member');

        return response()->json([
            'data' => TripMemoryResource::make($memory),
        ]);
    }

    public function destroy(Household $household, Trip $trip, TripMemory $memory): JsonResponse
    {
        $trip = $this->tripFor($household, $trip);
        $memory = $this->memoryFor($trip, $memory);

        $this->authorize('deleteTripMemory', [$household, $memory]);

        $memory->delete();

        return response()->json(null, 204);
    }

    private function tripFor(Household $household, Trip $trip): Trip
    {
        abort_if($trip->household_id !== $household->id, 404);

        return $trip;
    }

    private function memoryFor(Trip $trip, TripMemory $memory): TripMemory
    {
        abort_if($memory->trip_id !== $trip->id, 404);

        return $memory;
    }
}
