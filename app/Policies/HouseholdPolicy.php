<?php

namespace App\Policies;

use App\Enums\HouseholdRole;
use App\Models\Announcement;
use App\Models\Event;
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

    /**
     * Unlike a family note, an announcement is a household bulletin —
     * only an Owner/Adult can post one.
     */
    public function addAnnouncement(User $user, Household $household): bool
    {
        $role = $user->membershipFor($household)?->role;

        return $role?->canManageHousehold() ?? false;
    }

    public function deleteAnnouncement(User $user, Household $household, Announcement $announcement): bool
    {
        $membership = $user->membershipFor($household);

        if ($membership === null) {
            return false;
        }

        return $announcement->author_member_id === $membership->member_id
            || ($membership->role?->canManageHousehold() ?? false);
    }

    /**
     * Any member can add an event to their own personal schedule or the
     * shared household calendar.
     */
    public function addEvent(User $user, Household $household): bool
    {
        return $user->membershipFor($household) !== null;
    }

    /**
     * A private event is only visible to its creator; a household event is
     * visible to every member.
     */
    public function viewEvent(User $user, Household $household, Event $event): bool
    {
        $membership = $user->membershipFor($household);

        if ($membership === null) {
            return false;
        }

        return $event->visibility === 'household'
            || $event->creator_member_id === $membership->member_id;
    }

    /**
     * The creator can always manage their own event. A household-visible
     * event can also be managed by an Owner/Adult; a private event stays
     * creator-only regardless of role, since it's personal.
     */
    public function updateEvent(User $user, Household $household, Event $event): bool
    {
        return $this->manageEvent($user, $household, $event);
    }

    public function deleteEvent(User $user, Household $household, Event $event): bool
    {
        return $this->manageEvent($user, $household, $event);
    }

    private function manageEvent(User $user, Household $household, Event $event): bool
    {
        $membership = $user->membershipFor($household);

        if ($membership === null) {
            return false;
        }

        if ($event->creator_member_id === $membership->member_id) {
            return true;
        }

        return $event->visibility === 'household'
            && ($membership->role?->canManageHousehold() ?? false);
    }
}
