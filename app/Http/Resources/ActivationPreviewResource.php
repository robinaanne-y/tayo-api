<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivationPreviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'member_name' => $this->member->name,
            'household_name' => $this->member->households->first()?->name,
            'valid' => $this->isValid(),
        ];
    }
}
