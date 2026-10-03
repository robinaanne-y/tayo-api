<?php

namespace App\Policies;

use App\Enums\HouseholdRole;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\FamilyNote;
use App\Models\Household;
use App\Models\MealRequest;
use App\Models\PermissionRequest;
use App\Models\Task;
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
        if ($note->household_id !== $household->id) {
            return false;
        }

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
        if ($announcement->household_id !== $household->id) {
            return false;
        }

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
        if ($event->household_id !== $household->id) {
            return false;
        }

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
        if ($permissionRequest->household_id !== $household->id) {
            return false;
        }

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
        if ($permissionRequest->household_id !== $household->id) {
            return false;
        }

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
        if ($permissionRequest->household_id !== $household->id) {
            return false;
        }

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
        if ($permissionRequest->household_id !== $household->id) {
            return false;
        }

        $membership = $user->membershipFor($household);

        if ($membership === null) {
            return false;
        }

        return ($membership->role?->canManageHousehold() ?? false)
            && $permissionRequest->requester_member_id !== $membership->member_id;
    }

    /**
     * A meal plan item is a shared household artifact, not a personal
     * post like a Family Note. When the household has designated a
     * meal approver (`meal_approver_member_id`), that one member fully
     * replaces the role check -- not additive -- so even the Owner must
     * go through the request flow. With no approver set, any Owner/
     * Adult can add, edit, or remove any item, not just the one who
     * added it. Viewing the plan itself needs no dedicated gate: the
     * existing `view` ability (any household member) already covers
     * it, same as `index()` on every other household-scoped list.
     */
    public function addMealPlanItem(User $user, Household $household): bool
    {
        $membership = $user->membershipFor($household);

        if ($membership === null) {
            return false;
        }

        if ($household->meal_approver_member_id !== null) {
            return $membership->member_id === $household->meal_approver_member_id;
        }

        return $membership->role?->canManageHousehold() ?? false;
    }

    public function manageMealPlanItem(User $user, Household $household): bool
    {
        return $this->addMealPlanItem($user, $household);
    }

    /**
     * Any member can request a meal (mirrors addRequest).
     */
    public function addMealRequest(User $user, Household $household): bool
    {
        return $user->membershipFor($household) !== null;
    }

    public function updateMealRequest(User $user, Household $household, MealRequest $mealRequest): bool
    {
        if ($mealRequest->household_id !== $household->id) {
            return false;
        }

        $membership = $user->membershipFor($household);

        if ($membership === null) {
            return false;
        }

        return $mealRequest->requester_member_id === $membership->member_id && $mealRequest->isPending();
    }

    public function cancelMealRequest(User $user, Household $household, MealRequest $mealRequest): bool
    {
        return $this->updateMealRequest($user, $household, $mealRequest);
    }

    /**
     * Same approver-replaces-role logic as addMealPlanItem, combined
     * with actOnRequest's own rules: never the requester,
     * pending only.
     */
    public function actOnMealRequest(User $user, Household $household, MealRequest $mealRequest): bool
    {
        if ($mealRequest->household_id !== $household->id) {
            return false;
        }

        $membership = $user->membershipFor($household);

        if ($membership === null) {
            return false;
        }

        $canManage = $household->meal_approver_member_id !== null
            ? $membership->member_id === $household->meal_approver_member_id
            : ($membership->role?->canManageHousehold() ?? false);

        return $canManage
            && $mealRequest->requester_member_id !== $membership->member_id
            && $mealRequest->isPending();
    }

    public function acknowledgeMealRequest(User $user, Household $household, MealRequest $mealRequest): bool
    {
        if ($mealRequest->household_id !== $household->id) {
            return false;
        }

        $membership = $user->membershipFor($household);

        return $membership !== null && $mealRequest->requester_member_id === $membership->member_id;
    }

    /**
     * Adding an item and checking it off are as open as the shared list
     * itself -- any household member, including minors -- since those
     * are the everyday "shopping" actions and the stakes of a wrong
     * check-off are low. Editing an item's details, removing it, or
     * clearing the purchased list are more consequential/structural, so
     * those are Owner/Adult only (see `manageGroceryItem`).
     */
    public function addGroceryItem(User $user, Household $household): bool
    {
        return $user->membershipFor($household) !== null;
    }

    public function toggleGroceryItem(User $user, Household $household): bool
    {
        return $this->addGroceryItem($user, $household);
    }

    public function manageGroceryItem(User $user, Household $household): bool
    {
        $role = $user->membershipFor($household)?->role;

        return $role?->canManageHousehold() ?? false;
    }

    /**
     * Creating/structuring a task is Owner/Adult only -- a household
     * responsibility isn't a free-for-all post like a Family Note.
     */
    public function addTask(User $user, Household $household): bool
    {
        $role = $user->membershipFor($household)?->role;

        return $role?->canManageHousehold() ?? false;
    }

    /**
     * Editing (reassigning, changing title/due date) or deleting a task is
     * the same structural tier as creating one.
     */
    public function manageTask(User $user, Household $household, Task $task): bool
    {
        if ($task->household_id !== $household->id) {
            return false;
        }

        $role = $user->membershipFor($household)?->role;

        return $role?->canManageHousehold() ?? false;
    }

    /**
     * Marking a task complete/incomplete is as open as the shared grocery
     * list's purchase toggle -- the assignee (even a minor) can check off
     * their own chore without needing an adult, and an Owner/Adult can
     * complete/uncomplete anyone's.
     */
    public function toggleTask(User $user, Household $household, Task $task): bool
    {
        if ($task->household_id !== $household->id) {
            return false;
        }

        $membership = $user->membershipFor($household);

        if ($membership === null) {
            return false;
        }

        return $task->assigned_member_id === $membership->member_id
            || ($membership->role?->canManageHousehold() ?? false);
    }
}
