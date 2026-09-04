# Tayo API

Laravel REST API for the Tayo family-management mobile app.

The API is a versioned, modular monolith. The Flutter client lives in
`../tayo-mobile` in the parent workspace.

## Current Scope

The foundation currently provides:

- Token authentication with Laravel Sanctum
- User registration, login, logout, and current-user lookup
- Household creation, listing, viewing, and updating
- Household member listing and creation
- Household roles and membership status
- Authorization through Laravel policies

See the [architecture](docs/ARCHITECTURE.md) and [decision record](docs/DECISIONS.md)
for the implementation context and important constraints.

## Requirements

- PHP 8.3+
- Composer
- Docker Desktop, for PostgreSQL
- SQLite PHP extension, for the test suite

## Local Setup

From this directory:

```bash
composer install
copy .env.example .env       # Windows
php artisan key:generate
docker compose up -d
php artisan migrate
php artisan serve --port=8000
```

On macOS or Linux, use `cp .env.example .env` instead of `copy`.

The local API is available at `http://127.0.0.1:8000` and its health endpoint
is `GET /up`.

## API Endpoints

All API routes are prefixed with `/api/v1`.

| Method | Endpoint | Authentication |
| --- | --- | --- |
| `POST` | `/auth/register` | Public |
| `POST` | `/auth/login` | Public |
| `POST` | `/auth/logout` | Sanctum token |
| `GET` | `/auth/me` | Sanctum token |
| `GET` | `/households` | Sanctum token |
| `POST` | `/households` | Sanctum token |
| `GET` | `/households/{household}` | Sanctum token |
| `PUT/PATCH` | `/households/{household}` | Sanctum token |
| `GET` | `/households/{household}/members` | Sanctum token |
| `POST` | `/households/{household}/members` | Sanctum token |

Authenticated requests use:

```text
Authorization: Bearer <token>
Accept: application/json
```

## Testing

The API test suite uses an in-memory SQLite database and does not require the
Docker PostgreSQL container:

```bash
php artisan test
```

## Related Projects

- [Tayo mobile app](../tayo-mobile)
- [Architecture](docs/ARCHITECTURE.md)
- [Architecture decisions](docs/DECISIONS.md)
