<?php

namespace App\Models;

use App\Enums\MealSlot;
use App\Enums\RequestStatus;
use App\Models\Concerns\Approvable;
use Database\Factories\MealRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'household_id',
    'requester_member_id',
    'requested_date',
    'requested_slot',
    'title',
    'status',
    'responded_by_member_id',
    'responded_at',
    'response_note',
    'requester_acknowledged_at',
    'meal_plan_item_id',
])]
class MealRequest extends Model
{
    /** @use HasFactory<MealRequestFactory> */
    use Approvable, HasFactory;

    protected function casts(): array
    {
        return [
            'requested_date' => 'date',
            'requested_slot' => MealSlot::class,
            'status' => RequestStatus::class,
            'responded_at' => 'datetime',
            'requester_acknowledged_at' => 'datetime',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'requester_member_id');
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'responded_by_member_id');
    }

    public function mealPlanItem(): BelongsTo
    {
        return $this->belongsTo(MealPlanItem::class);
    }

    /**
     * Mirrors PermissionRequest::needsRequesterAcknowledgement() exactly
     * -- see that method for the reasoning.
     */
    public function needsRequesterAcknowledgement(): bool
    {
        return in_array($this->status, [RequestStatus::Approved, RequestStatus::Declined], true)
            && $this->requester_acknowledged_at === null;
    }
}
