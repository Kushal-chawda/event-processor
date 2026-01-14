# Event Processor (Laravel)

## Overview
Small backend to accept encoded events via `POST /api/events`, enqueue them, and persist asynchronously with strict tenant isolation.

## Architecture
Client → API (`POST /api/events`) → Decode & Validate → Database Queue (`jobs`) → Worker (`php artisan queue:work`) → DB (`tenants`, `tenant_sessions`, `events`).

## Key design choices
- **Encoding:** Base64(JSON) — simple and portable.
- **Queue driver:** `database` — built-in, easy to demo locally.
- **Session table name:** `tenant_sessions` (avoid conflict with Laravel `sessions`).
- **Idempotency:** deterministic `event_hash` (sha256 of tenant_id|session_id|event_type|timestamp) + unique DB constraint.
- **Tenant isolation:** all tables include `tenant_id`. Queries always filter by tenant.

## How to run locally
1. Clone repo
2. `cp .env.example .env` and configure DataBase
3. `composer install`
4. `php artisan key:generate`
5. Create SQLite file: `touch database/database.sqlite` (or configure MySQL)
6. `php artisan migrate`
7. Start server: `php artisan serve`
8. Start worker: `php artisan queue:work --tries=3 --sleep=3 --timeout=90`

## Demo steps
- Send encoded payloads to `/api/events` (see examples in `docs/requests.sh`).
- Watch worker logs and verify DB rows with `SELECT` queries.

## Idempotency & duplicates
- `event_hash` unique constraint prevents duplicated inserts.
- Worker performs quick exists() check and catches duplicate-key exceptions as a race-safety mechanism.

## Assumptions & trade-offs
- Using `database` queue for simplicity; Redis or RabbitMQ recommended for production.
- Payload is not signed; adding HMAC would improve authenticity.
- `Tenant` can be created on-the-fly if external ID is unknown — in production, tenant provisioning should be controlled.

## How I verified
- API returns `202 Accepted`.
- Job inserted into `jobs` table.
- Worker picks job, creates/updates `tenant_sessions`, persists `events`.
- Duplicate events ignored.
- Tenant data is isolated.

