# Tayo API Architecture

## Purpose

Tayo API is the Laravel backend for the Tayo Android and iOS application. It
exposes a JSON REST API and owns authentication, authorization, household data,
and member data.

The API is currently a modular monolith: one Laravel application, one deployable
service, and one relational database. The code follows Laravel conventions in
`app/Models`, `app/Http/Controllers/Api/V1`, `app/Http/Requests`,
`app/Http/Resources`, and `app/Policies`.

## System Context

```text
Flutter Android/iOS client
          |
          | HTTPS / JSON with Bearer token
          v
Laravel Tayo API
          |
          v
PostgreSQL
```

The test suite uses an in-memory SQLite database. PostgreSQL is the development
and production-oriented relational database configured by `.env` and
`docker-compose.yml`.

## Request Flow

1. A client calls a versioned route under `/api/v1`.
2. Public authentication routes validate the request and issue a Laravel Sanctum token.
3. Protected routes authenticate the token with `auth:sanctum`.
4. Form request classes validate input.
5. Controllers coordinate the operation and use transactions for multi-record writes.
6. Policies authorize access to household resources.
7. API resources shape JSON responses consistently under a `data` key.

## API Surface

Routes are defined in `routes/api.php` and currently cover:

- `auth/register`, `auth/login`, `auth/logout`, `auth/me` (`GET`), and
  `auth/me` (`PATCH`, user profile edit)
- Household index, create, show, and update operations
- Household member index, create, and update operations, plus a
  dedicated `POST .../members/{member}/avatar` upload endpoint (kept
  separate from the JSON `PATCH` because PHP's `$_FILES` doesn't
  populate for multipart `PATCH`/`PUT` request bodies)
- Household invitations (`POST .../invitations`, public
  `GET /invitations/{token}` preview, `POST /invitations/{token}/accept`)
- Member activation for placeholder members (`POST
  .../members/{member}/activation-link`, public `GET /activation/{token}`
  preview, `POST /activation/{token}/claim`)
- Family notes (`GET`/`POST`/`DELETE .../households/{household}/notes`) —
  any member can post, author-or-Owner/Adult can delete
- Announcements (`GET`/`POST`/`DELETE
  .../households/{household}/announcements`) — Owner/Adult-only post,
  any member can read; see `ROADMAP.md` → Phase 2 for what's still
  missing (a `GET /api/v1/home` aggregation endpoint)

There is currently no household or member delete endpoint. New capabilities should
be added under the existing `/api/v1` namespace.

## Domain Model

```text
User 1---0..1 Member
Member 1---* HouseholdMembership *---1 Household
```

- `User` represents an authentication account.
- `Member` represents a person managed in a household.
- A member may exist without a user account, allowing adults to manage children or other placeholders.
- `HouseholdMembership` connects members to households and stores role, status, and join time.
- A user can belong to multiple households through their linked member profile.

Household roles are represented by the `HouseholdRole` enum: `owner`, `adult`,
`minor`, and `child`.

## Authentication And Authorization

Laravel Sanctum issues personal access tokens for mobile clients. Clients send
the token as a Bearer token on protected requests.

Authorization is enforced through `HouseholdPolicy` and controller calls to
`authorize`. The policy boundary should remain the source of truth for whether
a user can view or manage a household and its members.

## Persistence

Migrations define the following core tables:

- `users`
- `personal_access_tokens`
- `households`
- `members`
- `household_memberships`

Household and member creation use database transactions so the primary record
and its membership are created together or not at all.

Member avatars are stored on the local `public` disk (`storage/app/public`,
symlinked via `php artisan storage:link`) under `avatars/`, referenced by
`members.avatar_path`. `Member::avatarUrl` returns a relative `/storage/...`
path rather than an absolute `Storage::url()` one, so mobile clients resolve
it against whatever host they're already configured to use instead of the
API's own `APP_URL` (see `DECISIONS.md`). This is a deliberate stand-in for
S3 until Phase 8 (see Infrastructure Introduction Order in `ROADMAP.md`).

## Testing Strategy

- Feature tests verify HTTP behavior, validation, authentication, authorization, and persistence.
- Unit tests cover isolated domain behavior where useful.
- PHPUnit overrides the database with in-memory SQLite for fast, repeatable tests.
- PostgreSQL-backed checks should be run when validating database-specific behavior or migrations.

Run the suite with:

```bash
php artisan test
```

## Operational Boundaries

The API does not currently include realtime updates, push notification delivery,
queues beyond the Laravel defaults, or third-party subscription integrations.
File storage is limited to member avatars on the local `public` disk (see
Persistence above) — there is no S3 or other cloud storage yet. These
boundaries can be introduced behind explicit application boundaries as
features require them; they should not be treated as existing dependencies.
