<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'household_id' => $this->household_id,
            'creator_member_id' => $this->creator_member_id,
            'creator_name' => $this->creator?->name,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'start_at' => $this->start_at,
            'end_at' => $this->end_at,
            'visibility' => $this->visibility,
            'participants' => MemberResource::collection($this->whenLoaded('participants')),
            'shared_households' => HouseholdResource::collection($this->whenLoaded('sharedHouseholds')),
            'is_recurring' => $this->recurring_rule_id !== null,
            'recurrence_summary' => $this->whenLoaded('recurringRule', fn () => $this->recurringRule ? $this->summarizeRecurrence($this->recurringRule) : null),
            'created_at' => $this->created_at,
        ];
    }

    private function summarizeRecurrence(\App\Models\RecurringRule $rule): string
    {
        $unit = match ($rule->frequency) {
            'daily' => 'day',
            'weekly' => 'week',
            'monthly' => 'month',
            default => $rule->frequency,
        };

        $summary = $rule->interval > 1
            ? "Repeats every {$rule->interval} {$unit}s"
            : "Repeats {$rule->frequency}";

        if ($rule->frequency === 'weekly' && !empty($rule->by_day)) {
            $names = ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            $days = collect($rule->by_day)->sort()->map(fn ($d) => $names[$d])->implode(', ');
            $summary .= " on {$days}";
        }

        return $summary;
    }
}
