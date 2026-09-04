<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'member' => MemberResource::make($this->whenLoaded('member')),
            'households' => $this->when(
                $this->relationLoaded('member') && $this->member?->relationLoaded('households'),
                fn () => HouseholdResource::collection($this->member->households),
            ),
        ];
    }
}
