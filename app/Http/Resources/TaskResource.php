<?php

namespace App\Http\Resources;

use App\Models\RecurringRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'household_id' => $this->household_id,
            'title' => $this->title,
            'description' => $this->description,
            'due_at' => $this->due_at?->toDateString(),
            'trip_id' => $this->trip_id,
            'assigned_member_id' => $this->assigned_member_id,
            'assignee_name' => $this->assignee?->name,
            'created_by_member_id' => $this->created_by_member_id,
            'created_by_name' => $this->createdBy?->name,
            'completed_at' => $this->completed_at,
            'completed_by_member_id' => $this->completed_by_member_id,
            'completed_by_name' => $this->completedBy?->name,
            'is_recurring' => $this->recurring_rule_id !== null,
            'recurrence_summary' => $this->whenLoaded('recurringRule', fn () => $this->recurringRule ? $this->summarizeRecurrence($this->recurringRule) : null),
            'created_at' => $this->created_at,
        ];
    }

    private function summarizeRecurrence(RecurringRule $rule): string
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

        if ($rule->frequency === 'weekly' && ! empty($rule->by_day)) {
            $names = ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            $days = collect($rule->by_day)->sort()->map(fn ($d) => $names[$d])->implode(', ');
            $summary .= " on {$days}";
        }

        return $summary;
    }
}
