<?php

namespace App\Policies;

use App\Enums\HouseholdRole;
use App\Models\FamilyNote;
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

    /**
     * Any member — including a minor or child — can leave a family note.
     */
    public function addNote(User $user, Household $household): bool
    {
        return $user->membershipFor($household) !== null;
    }

    public function deleteNote(User $user, Household $household, FamilyNote $note): bool
    {
        $membership = $user->membershipFor($household);

        if ($membership === null) {
            return false;
        }

        return $note->author_member_id === $membership->member_id
            || ($membership->role?->canManageHousehold() ?? false);
    }
}
