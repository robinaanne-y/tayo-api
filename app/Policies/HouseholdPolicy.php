<?php

namespace App\Policies;

use App\Enums\HouseholdRole;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\FamilyNote;
use App\Models\Household;
use App\Models\PermissionRequest;
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
     * A private event is only visible to its creator; a household,
     * selected_households, or all_member_households event is visible to
     * every member of $household — this method doesn't itself check
     * whether $household is actually one the event was shared into
     * (EventController::index does that); it just answers "is this member
     * allowed to see this event within this household's context".
     */
    public function viewEvent(User $user, Household $household, Event $event): bool
    {
        $membership = $user->membershipFor($household);

        if ($membership === null) {
            return false;
        }

        return in_array($event->visibility, ['household', 'selected_households', 'all_member_households'], true)
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

        // Reached only through the event's home household route (a
        // cross-household attempt already 404s in EventController::eventFor),
        // so this never grants a shared-into household's Owner/Adult edit
        // rights — only the home household's.
        return in_array($event->visibility, ['household', 'selected_households', 'all_member_households'], true)
            && ($membership->role?->canManageHousehold() ?? false);
    }

    /**
     * Any member can submit a request — including a minor or an adult
     * asking a co-parent for sign-off.
     */
    public function addRequest(User $user, Household $household): bool
    {
        return $user->membershipFor($household) !== null;
    }

    /**
     * Every household request is visible to every household member —
     * unlike events, requests have no per-item visibility levels.
     */
    public function viewRequest(User $user, Household $household): bool
    {
        return $user->membershipFor($household) !== null;
    }

    /**
     * Only the requester can edit their own request, and only while it's
     * still pending — once an adult has responded, the content is locked.
     */
    public function updateRequest(User $user, Household $household, PermissionRequest $permissionRequest): bool
    {
        $membership = $user->membershipFor($household);

        if ($membership === null) {
            return false;
        }

        return $permissionRequest->requester_member_id === $membership->member_id && $permissionRequest->isPending();
    }

    /**
     * Only the requester can clear their own "needs attention" notification
     * for a request that's been approved/declined.
     */
    public function acknowledgeRequest(User $user, Household $household, PermissionRequest $permissionRequest): bool
    {
        $membership = $user->membershipFor($household);

        return $membership !== null && $permissionRequest->requester_member_id === $membership->member_id;
    }

    /**
     * Only the requester can withdraw their own request, and only while
     * it's still pending.
     */
    public function cancelRequest(User $user, Household $household, PermissionRequest $permissionRequest): bool
    {
        return $this->updateRequest($user, $household, $permissionRequest);
    }

    /**
     * Approving or declining is an Owner/Adult action, only while the
     * request is still pending (prevents re-approving/re-declining, which
     * would also let a "create_event" approval promote a second, duplicate
     * event) — and never on one's own request, since that would let
     * someone approve their own ask (e.g. an adult filing a request meant
     * for a co-parent's sign-off).
     */
    public function actOnRequest(User $user, Household $household, PermissionRequest $permissionRequest): bool
    {
        $membership = $user->membershipFor($household);

        if ($membership === null) {
            return false;
        }

        return ($membership->role?->canManageHousehold() ?? false)
            && $permissionRequest->requester_member_id !== $membership->member_id
            && $permissionRequest->isPending();
    }

    /**
     * Adding a condition is an Owner/Adult action, at any point in a
     * request's life (a minor administrative note, not worth restricting
     * by status) — but still never on one's own request.
     */
    public function addRequestCondition(User $user, Household $household, PermissionRequest $permissionRequest): bool
    {
        $membership = $user->membershipFor($household);

        if ($membership === null) {
            return false;
        }

        return ($membership->role?->canManageHousehold() ?? false)
            && $permissionRequest->requester_member_id !== $membership->member_id;
    }
}
