<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trips\StoreTripItineraryItemRequest;
use App\Http\Requests\Trips\UpdateTripItineraryItemRequest;
use App\Http\Resources\TripItineraryItemResource;
use App\Models\Household;
use App\Models\Trip;
use App\Models\TripItineraryItem;
use Illuminate\Http\JsonResponse;

class TripItineraryItemController extends Controller
{
    public function store(StoreTripItineraryItemRequest $request, Household $household, Trip $trip): JsonResponse
    {
        $trip = $this->tripFor($household, $trip);

        $item = $trip->itineraryItems()->create([
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'scheduled_at' => $request->validated('scheduled_at'),
            'created_by_member_id' => $request->user()->member->id,
        ]);

        $item->load('createdBy');

        return response()->json([
            'data' => TripItineraryItemResource::make($item),
        ], 201);
    }

    public function update(
        UpdateTripItineraryItemRequest $request,
        Household $household,
        Trip $trip,
        TripItineraryItem $itineraryItem,
    ): JsonResponse {
        $trip = $this->tripFor($household, $trip);
        $itineraryItem = $this->itineraryItemFor($trip, $itineraryItem);

        $itineraryItem->update([
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'scheduled_at' => $request->validated('scheduled_at'),
        ]);

        $itineraryItem->load('createdBy');

        return response()->json([
            'data' => TripItineraryItemResource::make($itineraryItem),
        ]);
    }

    public function destroy(Household $household, Trip $trip, TripItineraryItem $itineraryItem): JsonResponse
    {
        $trip = $this->tripFor($household, $trip);
        $itineraryItem = $this->itineraryItemFor($trip, $itineraryItem);

        $this->authorize('manageTrip', [$household, $trip]);

        $itineraryItem->delete();

        return response()->json(null, 204);
    }

    private function tripFor(Household $household, Trip $trip): Trip
    {
        abort_if($trip->household_id !== $household->id, 404);

        return $trip;
    }

    private function itineraryItemFor(Trip $trip, TripItineraryItem $itineraryItem): TripItineraryItem
    {
        abort_if($itineraryItem->trip_id !== $trip->id, 404);

        return $itineraryItem;
    }
}
