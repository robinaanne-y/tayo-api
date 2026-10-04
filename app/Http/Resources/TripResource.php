<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'household_id' => $this->household_id,
            'title' => $this->title,
            'destination' => $this->destination,
            'start_at' => $this->start_at?->toDateString(),
            'end_at' => $this->end_at?->toDateString(),
            'notes' => $this->notes,
            'status' => $this->status->value,
            'thumbnail_url' => $this->thumbnail_url,
            'days_until' => $this->daysUntil(),
            'participants' => MemberResource::collection($this->whenLoaded('participants')),
            'itinerary_items' => TripItineraryItemResource::collection($this->whenLoaded('itineraryItems')),
            'memories' => TripMemoryResource::collection($this->whenLoaded('memories')),
            'created_by_member_id' => $this->created_by_member_id,
            'created_by_name' => $this->createdBy?->name,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Null once the trip has already started -- the countdown only makes
     * sense for a trip that hasn't begun yet. Never stored; computed here
     * from start_at vs now() on every read.
     */
    private function daysUntil(): ?int
    {
        $days = (int) now()->startOfDay()->diffInDays($this->start_at, false);

        return $days >= 0 ? $days : null;
    }
}
