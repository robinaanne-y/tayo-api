<?php

namespace App\Models;

use App\Enums\HouseholdRole;
use App\Models\Concerns\HasSecureToken;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['household_id', 'created_by_member_id', 'role', 'token_hash', 'expires_at'])]
class HouseholdInvitation extends Model
{
    use HasSecureToken;

    protected function casts(): array
    {
        return [
            'role' => HouseholdRole::class,
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
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

    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'used_by_member_id');
    }
}
