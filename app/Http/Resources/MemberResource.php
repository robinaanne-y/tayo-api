<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'birth_date' => $this->birth_date?->toDateString(),
            'is_placeholder' => $this->is_placeholder,
            'role' => $this->when(
                $this->pivot !== null,
                fn () => $this->pivot->role?->value,
            ),
        ];
    }
}
