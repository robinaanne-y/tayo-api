<?php

namespace App\Models;

use Database\Factories\HouseholdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'color', 'emoji', 'created_by_user_id', 'meal_approver_member_id'])]
class Household extends Model
{
    /** @use HasFactory<HouseholdFactory> */
    use HasFactory;

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * The one member designated to manage the meal plan and approve/
     * decline meal requests, when set -- see HouseholdPolicy's meal
     * methods. Null means no delegation: any Owner/Adult manages.
     */
    public function mealApprover(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'meal_approver_member_id');
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

    public function familyNotes(): HasMany
    {
        return $this->hasMany(FamilyNote::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(PermissionRequest::class);
    }

    public function mealPlanItems(): HasMany
    {
        return $this->hasMany(MealPlanItem::class);
    }

    public function mealRequests(): HasMany
    {
        return $this->hasMany(MealRequest::class);
    }

    public function groceryItems(): HasMany
    {
        return $this->hasMany(GroceryItem::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
