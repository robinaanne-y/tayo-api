<?php

namespace App\Models\Concerns;

use App\Enums\RequestStatus;
use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared status/approval mechanics for member-submitted requests that an
 * adult acts on (permission requests now, meal requests in a later phase).
 * Assumes the host model has `status`, `responded_by_member_id`,
 * `responded_at`, and `response_note` columns, with `status` cast to
 * [RequestStatus].
 */
trait Approvable
{
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', RequestStatus::Pending);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', RequestStatus::Approved);
    }

    public function scopeDeclined(Builder $query): Builder
    {
        return $query->where('status', RequestStatus::Declined);
    }

    public function approve(Member $responder, ?string $note = null): void
    {
        $this->forceFill([
            'status' => RequestStatus::Approved,
            'responded_by_member_id' => $responder->id,
            'responded_at' => now(),
            'response_note' => $note,
        ])->save();
    }

    public function decline(Member $responder, ?string $note = null): void
    {
        $this->forceFill([
            'status' => RequestStatus::Declined,
            'responded_by_member_id' => $responder->id,
            'responded_at' => now(),
            'response_note' => $note,
        ])->save();
    }

    public function cancel(): void
    {
        $this->forceFill(['status' => RequestStatus::Cancelled])->save();
    }

    public function isPending(): bool
    {
        return $this->status === RequestStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === RequestStatus::Approved;
    }

    public function isDeclined(): bool
    {
        return $this->status === RequestStatus::Declined;
    }

    public function isCancelled(): bool
    {
        return $this->status === RequestStatus::Cancelled;
    }
}
