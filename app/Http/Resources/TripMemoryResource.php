<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripMemoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'member_id' => $this->member_id,
            'member_name' => $this->member?->name,
            'member_avatar_url' => $this->member?->avatar_url,
            'content' => $this->content,
            'created_at' => $this->created_at,
        ];
    }
}
