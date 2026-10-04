<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trips\StoreTripRequest;
use App\Http\Requests\Trips\UpdateTripRequest;
use App\Http\Requests\Trips\UploadTripThumbnailRequest;
use App\Http\Resources\TripResource;
use App\Models\Household;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class TripController extends Controller
{
    public function index(Household $household): JsonResponse
    {
        $this->authorize('view', $household);

        $trips = $household->trips()
            ->with(['participants', 'createdBy'])
            ->orderBy('start_at')
            ->get();

        return response()->json([
            'data' => TripResource::collection($trips),
        ]);
    }

    public function store(StoreTripRequest $request, Household $household): JsonResponse
    {
        $trip = $household->trips()->create([
            'title' => $request->validated('title'),
            'destination' => $request->validated('destination'),
            'start_at' => $request->validated('start_at'),
            'end_at' => $request->validated('end_at'),
            'notes' => $request->validated('notes'),
            'status' => $request->validated('status', 'planning'),
            'created_by_member_id' => $request->user()->member->id,
        ]);

        $trip->participants()->sync($request->validated('participant_member_ids') ?? []);
        $trip->load(['participants', 'itineraryItems', 'memories.member', 'createdBy']);

        return response()->json([
            'data' => TripResource::make($trip),
        ], 201);
    }

    public function show(Household $household, Trip $trip): JsonResponse
    {
        $trip = $this->tripFor($household, $trip);

        $this->authorize('view', $household);

        $trip->load(['participants', 'itineraryItems', 'memories.member', 'createdBy']);

        return response()->json([
            'data' => TripResource::make($trip),
        ]);
    }

    public function update(UpdateTripRequest $request, Household $household, Trip $trip): JsonResponse
    {
        $trip = $this->tripFor($household, $trip);

        $trip->update([
            'title' => $request->validated('title'),
            'destination' => $request->validated('destination'),
            'start_at' => $request->validated('start_at'),
            'end_at' => $request->validated('end_at'),
            'notes' => $request->validated('notes'),
            'status' => $request->validated('status', $trip->status->value),
        ]);

        if ($request->has('participant_member_ids')) {
            $trip->participants()->sync($request->validated('participant_member_ids') ?? []);
        }

        $trip->load(['participants', 'itineraryItems', 'memories.member', 'createdBy']);

        return response()->json([
            'data' => TripResource::make($trip),
        ]);
    }

    public function destroy(Household $household, Trip $trip): JsonResponse
    {
        $trip = $this->tripFor($household, $trip);

        $this->authorize('manageTrip', [$household, $trip]);

        $trip->delete();

        return response()->json(null, 204);
    }

    public function uploadThumbnail(UploadTripThumbnailRequest $request, Household $household, Trip $trip): JsonResponse
    {
        $trip = $this->tripFor($household, $trip);

        $oldPath = $trip->thumbnail_path;
        $newPath = $request->file('thumbnail')->store('trip-thumbnails', 'public');

        $trip->update(['thumbnail_path' => $newPath]);

        if ($oldPath !== null) {
            Storage::disk('public')->delete($oldPath);
        }

        return response()->json([
            'data' => TripResource::make($trip),
        ]);
    }

    private function tripFor(Household $household, Trip $trip): Trip
    {
        abort_if($trip->household_id !== $household->id, 404);

        return $trip;
    }
}
