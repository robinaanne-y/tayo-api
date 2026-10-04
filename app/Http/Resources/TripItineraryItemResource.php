<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripItineraryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'title' => $this->title,
            'description' => $this->description,
            'scheduled_at' => $this->scheduled_at,
            'created_by_member_id' => $this->created_by_member_id,
            'created_by_name' => $this->createdBy?->name,
            'created_at' => $this->created_at,
        ];
    }
}
