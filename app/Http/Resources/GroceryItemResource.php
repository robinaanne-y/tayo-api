<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroceryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'household_id' => $this->household_id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'category' => $this->category,
            'added_by_name' => $this->addedBy?->name,
            'purchased_at' => $this->purchased_at,
            'purchased_by_name' => $this->purchasedBy?->name,
            'created_at' => $this->created_at,
        ];
    }
}
