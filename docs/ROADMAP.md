# Tayo API — Roadmap

> **Source of truth for product intent:** `tayo-mobile/docs/ROADMAP.md`. This
> document mirrors that phase structure but describes it from the backend's
> side — which tables, endpoints, jobs and policies each phase requires. If
> the two ever disagree on what a phase *means*, the mobile roadmap wins;
> this file should be updated to match.
>
> **Status:** Phase 0 done. Phase 1 (auth, households, members, invite
> links + QR, placeholder activation, user profile edit, member profile
> edit + avatar upload, household profile settings) is fully implemented
> and tested — see `ARCHITECTURE.md` → "API Surface" and `DECISIONS.md`
> for what exists and why. Phase 2 is in progress: Family Notes and
> Announcements are done; only the `GET /api/v1/home` aggregation
> endpoint remains. Phase 3's core slice is done: event CRUD with
> `private`/`household` visibility and calendar-range querying — see the
> Phase 3 section below and `ARCHITECTURE.md` → "Foundation note — Events"
> for what's implemented now versus deferred (recurring events,
> `selected_households`/`all_member_households` visibility, participants,
> location).

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

## Phase 1 — Accounts & Households ✅ Done

### Done

- `POST /api/v1/auth/register`, `login`, `logout`, `GET /auth/me`
- `households` index/store/show/update
- `households/{household}/members` index/store
- `HouseholdRole` enum (`owner`, `adult`, `minor`, `child`)
- **Household invitations** — `household_invitations` table (single-use,
  expiring, hashed token). `POST /api/v1/households/{household}/invitations`
  (Owner/Adult only), `GET /api/v1/invitations/{token}` (public preview),
  `POST /api/v1/invitations/{token}/accept` (authenticated). Tested end to
  end, including on-device via the mobile deep link.
- **Member activation** — `member_activation_tokens` table (same shape as
  invitations). `POST /api/v1/households/{household}/members/{member}/activation-link`
  (Owner/Adult only, placeholder members only), `GET /api/v1/activation/{token}`
  (public preview), `POST /api/v1/activation/{token}/claim` (public; creates
  a `User`, links `member.user_id`, invalidates the token, logs the user in).
- **QR invitation** — no new backend work needed; the mobile app renders
  the same invite/activation link as a QR code and decodes one back into a
  token client-side. Verified end to end (including via a Playwright run
  with a synthetic camera feed) that a scanned code correctly round-trips
  through `GET /api/v1/invitations/{token}`.
- **User profile edit** — `PATCH /api/v1/auth/me` (`AuthController::updateProfile`).
- **Member profile detail/edit** — `PATCH
  /api/v1/households/{household}/members/{member}`. `role` is `sometimes`
  and `notIn(['owner'])` — the Owner's own row must omit the field
  entirely rather than resend a value that's always rejected (see
  `DECISIONS.md`).
- **Member avatar upload** — `POST
  /api/v1/households/{household}/members/{member}/avatar`, a dedicated
  multipart endpoint (not folded into the `PATCH` above, since PHP's
  `$_FILES` doesn't populate for multipart `PATCH`/`PUT` bodies). Stores
  to the `public` disk under `avatars/`, deletes the previous file on
  replace, exposes a relative `avatar_url` (not `Storage::url()`, to avoid
  baking in `APP_URL`) — see `DECISIONS.md`.
- **Household switching support** — already possible data-model-wise
  (`GET /api/v1/households` returns all memberships); no backend gap here,
  this was a mobile-only UI task (now built).
- **Default household** — nullable `users.default_household_id` FK.
  `PATCH /api/v1/auth/default-household` (`UpdateDefaultHouseholdRequest`
  validates the id belongs to one of the user's own households via
  `household_memberships`), kept separate from `updateProfile()` since that
  endpoint requires name+email unconditionally. The first household a user
  creates is auto-set as their default (`HouseholdController::store()`,
  only if they didn't already have one); later households don't override
  it. `UserResource` exposes `default_household_id` so the mobile app can
  open on the right household across sessions/devices.
- **Household profile settings** — `households.color` and `households.emoji`
  (nullable, hex/emoji strings), settable on `POST /api/v1/households` and
  `PATCH /api/v1/households/{household}` (Owner-only, per the existing
  `HouseholdPolicy::update`). Cosmetic only, not validated against a fixed
  palette server-side so the mobile-side color/emoji options can change
  without a backend deploy.

### Milestone

Matches mobile: register → create household → add members → invite →
manage household → edit member profiles (incl. avatar), fully backed by
real endpoints. Invite link and placeholder activation tested end to end.

---

## Phase 2 — Home & Family Feed (in progress — Family Notes done)

Backend delivers one aggregation endpoint; it must not become a second
source of truth for data owned by other modules (see `ARCHITECTURE.md` §9
"Home Architecture" in mobile docs).

### Done

- **Family notes** — `family_notes` table (`household_id`,
  `author_member_id`, `content`, `expires_at`). `GET`/`POST`/`DELETE
  /api/v1/households/{household}/notes`. Any household member can create
  one (via `HouseholdPolicy::addNote`); only the author or an Owner/Adult
  can delete one (`HouseholdPolicy::deleteNote`). `content` capped at 280
  characters, `expires_at` server-set to `now()->addDay()` — not
  client-settable. `FamilyNote::scopeActive` excludes expired notes from
  `index`; an hourly `Schedule::call` in `routes/console.php` deletes
  expired rows so the table doesn't grow unbounded (not
  exact-to-the-second — the query-time filter is what keeps `index`
  correct in the meantime, per the Milestone below).
- **Announcements** — `announcements` table (`household_id`,
  `author_member_id`, `content`), no `expires_at` — these are longer-lived
  than a family note and are removed manually rather than expiring.
  `GET`/`POST`/`DELETE /api/v1/households/{household}/announcements`.
  Unlike notes, only an Owner/Adult can create one
  (`HouseholdPolicy::addAnnouncement`) — a household bulletin, not a
  free-for-all; any member can read the list. Delete follows the same
  author-or-Owner/Adult rule as notes (`HouseholdPolicy::deleteAnnouncement`).
  `content` capped at 500 characters (longer than a note's 280, since
  these are meant to be read in full rather than glanced at).

### Remaining backend work

- `GET /api/v1/home` — assembles today's events, pending requests, family
  notes, announcements, today's meal, grocery summary, upcoming trips,
  household status from the owning modules. Scoped to the authenticated
  member + selected household. Deferred until there are enough real
  sources to aggregate — Family Notes and Announcements are the only ones
  that exist so far, so the mobile client calls their endpoints directly
  rather than through a two-source "aggregation" endpoint.
- No new table for "reminders" yet — reminders in Phase 2 are just
  read-through of other modules' due dates; a dedicated reminders/
  notification-preferences table belongs to Phase 9.

### Milestone

`GET /api/v1/home` alone can answer "what's happening today" without the
client stitching together five separate calls.

---

## Phase 3 — Calendar & Scheduling

### Core slice ✅ Implemented

- `events` table: `household_id`, `creator_member_id`, `title`,
  `description`, `start_at`, `end_at`, `visibility` (string, not a DB
  enum — see below), indexed on `(household_id, start_at)` for range
  queries.
- Endpoints: `GET`/`POST`/`PUT`/`DELETE .../households/{household}/events`,
  with `GET` supporting `?from=&to=` range filtering for month views.
- Visibility: only `private` (creator-only) and `household` (every
  member) for now — validated as a plain string rather than a DB enum
  specifically so the two levels below don't require a migration to add.
- Authorization (in `HouseholdPolicy`, alongside Notes/Announcements
  rather than a new policy class): creator can always edit/delete their
  own event; Owner/Adult can additionally manage a `household`-visibility
  event they didn't create; a `private` event stays creator-only
  regardless of role.
- No timezone column anywhere yet (`Household`/`Member` included) —
  `start_at`/`end_at` are stored as sent (UTC), and the mobile client
  converts device-local time at the edges.
- `event_participants` table (`event_id`, `member_id`, plain pivot):
  any household member can be tagged on an event via
  `participant_member_ids` on create/update, synced (not attached) each
  time. Validated against the event's own household membership list.
- `events.location` (nullable string, free-text — no geocoding).
- Member filtering — implemented entirely client-side (the mobile app
  filters the events `index` already returns by participant/creator);
  no API change was needed since that data was already in every payload.
- Multi-household visibility: `selected_households` (explicit list, via
  the new `event_households` pivot / `shared_household_ids`) and
  `all_member_households` (every household the creator belongs to,
  computed live — no stored rows). `EventController::index()` now checks
  all three "this event belongs in this household's list" paths (native,
  shared-in explicitly, shared-in via all-member-households) instead of
  a plain `household_id = X` scope. Sharing only widens *visibility*,
  never edit/delete rights — those stay scoped to the event's home
  household regardless of how many households it's shared to.

- Recurring events — a `recurring_rules` table (frequency, interval,
  `by_day`, and exactly one of `ends_at`/`occurrence_count`, capped at
  260 total occurrences) plus `events.recurring_rule_id`. Occurrences are
  materialized up front at creation time (`App\Support\RecurrenceGenerator`)
  rather than expanded on read or generated by a background job — there's
  no scheduler/queue infrastructure yet, and every recurring event must
  specify an end, so a bounded, one-shot generation is sufficient. Three
  deliberate scope cuts, worth knowing before extending this:
  - The pattern itself (frequency/interval/`by_day`/end condition) is set
    only at creation and is **immutable** afterward — there's no "edit
    the series' pattern," only "edit this occurrence" or "edit this and
    following."
  - Editing/deleting takes an `edit_scope`/`scope` of `this` (detaches
    the occurrence from the rule) or `following` (bulk-updates
    content-only fields — title/description/location/visibility — on
    this-and-later occurrences; never touches timing). There is no
    separate "all events" option — calling "following" on the first
    occurrence already covers that case.
  - A `RecurringRule` row is pruned once no `Event` rows reference it
    (see `EventController::pruneOrphanedRule()`).

### Deferred to a later increment

- The cross-household `GET /api/v1/events` endpoint — the per-household
  `index` enhancement above already satisfies the milestone below since
  the mobile app has no "all households combined" screen; a combined-view
  endpoint stays deferred until one exists to call it.
- Editable recurrence patterns, a true "all events" edit/delete scope,
  and generation via a background job for open-ended ("never ends")
  series — all deferred until there's scheduler/queue infrastructure and
  real demand past what the capped, immutable-pattern version above
  covers.

### Milestone

A household can run its own shared calendar (event CRUD, all 4
visibility levels, month-range queries, daily/weekly/monthly recurrence)
through the API, and a member belonging to multiple households sees the
right subset of events in each.

---

## Phase 4 — Family Requests — ✅ Done

- `permission_requests` table (`requester_member_id`, `household_id`,
  `type`, `status`, `requested_start_at`/`requested_end_at`, `title`,
  `description`, `responded_by_member_id`, `responded_at`,
  `response_note`, `promoted_event_id`) with status enum `pending`,
  `approved`, `declined`, `cancelled`, `expired`. Named `PermissionRequest`/
  `permission_requests` rather than the literal `Request`/`requests` —
  `Request` collides with `Illuminate\Http\Request`. `target_date` became
  a `requested_start_at`/`requested_end_at` pair instead of a single date,
  since the roadmap's own example ("Saturday 3:00–6:00 PM") is a time
  range and "promote to event" needs a start/end to copy.
- `request_conditions` table for adult-added conditions.
- `App\Models\Concerns\Approvable` trait (status transitions + scopes),
  shared by `PermissionRequest` now and `MealRequest` in Phase 5 — built
  now per this section's own suggestion, not speculatively.
- Endpoints: create/list/show/update (any member can create; the
  requester can edit/cancel only while pending), approve/decline/
  add-condition (adult only, policy-enforced, never on one's own
  request), and an "approve with `create_event: true`" option that
  atomically creates an `events` row from the request's time window
  (422 if the request has none) and records it on `promoted_event_id`.
- No per-request visibility levels — every household member can see
  every request, unlike Events' 4-level visibility.
- `expired` stays in the enum for schema completeness; nothing
  auto-transitions into it yet (genuinely Phase 9/Scheduler territory).
- `meal_requests` — built in Phase 5, reusing `Approvable` unchanged.
- Notification hook: `App\Events\PermissionRequestCreated`/`Approved`/
  `Declined` are dispatched with no listeners yet — the extension point
  Phase 9 will consume.

### Milestone

Adults can approve or decline a minor's request entirely through the API,
with conditions and an optional calendar event created atomically. Met.

---

## Phase 5 — Meals & Groceries — ✅ Done — **MVP boundary**

- Tables: `meal_plan_items`, `meal_requests`, `grocery_items`. **No
  `meal_plans` or `grocery_lists` parent tables** — a deliberate
  deviation from the schema originally sketched above. Neither earns
  its keep for v1: there's exactly one ongoing grocery list per
  household, and meal plan items are naturally queried by
  `household_id` + date range with no need for a week-grouping parent
  row — this mirrors how `events` has no "calendar" parent row.
  `meal_plan_items` and `grocery_items` each carry `household_id`
  directly.
- Endpoints under `/api/v1/households/{household}/meal-plan-items`,
  `/meal-requests`, and `/grocery-items` (not `/meal-plans`/
  `/grocery-lists`, following from the no-parent-table decision above).
- `meal_plan_items` has no dedicated update endpoint — `store()` is an
  upsert keyed on `(household_id, date, slot)`, since "change what's
  for Tuesday dinner" and "set Tuesday dinner" are the same action from
  the user's perspective. Only `index`/`store`/`destroy` exist.
  (Implementation note: Eloquent's `updateOrCreate()` doesn't reliably
  match an existing row when a matched column has a non-trivial cast —
  here, `slot`'s backed enum — so the upsert is an explicit
  find-then-write, `MealPlanItem::upsertFor()`.)
- Meal request flow mirrors Phase 4's approval mechanics exactly,
  reusing the `Approvable` trait unchanged: member requests → adult
  approves/declines → becomes a `meal_plan_items` row. "Adult can move
  it to another day" is folded into `approve()`'s optional `date`/
  `slot` overrides rather than a separate endpoint — mirrors how
  `PermissionRequestController::approve()` already takes an optional
  `create_event` rather than a separate promote endpoint.
- `HouseholdPolicy`: meal plan items are Owner/Adult-managed only (any
  adult can add/edit/delete any item — a meal plan is a shared
  household artifact, not a personal post, unlike Family Notes'
  "author or adult" rule). Grocery items started fully open to any
  household member including minors for every action; later narrowed
  (still within Phase 5) so editing/removing an item and the bulk
  clear-purchased action are Owner/Adult only, while adding an item and
  checking it off stay open to everyone, including minors — those are
  the everyday "shopping" actions, split across `addGroceryItem`
  (add/toggle) and `manageGroceryItem` (edit/delete/clear).
- Explicitly did not build a recipe engine or meal→ingredient→grocery
  auto-generation in this phase (mobile roadmap is explicit about
  this).
- Grocery "shopping mode" is a client-side concern (checking an item
  off *is* shopping mode) — no new table for it.
- Follow-up additions after the initial Phase 5 build: a per-household
  **meal approver** setting (`households.meal_approver_member_id`) —
  when set, that one member fully replaces the Owner/Adult check for
  managing the meal plan and acting on meal requests, so even the
  Owner must go through the request flow once delegated; a bulk
  `clear-purchased` grocery endpoint; and auto-expiring an overdue
  pending meal request (date passed, still `pending`) to `expired` the
  next time the household's requests are listed, lazily rather than via
  a scheduler.

### Milestone

Everything in the mobile MVP list has a backing endpoint: accounts,
households, members, placeholders, invitations, activation, home, notes,
announcements, reminders, calendar, scheduling, permission requests, meal
planning, meal requests, grocery list. **This is the API's MVP-complete
line**, matching the mobile roadmap's MVP boundary. Met.

---

## Phase 6 — Tasks & Chores (V1.1) ✅ Done

- One `tasks` table, not the four originally sketched here — reuses the
  existing `recurring_rules` table verbatim (same as Events) and inlines
  single-assignee/completion as `assigned_member_id`/`completed_at`/
  `completed_by_member_id` columns, matching the single-assignee/inline-
  status conventions already used by `grocery_items` and `meal_requests`.
  Consistent with how Phase 3 and Phase 5 each simplified their own
  original multi-table sketch once built.
- Recurring task generation is a backend job (`tasks:generate-occurrences`,
  Laravel Scheduler, daily), never client-driven — mobile must not be the
  thing that "creates" this week's recurring chore. Generates one
  occurrence at a time into a 2-day lookahead buffer (`RecurringTaskOccurrenceGenerator`),
  unlike Events' eager-at-creation expansion, since a chore has no natural
  end-of-range to bound an eager expansion against. Idempotent against
  same-day re-runs and safe to catch up after extended downtime via a
  `unique(recurring_rule_id, due_at)` index.
- `HouseholdPolicy`: creating/editing/deleting a task is Owner/Adult only
  (`addTask`/`manageTask`); marking complete/incomplete is open to the
  assignee (even a minor) or an Owner/Adult (`toggleTask`) — the same
  any-member-for-the-everyday-action split as grocery items.
- Endpoints: CRUD under `/api/v1/households/{household}/tasks`, plus
  `/complete` and `/uncomplete`, `index` filterable by
  `assignee_member_id`/`status` (pending/completed/overdue)/`due_before`/
  `due_after`.

### Milestone

Recurring chores keep generating and staying assigned even if nobody
opens the app for a week. Met — covered by
`tests/Feature/Tasks/TaskRecurrenceGenerationTest.php`, including a
7-day "nobody opened the app" catch-up scenario.

---

## Phase 7 — Trips & Family Events (V1.2) ✅ Done

- Tables: `trips`, `trip_participants`, `trip_itinerary_items`,
  `trip_memories`. No `trip_checklists`/`trip_checklist_items` tables —
  a trip's checklist and "trip tasks" are the same thing: the existing
  Phase 6 `tasks` table gained a nullable `trip_id` FK, so a checklist
  item is just `Task::where('trip_id', ...)`, reusing its assignment/
  completion columns and policy tier rather than duplicating them.
  Grocery integration is the identical move — a nullable `trip_id` on
  the existing `grocery_items` table, not a `grocery_lists` row (that
  table was never built; see Phase 5's note on why).
- Countdown is computed (`trip.start_at` vs `now()` in `TripResource`),
  never a stored, mutable field.
- Trip dates/itinerary are exposed to the calendar without data
  duplication: `EventController::index()` calls a new
  `TripCalendarProjector` that synthesizes non-persisted, `EventResource`
  -shaped entries (namespaced ids like `trip-5`/`trip-itinerary-12`, a
  `type` discriminator) and merges them into the response. No `events`
  row is ever created for a trip — the same "computed live, not stored"
  technique the `all_member_households` visibility branch already used.
- A trip can have a thumbnail (`thumbnail_path`, Owner/Adult upload,
  mirrors the member-avatar-upload endpoint shape) and `trip_memories` —
  one free-text "memorable moment" per member per trip (upsert on
  repost), any member can add their own, author-or-Owner/Adult can
  delete (mirrors Family Notes' deletion rule).

### Milestone

A trip's itinerary, checklist and dates are fully API-backed and show up
in the shared calendar without data duplication. Met — covered by
`tests/Feature/Trips/TripCalendarIntegrationTest.php`, which also
asserts the `events` table's row count never changes when a trip or its
itinerary is created.

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

## Phase 9 — Realtime, Notifications & Automation (V1.4) — partially done

Scoped down to just the automation piece — Reverb and FCM both need
external service/account setup (Firebase project, APNs certs, a running
WebSocket server) that wasn't feasible to do in one sitting; they stay
deferred, tracked below.

- Automation ✅ Done, with no new infra: `notification_preferences`
  table (`member_id`, `category`, `enabled`, default enabled — opt-out,
  since these are low-stakes nudges, not consent-sensitive data) and a
  `ReminderComputer` support class that computes reminder state **live
  from existing data at read time** — no `reminders` table, same
  "computed at read, never stored" technique used for
  `all_member_households` calendar visibility and overdue-task
  detection. Three categories: `meal_planning` (next week unplanned,
  only fires Thursday onward), `grocery` (list empty or untouched for a
  week), `trip_prep` (a trip within 7 days still has incomplete
  checklist items — the same Phase 7 `tasks.trip_id` reuse, so no new
  "checklist" concept here either). `event_reminders` was dropped from
  this cut: a timed "remind me before X" reminder needs push or local
  notifications to mean anything — there's nothing a read-time
  computation can do for it. Recurring chore generation ("formalizes
  what Phase 6 started ad hoc") needed no new code — Phase 6's
  `tasks:generate-occurrences` already satisfies it.
- Realtime (Reverb) — **deferred**, not started.
- Push notifications (FCM) — **deferred**, not started. This also means
  the automation above is in-app only (surfaced on next app open), not
  a true push while the app is closed.

### Milestone

The backend proactively pushes relevant updates instead of only
answering when asked. **Not yet met** — reminders are computed and
exposed via `GET /households/{household}/reminders`, but nothing is
pushed; the client has to ask. Revisit once Reverb/FCM are set up.

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
| 1 | Accounts + Households + Members | household_invitations, member_activation_tokens | Phase 0 — ✅ Done |
| 2 | Home + Family Feed | family_notes, announcements | Phase 1 |
| 3 | Calendar + Scheduling | events, event_participants, event_households, recurring_rules | Phase 1 |
| 4 | Family Requests | permission_requests, request_conditions | Phase 1, 3 (for promote-to-event) — ✅ Done |
| 5 | Meals + Groceries | meal_plan_items, meal_requests, grocery_items | Phase 4 (approval flow) — ✅ Done — **MVP line** |
| 6 | Tasks + Chores | tasks (reuses recurring_rules) | Phase 1 — ✅ Done |
| 7 | Trips + Events | trips, trip_participants, trip_itinerary_items, trip_memories (reuses tasks/grocery_items) | Phase 3, 5, 6 — ✅ Done |
| 8 | Family Map + Location | member_location_settings, member_locations, saved_places, geofences | Phase 1 + privacy review |
| 9 | Realtime + Automation | notification_preferences | Reverb, FCM infra — automation done, realtime/push deferred |
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
