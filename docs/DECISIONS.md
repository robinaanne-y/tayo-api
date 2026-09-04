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
