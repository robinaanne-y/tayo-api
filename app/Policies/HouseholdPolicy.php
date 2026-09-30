<?php

namespace App\Policies;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\User;

class HouseholdPolicy
{
    /**
     * Any authenticated user may create a household; they become its Owner.
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Household $household): bool
    {
        return $user->membershipFor($household) !== null;
    }

    public function update(User $user, Household $household): bool
    {
        return $user->membershipFor($household)?->role === HouseholdRole::Owner;
    }

    public function viewMembers(User $user, Household $household): bool
    {
        return $this->view($user, $household);
    }

    public function addMember(User $user, Household $household): bool
    {
        $role = $user->membershipFor($household)?->role;

        return $role?->canManageHousehold() ?? false;
    }
}
