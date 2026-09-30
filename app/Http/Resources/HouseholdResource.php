<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HouseholdResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // Present when loaded via the authenticated member's pivot,
            // e.g. GET /households listing "my households".
            'my_role' => $this->when(
                $this->pivot !== null,
                fn () => $this->pivot->role?->value,
            ),
            'created_at' => $this->created_at,
        ];
    }
}
