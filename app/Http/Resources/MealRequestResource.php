<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'household_id' => $this->household_id,
            'requester_member_id' => $this->requester_member_id,
            'requester_name' => $this->requester?->name,
            'requested_date' => $this->requested_date->toDateString(),
            'requested_slot' => $this->requested_slot->value,
            'title' => $this->title,
            'status' => $this->status->value,
            'responded_by_name' => $this->respondedBy?->name,
            'responded_at' => $this->responded_at,
            'response_note' => $this->response_note,
            'meal_plan_item_id' => $this->meal_plan_item_id,
            // Viewer-relative, same as PermissionRequestResource: true only
            // when the current caller is the requester and hasn't
            // acknowledged the resolution yet.
            'needs_requester_attention' => $this->needsRequesterAcknowledgement()
                && $request->user()?->member?->id === $this->requester_member_id,
            'created_at' => $this->created_at,
        ];
    }
}
