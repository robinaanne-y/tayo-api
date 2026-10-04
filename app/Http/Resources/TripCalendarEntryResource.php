<?php

namespace App\Http\Resources;

use App\Models\Trip;
use App\Models\TripItineraryItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A read-only, non-persisted calendar entry projected from a Trip or
 * TripItineraryItem -- a sibling to EventResource, not a conditional
 * branch inside it, so EventResource itself stays untouched. The `type`
 * field (absent on real EventResource output) and the non-numeric,
 * namespaced `id` are what let the mobile client tell a trip-derived
 * entry apart from a genuine, editable event.
 */
class TripCalendarEntryResource extends JsonResource
{
    public static function forTripSpan(Trip $trip): self
    {
        return new self([
            'id' => "trip-{$trip->id}",
            'type' => 'trip',
            'trip_id' => $trip->id,
            'household_id' => $trip->household_id,
            'creator_member_id' => $trip->created_by_member_id,
            'creator_name' => $trip->createdBy?->name,
            'title' => $trip->title,
            'description' => null,
            'location' => $trip->destination,
            'start_at' => $trip->start_at,
            'end_at' => $trip->end_at ?? $trip->start_at,
            'visibility' => 'household',
            'participants' => MemberResource::collection($trip->participants)->resolve(),
            'created_at' => $trip->created_at,
        ]);
    }

    public static function forItineraryItem(Trip $trip, TripItineraryItem $item): self
    {
        return new self([
            'id' => "trip-itinerary-{$item->id}",
            'type' => 'trip_itinerary',
            'trip_id' => $trip->id,
            'household_id' => $trip->household_id,
            'creator_member_id' => $trip->created_by_member_id,
            'creator_name' => $trip->createdBy?->name,
            'title' => "{$trip->title}: {$item->title}",
            'description' => $item->description,
            'location' => $trip->destination,
            'start_at' => $item->scheduled_at,
            'end_at' => $item->scheduled_at,
            'visibility' => 'household',
            'participants' => MemberResource::collection($trip->participants)->resolve(),
            'created_at' => $item->created_at,
        ]);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'type' => $this->resource['type'],
            'trip_id' => $this->resource['trip_id'],
            'household_id' => $this->resource['household_id'],
            'creator_member_id' => $this->resource['creator_member_id'],
            'creator_name' => $this->resource['creator_name'],
            'title' => $this->resource['title'],
            'description' => $this->resource['description'],
            'location' => $this->resource['location'],
            'start_at' => $this->resource['start_at'],
            'end_at' => $this->resource['end_at'],
            'visibility' => $this->resource['visibility'],
            'participants' => $this->resource['participants'],
            'shared_households' => [],
            'is_recurring' => false,
            'recurrence_summary' => null,
            'created_at' => $this->resource['created_at'],
        ];
    }
}
