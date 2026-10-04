# Tayo API Architecture Decisions

This document records decisions that shape the current API. It is intentionally
short and should be updated when an accepted design choice changes.

## ADR-001: Use A Versioned REST API

- **Status:** Accepted
- **Date:** 2026-09-04

### Decision

Expose the mobile contract under `/api/v1` and keep route definitions in
`routes/api.php`.

### Rationale

The Flutter client needs a stable HTTP and JSON boundary. Versioning provides a
place to evolve that contract without silently changing existing mobile builds.

### Consequences

New API capabilities should be added under the current version unless a
breaking contract requires a new version. Response and validation changes need
to consider already-released mobile clients.

## ADR-002: Keep The Backend As A Modular Monolith

- **Status:** Accepted
- **Date:** 2026-09-04

### Decision

Keep the API in one Laravel application and use Laravel's existing application,
HTTP, model, resource, request, and policy boundaries. Do not split the current
household foundation into microservices.

### Rationale

The current domain is small, and one deployable service keeps local development,
testing, transactions, and authorization straightforward. Domain boundaries can
still be made explicit as features grow.

### Consequences

The codebase should maintain clear module ownership inside the monolith. A
separate service should be introduced only when scaling, isolation, or
operational requirements justify its cost.

## ADR-003: Model People Separately From Accounts

- **Status:** Accepted
- **Date:** 2026-08-30

### Decision

Use `User` for authentication accounts and `Member` for people represented in a
household. A member's `user_id` is nullable.

### Rationale

A household may need to manage children or other people who do not have accounts.
Separating the concepts preserves their household data before and after account
activation and avoids forcing every member to register.

### Consequences

Code must not assume every member has a user. Account activation can link a user
to an existing member later without moving household-related records.

## ADR-004: Represent Household Membership Explicitly

- **Status:** Accepted
- **Date:** 2026-08-30

### Decision

Connect members and households through `household_memberships` rather than
putting a single `household_id` on either account or member records.

### Rationale

A person may belong to more than one household. The membership is also the
natural place for household-specific role, status, and join-time data.

### Consequences

All household-scoped authorization must evaluate the relevant membership. The
membership table has a unique household/member pair to prevent duplicates.

## ADR-005: Use Sanctum Personal Access Tokens For Mobile Authentication

- **Status:** Accepted
- **Date:** 2026-08-30

### Decision

Issue Laravel Sanctum personal access tokens at registration and login. Mobile
clients send them using the `Authorization: Bearer` header.

### Rationale

This is a direct fit for a native Flutter client and avoids coupling mobile
authentication to browser cookies or a server-rendered session.

### Consequences

Tokens must be stored using secure platform storage on the client. Logout
currently revokes the authenticated current token. Token expiry, rotation, and
revocation policy should be revisited before production release.

## ADR-006: Use PostgreSQL For Development And SQLite For Fast Tests

- **Status:** Accepted
- **Date:** 2026-09-04

### Decision

Use PostgreSQL through Docker Compose for local application development, while
PHPUnit uses an in-memory SQLite database by default.

### Rationale

PostgreSQL matches the intended relational deployment environment. In-memory
SQLite keeps the test suite fast and isolated.

### Consequences

Database-specific behavior, migration compatibility, and PostgreSQL constraints
must be checked separately when they are relevant. A passing SQLite test suite
alone does not prove PostgreSQL compatibility.

## ADR-007: Store Member Avatars On The Local Public Disk With Relative URLs

- **Status:** Accepted
- **Date:** 2026-09-22

### Decision

Store member avatar uploads on Laravel's local `public` disk under
`avatars/`, referenced by `members.avatar_path`. Serve them through a
dedicated `POST /households/{household}/members/{member}/avatar`
multipart endpoint rather than folding the file into the JSON `PATCH
/members/{member}` payload. Expose the avatar to clients as a relative
path (`/storage/{path}`) via a `Member::avatarUrl` accessor, not the
absolute URL that `Storage::disk('public')->url()` would produce.

### Rationale

A dedicated endpoint is required because PHP does not populate `$_FILES`
for multipart `PATCH`/`PUT` request bodies, so a file upload can't be
merged into the existing member-update route without a workaround. Local
disk storage matches the "don't add infrastructure before the phase that
needs it" rule (ADR-002); S3 is deferred to Phase 8 per the mobile
roadmap's Infrastructure Introduction Order. A relative URL is required
because `Storage::url()` bakes in `.env`'s `APP_URL`, which is
`http://localhost:8000` in development — a value that only resolves
correctly on the machine running the server. A physical mobile device on
the same LAN (or any client using a different `API_BASE_URL`) would fail
to load the image if the backend returned that absolute URL. Returning a
relative path lets each client resolve it against whatever host it's
already configured to reach the API on.

### Consequences

Every client must resolve `avatar_url` against its own API base host
(mobile does this via `Env.mediaBaseUrl`). Moving to S3 (or any CDN) later
means changing `Member::avatarUrl` to return an absolute URL again, and at
that point clients must stop prepending their own host — this is a
breaking contract change for `avatar_url`'s shape, not just its storage
backend, and should be versioned accordingly.

## ADR-008: Family Map & Location — Consent, Storage, and Visibility Model

- **Status:** Accepted
- **Date:** 2026-10-04

### Decision

Phase 8 ("Where is everyone?") ships with these constraints, satisfying
the roadmap's requirement for a privacy review before implementation:

1. **Self-service sharing only.** A member's location-sharing setting
   can only be changed by that member's own authenticated session. No
   API surface lets an Owner/Adult enable or configure sharing on
   another member's behalf — including a Minor/Child member. A
   placeholder member with no `User` account (no device, no way to
   consent) simply cannot participate; there is no "parent enables
   tracking for their kid" path in this phase.
2. **Four sharing modes**, matching the roadmap's enum: `off`,
   `temporary`, `always`, `while_using_app`.
   - `while_using_app` reports location only while the app is
     foregrounded on the member's device — no background-location OS
     permission is requested for this mode.
   - `always` requires the OS background-location permission and must
     get its own explicit, separate in-app explanation before the
     system prompt — never bundled into a generic "allow location"
     ask.
   - `temporary` is time-boxed: the setting carries an `expires_at`,
     and once it passes, the server treats it exactly like `off`
     (below) on the next read — no separate expiry job required, same
     "computed at read time" pattern used for expired meal requests and
     family notes elsewhere in this codebase.
3. **Store last-known location only, not a history.** A single
   `member_locations` row per member is upserted on each update
   (`member_id` unique). There is no time-series/trail table in this
   phase — satisfies the roadmap's explicit "do not store high-frequency
   GPS history without a concrete feature requirement."
4. **Coarse precision.** Stored latitude/longitude are rounded to 3
   decimal places (~111m) before being persisted, not raw GPS precision.
5. **Turning sharing off deletes the stored location row**, not just
   hides it. Withdrawing consent leaves nothing stored, including when
   a `temporary` share expires.
6. **Household-wide visibility, no per-recipient lists.** A member who
   is sharing is visible to every other member of every household they
   belong to — the same "whole household sees it" model already used
   for every other shared resource in this app (calendar, meal plan,
   grocery list). There is no "share with these specific people"
   selector in this phase; that would be a distinct, later feature, not
   an extension of this table.
7. **Arrival/departure notifications, saved places, and geofencing are
   out of scope for this phase.** They require continuous geofence
   monitoring, which is a materially different (and more invasive)
   background-processing and battery posture than "report current
   location on request/interval while sharing is on." Defer until a
   concrete need justifies that additional infrastructure and privacy
   surface, per ADR-002's "don't add infrastructure ahead of need" rule.
   `saved_places`/`geofences` tables are not created in this phase.

### Rationale

Location is the most sensitive data category this app handles —
precise, continuously-updated whereabouts of named family members,
potentially including minors. The roadmap already states the guiding
principles (opt-in, no precise location to the unauthorized, don't
collect when not needed); this ADR makes them concrete enough to build
against:

- Self-service-only sharing closes the most serious misuse path for a
  family app specifically: a household member (even a well-intentioned
  one) remotely enabling tracking on someone else, especially a minor,
  without that person's own action. It is also the only version of
  "opt-in" that is actually opt-in rather than opt-in-by-someone-else.
- A single upserted row (not a trail) means there is no historical
  movement log to leak, subpoena, or be misused later — the data that
  doesn't exist can't be breached. It also sidesteps the storage/cost
  questions a GPS history table would raise.
- Coarse rounding means a compromised row still only places someone
  within roughly a city block, not an exact address pin.
- Deleting (not hiding) the row on sharing-off or expiry means
  "location sharing is off" is verifiably true in the data, not just in
  a UI toggle that still has a server-side row sitting behind it.
- Household-wide visibility matches how every other feature in this
  app already works (no existing precedent for per-recipient sharing
  lists anywhere), keeping the mental model consistent and the
  implementation simple enough to actually audit.

### Consequences

- `member_location_settings` needs at minimum: `member_id` (unique),
  `sharing_mode`, `expires_at` (nullable, `temporary` only). No
  `household_id` — a setting belongs to the member, not a household,
  since the same sharing state applies across every household they're
  in (consistent with point 6 above).
- `member_locations` needs at minimum: `member_id` (unique), `latitude`,
  `longitude`, `updated_at`. Both tables are small and simple by design;
  resist the urge to add convenience columns (e.g., a cached address)
  that would require storing more than the minimum.
- The location-update endpoint must re-check `sharing_mode` (and
  `expires_at`) server-side on every write — a client that still has a
  stale "sharing is on" state must not be able to write a location once
  the member has turned it off or a `temporary` window has lapsed.
- Any future "share with specific people" or "trail history" feature is
  a new decision, not a natural extension of this one, and needs its
  own privacy review given points 3 and 6 above are deliberate, not
  incidental.
- Mobile needs its own explicit, mode-specific permission-request
  copy (especially before requesting background/`always` location) —
  this ADR constrains the API/data model; the equivalent mobile-side
  consent-flow decisions should be confirmed before building the
  Flutter UI.
