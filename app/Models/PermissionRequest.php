<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Models\Concerns\Approvable;
use Database\Factories\PermissionRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'household_id',
    'requester_member_id',
    'type',
    'title',
    'description',
    'requested_start_at',
    'requested_end_at',
    'status',
    'responded_by_member_id',
    'responded_at',
    'response_note',
    'promoted_event_id',
    'requester_acknowledged_at',
])]
class PermissionRequest extends Model
{
    /** @use HasFactory<PermissionRequestFactory> */
    use Approvable, HasFactory;

    protected function casts(): array
    {
        return [
            'requested_start_at' => 'datetime',
            'requested_end_at' => 'datetime',
            'status' => RequestStatus::class,
            'responded_at' => 'datetime',
            'requester_acknowledged_at' => 'datetime',
        ];
    }

    /**
     * True once an adult has resolved this request and the requester
     * hasn't opened it since -- what the mobile app's notification bell
     * badge and "Needs Your Attention" section surface to the requester.
     * Reset only happens by acknowledging (see PermissionRequestController::acknowledge());
     * approve()/decline() never touch this column themselves.
     */
    public function needsRequesterAcknowledgement(): bool
    {
        return in_array($this->status, [RequestStatus::Approved, RequestStatus::Declined], true)
            && $this->requester_acknowledged_at === null;
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

    public function conditions(): HasMany
    {
        return $this->hasMany(RequestCondition::class);
    }

    public function promotedEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'promoted_event_id');
    }
}
