# Tayo API — Roadmap

> **Source of truth for product intent:** `tayo-mobile/docs/ROADMAP.md`. This
> document mirrors that phase structure but describes it from the backend's
> side — which tables, endpoints, jobs and policies each phase requires. If
> the two ever disagree on what a phase *means*, the mobile roadmap wins;
> this file should be updated to match.
>
> **Status:** Phase 0 done. Phase 1's core slice (auth, households, members)
> is implemented — see `ARCHITECTURE.md` → "API Surface" and `DECISIONS.md`
> for what exists and why. Invitations/activation, the remainder of Phase 1,
> are not started.

## Ground Rules

- The API should stay at least one phase ahead of, or landing alongside,
  the mobile client — Flutter should never need to build against an
  endpoint that doesn't exist yet.
- Every new phase gets: migrations, Eloquent models, form requests,
  policies, API resources, feature tests, and a `routes/api.php` entry —
  per the `Definition of Done` in the mobile roadmap. A phase isn't done
  because the routes respond; it's done when it's authorized, validated,
  and tested.
- New tables get a foreign key to `households` or `members` (directly or
  transitively) unless there's a specific reason not to — see
  `ARCHITECTURE.md` → Domain Model.
- Keep the modular monolith. Don't introduce a new service, queue driver,
  or infrastructure dependency before the phase that needs it (ADR-002).

---

## Phase 0 — Foundation ✅ Done

- Laravel project, PostgreSQL, Sanctum, versioned `/api/v1` routes.
- `users`, `households`, `members`, `household_memberships`,
  `personal_access_tokens`.
- `HouseholdPolicy`, transactional household/member creation, PHPUnit
  in-memory SQLite suite.

---

## Phase 1 — Accounts & Households (in progress)

### Done

- `POST /api/v1/auth/register`, `login`, `logout`, `GET /auth/me`
- `households` index/store/show/update
- `households/{household}/members` index/store
- `HouseholdRole` enum (`owner`, `adult`, `minor`, `child`)

### Remaining backend work

- **Member activation tokens** — new `member_activation_tokens` table
  (random, single-use, expirable; see `ARCHITECTURE.md` §7 in mobile docs
  for the shape). Endpoints:
  - `POST /api/v1/households/{household}/members/{member}/activation-link`
    (Owner/Adult only — generates/rotates the token)
  - `POST /api/v1/activation/{token}/claim` (public; creates a `User`,
    links `member.user_id`, invalidates the token)
- **Invite link / QR** — an invite is a household-level token distinct
  from member activation (it lets a *new* person join, rather than
  claiming an existing placeholder). Needs its own table
  (`household_invitations`) and accept endpoint.
- **User profile edit** — `PATCH /api/v1/auth/me` or `PATCH /api/v1/users/me`.
- **Household profile (richer)** — extend the `households` resource/update
  payload once mobile defines what "settings" means (avatar, timezone,
  etc.) — don't speculate on fields ahead of the UI.
- **Member profile detail/edit** — `GET`/`PATCH
  /api/v1/households/{household}/members/{member}`.
- **Household switching support** — already possible data-model-wise
  (`GET /api/v1/households` returns all memberships); no backend gap here,
  this is a mobile-only UI task.

### Milestone

Matches mobile: register → create household → add members → invite →
manage household, fully backed by real endpoints (not just the slice that
works today).

---

## Phase 2 — Home & Family Feed

Backend delivers one aggregation endpoint; it must not become a second
source of truth for data owned by other modules (see `ARCHITECTURE.md` §9
"Home Architecture" in mobile docs).

- `GET /api/v1/home` — assembles today's events, pending requests, family
  notes, announcements, today's meal, grocery summary, upcoming trips,
  household status from the owning modules. Scoped to the authenticated
  member + selected household.
- `family_notes` table (`household_id`, `author_member_id`, `content`,
  `expires_at`) + `POST`/`GET`/`DELETE
  /api/v1/households/{household}/notes`.
- Scheduled job to purge/deactivate expired notes (Laravel Scheduler,
  not exact-to-the-second).
- `announcements` table + basic CRUD, household-scoped.
- No new table for "reminders" yet — reminders in Phase 2 are just
  read-through of other modules' due dates; a dedicated reminders/
  notification-preferences table belongs to Phase 9.

### Milestone

`GET /api/v1/home` alone can answer "what's happening today" without the
client stitching together five separate calls.

---

## Phase 3 — Calendar & Scheduling

- Tables: `events`, `event_participants`, `event_households`,
  `recurring_rules`.
- `events` fields: creator/member, title, description, start_at, end_at,
  location, visibility, recurrence reference.
- Visibility enum: `private`, `household`, `selected_households`,
  `all_member_households` — every read path must filter through this,
  not just the write path.
- Endpoints under `/api/v1/households/{household}/events` plus a
  cross-household `GET /api/v1/events` that respects visibility for the
  authenticated member.
- Authorization: event creator can edit/delete; household Owner/Adult can
  manage household-visibility events; policy must check membership in
  *every* household an event is shared to, not just the creating one.

### Milestone

A household can run its shared calendar entirely through the API,
including a member who belongs to more than one household seeing the
right subset of events in each.

---

## Phase 4 — Family Requests

- `requests` table (requester_member_id, household_id, type, status,
  target_date, title, description) with status enum `pending`,
  `approved`, `declined`, `cancelled`, `expired`.
- `request_conditions` table for adult-added conditions.
- Endpoints: create (minor), list, approve/decline/add-condition (adult
  only — policy-enforced), optional "promote to calendar event" action
  that creates an `events` row from an approved request.
- `meal_requests` — see Phase 5, but the approval/notification mechanics
  are shared with permission requests; consider a shared
  `Approvable`/status trait rather than duplicating logic.
- Notification hook: approving/declining/creating a request should be a
  clear extension point for Phase 9, even before push notifications exist
  (e.g. dispatch a job/event now that Phase 9 later listens to).

### Milestone

Adults can approve or decline a minor's request entirely through the API,
with conditions and an optional calendar event created atomically.

---

## Phase 5 — Meals & Groceries — **MVP boundary**

- Tables: `meal_plans`, `meal_plan_items`, `meal_requests`.
- Tables: `grocery_lists`, `grocery_items` (name, quantity, unit,
  category, added_by, purchased_at, purchased_by).
- Endpoints under `/api/v1/households/{household}/meal-plans` and
  `/api/v1/households/{household}/grocery-lists`.
- Meal request flow mirrors Phase 4's approval mechanics: member requests
  → adult approves/declines/moves-day → becomes a `meal_plan_items` row.
- Explicitly do not build a recipe engine or meal→ingredient→grocery
  auto-generation in this phase (mobile roadmap is explicit about this).
- Grocery "shopping mode" is a client-side concern — no new table for it.

### Milestone

Everything in the mobile MVP list has a backing endpoint: accounts,
households, members, placeholders, invitations, activation, home, notes,
announcements, reminders, calendar, scheduling, permission requests, meal
planning, meal requests, grocery list. **This is the API's MVP-complete
line**, matching the mobile roadmap's MVP boundary.

---

## Phase 6 — Tasks & Chores (V1.1)

- Tables: `tasks`, `task_assignments`, `task_recurrences`,
  `task_completions`.
- Recurring task generation is a backend job (Laravel Scheduler), never
  client-driven — mobile must not be the thing that "creates" this week's
  recurring chore.
- Endpoints: CRUD under `/api/v1/households/{household}/tasks`, assign,
  complete, list by assignee/status/due date.

### Milestone

Recurring chores keep generating and staying assigned even if nobody
opens the app for a week.

---

## Phase 7 — Trips & Family Events (V1.2)

- Tables: `trips`, `trip_participants`, `trip_itinerary_items`,
  `trip_checklists`, `trip_checklist_items`.
- Countdown is computed (`trip.start_at - now()`), never a stored,
  mutable field.
- Trip dates/itinerary should be exposed to the calendar (Phase 3) through
  the application layer — not duplicated as separate `events` rows.
- Trip grocery integration: a trip can reference/create a
  `grocery_lists` row rather than inventing a parallel list type.

### Milestone

A trip's itinerary, checklist and dates are fully API-backed and show up
in the shared calendar without data duplication.

---

## Phase 8 — Family Map & Location (V1.3)

- Tables: `member_location_settings`, `member_locations`,
  `saved_places`, `geofences`.
- Sharing mode enum: `off`, `temporary`, `always`, `while_using_app`.
- Start with coarse/last-known location; do not store high-frequency GPS
  history in PostgreSQL without a concrete feature requirement.
- This phase needs an explicit security/privacy review before
  implementation (ADR needed) — location endpoints get extra scrutiny per
  `ARCHITECTURE.md` §27.

### Milestone

"Where is everyone?" is answerable via the API, opt-in only, with no
member's precise location exposed to a household member who shouldn't
see it.

---

## Phase 9 — Realtime, Notifications & Automation (V1.4)

- Introduce Laravel Reverb for: grocery list changes, task completion,
  permission requests, meal requests, trip checklist updates, household
  announcements. Not every mutation needs to broadcast — only where it
  materially improves the experience (mobile roadmap is explicit here).
- Introduce Firebase Cloud Messaging for push. Notifications are
  generated from backend events/jobs, never triggered client-side.
- `notification_preferences` table (per-member, per-category) — this is
  the first phase that actually needs it; earlier phases can hardcode
  "always notify."
- Automation via Laravel Scheduler + queues: weekly meal-planning
  reminder, recurring grocery reminder, trip-prep reminders, event
  reminders, recurring chore generation (formalizes what Phase 6 started
  ad hoc).

### Milestone

The backend proactively pushes relevant updates instead of only
answering when asked.

---

## Phase 10 — Monetization (after product validation)

- `households` gains subscription/entitlement fields (plan, max_members,
  feature flags) — subscription state lives on the household, not the
  individual member, per `ARCHITECTURE.md` §34.
- RevenueCat webhook endpoint to sync mobile purchase state → household
  entitlements.
- Laravel remains the enforcement point for every gated feature — never
  trust a client-reported entitlement.

### Milestone

Feature access is enforced server-side based on household plan, with
RevenueCat as the source of truth for payment state only.

---

## Condensed Roadmap

| Phase | Focus | New tables (rough) | Depends on |
|---|---|---|---|
| 0 | Foundation | users, households, members, household_memberships | — ✅ |
| 1 | Accounts + Households + Members | member_activation_tokens, household_invitations | Phase 0 (in progress) |
| 2 | Home + Family Feed | family_notes, announcements | Phase 1 |
| 3 | Calendar + Scheduling | events, event_participants, event_households, recurring_rules | Phase 1 |
| 4 | Family Requests | requests, request_conditions | Phase 1, 3 (for promote-to-event) |
| 5 | Meals + Groceries | meal_plans, meal_plan_items, meal_requests, grocery_lists, grocery_items | Phase 4 (approval flow) — **MVP line** |
| 6 | Tasks + Chores | tasks, task_assignments, task_recurrences, task_completions | Phase 1 |
| 7 | Trips + Events | trips, trip_participants, trip_itinerary_items, trip_checklists, trip_checklist_items | Phase 3, 5 |
| 8 | Family Map + Location | member_location_settings, member_locations, saved_places, geofences | Phase 1 + privacy review |
| 9 | Realtime + Automation | notification_preferences | Reverb, FCM infra |
| 10 | Monetization | subscription/entitlement fields on households | RevenueCat, product validation |

---

## Infrastructure Introduction Order

Matches `ARCHITECTURE.md` §33 — do not add these ahead of the phase that
actually needs them:

```text
MVP (Phases 0-5): Laravel + PostgreSQL only
Phase 6-8:        + Scheduler/queue usage grows, no new infra
Phase 9:          + Laravel Reverb, + Firebase Cloud Messaging
Phase 8 (files):  + Amazon S3 (if/when profile or trip images ship)
Phase 8 (maps):   + Maps provider (Google Maps or Mapbox)
Phase 10:         + RevenueCat webhook integration
```
