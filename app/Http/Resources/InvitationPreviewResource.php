<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvitationPreviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'household_name' => $this->household->name,
            'role' => $this->role->value,
            'valid' => $this->isValid(),
        ];
    }
}
