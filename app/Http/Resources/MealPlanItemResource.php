<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealPlanItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'household_id' => $this->household_id,
            'date' => $this->date->toDateString(),
            'slot' => $this->slot->value,
            'title' => $this->title,
            'added_by_name' => $this->addedBy?->name,
            'created_at' => $this->created_at,
        ];
    }
}
