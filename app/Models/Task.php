<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'household_id',
    'title',
    'description',
    'due_at',
    'created_by_member_id',
    'assigned_member_id',
    'completed_at',
    'completed_by_member_id',
    'recurring_rule_id',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'due_at' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'created_by_member_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'assigned_member_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'completed_by_member_id');
    }

    public function recurringRule(): BelongsTo
    {
        return $this->belongsTo(RecurringRule::class);
    }

    public function isOverdue(): bool
    {
        if ($this->completed_at !== null || $this->due_at === null) {
            return false;
        }

        /** @var Carbon $dueAt */
        $dueAt = $this->due_at;

        return $dueAt->lessThan(Carbon::today());
    }
}
