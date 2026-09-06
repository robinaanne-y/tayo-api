<?php

namespace App\Models;

use Database\Factories\HouseholdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'created_by_user_id'])]
class Household extends Model
{
    /** @use HasFactory<HouseholdFactory> */
    use HasFactory;

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(HouseholdMembership::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'household_memberships')
            ->using(HouseholdMembership::class)
            ->withPivot(['role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(HouseholdInvitation::class);
    }
}
