<?php

namespace App\Models;

use App\Enums\HouseholdRole;
use Database\Factories\HouseholdMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Concerns\AsPivot;

#[Fillable(['household_id', 'member_id', 'role', 'status', 'joined_at'])]
class HouseholdMembership extends Model
{
    /** @use HasFactory<HouseholdMembershipFactory> */
    use AsPivot, HasFactory;

    protected $table = 'household_memberships';

    protected function casts(): array
    {
        return [
            'role' => HouseholdRole::class,
            'joined_at' => 'datetime',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
