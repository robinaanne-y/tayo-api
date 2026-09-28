<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'household_id' => $this->household_id,
            'creator_member_id' => $this->creator_member_id,
            'creator_name' => $this->creator?->name,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'start_at' => $this->start_at,
            'end_at' => $this->end_at,
            'visibility' => $this->visibility,
            'participants' => MemberResource::collection($this->whenLoaded('participants')),
            'shared_households' => HouseholdResource::collection($this->whenLoaded('sharedHouseholds')),
            'created_at' => $this->created_at,
        ];
    }
}
