<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'household_id' => $this->household_id,
            'requester_member_id' => $this->requester_member_id,
            'requester_name' => $this->requester?->name,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'requested_start_at' => $this->requested_start_at,
            'requested_end_at' => $this->requested_end_at,
            'status' => $this->status->value,
            'responded_by_name' => $this->respondedBy?->name,
            'responded_at' => $this->responded_at,
            'response_note' => $this->response_note,
            'conditions' => RequestConditionResource::collection($this->whenLoaded('conditions')),
            'promoted_event_id' => $this->promoted_event_id,
            'is_overdue' => $this->status->value === 'pending'
                && $this->requested_start_at !== null
                && $this->requested_start_at->isPast(),
            'created_at' => $this->created_at,
        ];
    }
}
