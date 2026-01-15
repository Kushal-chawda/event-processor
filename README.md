# Event Processor (Laravel)

## Overview

This project is a small backend system built using **Laravel (latest version)**. It accepts **encoded events** via an API, processes them **asynchronously using a queue**, and stores them in a **database** with **strict tenant isolation**.

The API is **fast and non-blocking**. All heavy work happens in a background worker.

---

## High-Level Architecture

```
Client (Postman)
   ↓
API (POST /api/events)
   ↓
Decode & Validate Payload
   ↓
Database Queue (jobs table)
   ↓
Queue Worker (php artisan queue:work)
   ↓
MySQL Database (tenants, tenant_sessions, events)
```

---

## Key Design Choices

* **Database**: MySQL
  Used for easy inspection, relations.

* **Queue Driver**: `database` (default Laravel queue)
  No external services required. Easy to run locally.

* **Encoding**: Base64(JSON)
  Simple, readable, and easy to test using Postman.

* **Session Table Name**: `tenant_sessions`
  Avoids conflict with Laravel’s default `sessions` table.

* **Idempotency**:
  Each event has a unique `event_hash` (SHA-256 of `tenant_id | session_id | event_type | timestamp`).

* **Tenant Isolation**:
  Every table includes `tenant_id`. No data is shared across tenants.

---

Required queue table (run once):

```bash
php artisan queue:table
php artisan migrate
```

---

## Logging (Log Viewer)

This project includes **Laravel Log Viewer** to inspect logs easily. Use the log viewer to see queue processing logs, worker errors, and duplicate event messages.

### Log Viewer URL (local)

```
http://localhost:8000/log-viewer
```

Open this URL in your browser while your app is running to view logs.

---

## How to Run Locally 

1. **Clone the repository**

2. **Copy environment example**

```bash
cp .env.example .env
```

3. **Edit `.env` and set your MySQL credentials** (see section above)

4. **Install dependencies**

```bash
composer install
```

5. **Generate application key**

```bash
php artisan key:generate
```

6. **Run migrations**

```bash
php artisan migrate
```

7. **Start the app server**

```bash
php artisan serve
```

8. **Start the queue worker in another terminal**

```bash
php artisan queue:work --tries=3 --sleep=3 --timeout=90
```

Now your API and worker are running locally.

---

## API Usage (Postman Setup) —

### Endpoint

```
POST /api/events
```

### Headers (use these exact headers)

```
Accept: application/json
Content-Type: application/json
```

### Postman Pre-request Script (how to encode payload)

In Postman, open the request and go to **Pre-request Script**. Paste this exact JavaScript:

```js
const payload = {
  tenant_id: "T1",
  session_id: "S100",
  event_type: "keydown", // options: page_view, click, submit, keydown
  timestamp: "2026-01-01T10:00:00Z"
};

const encodedPayload = btoa(JSON.stringify(payload));

pm.environment.set("encoded_payload", encodedPayload);
```

This script creates an encoded payload and stores it in your Postman environment variable named `encoded_payload`.

### Request Body (raw JSON)

Use this body exactly:

```json
{
  "payload": "{{encoded_payload}}"
}
```

When you send the request, Postman will replace `{{encoded_payload}}` with the Base64 string created in the Pre-request Script.

### Expected Response

```json
{
  "status": "accepted"
}
```

> Note: The API only validates and queues the event. It does not write to the database directly.

---

## Idempotency & Duplicate Handling

* `event_hash` has a unique database constraint.
* The worker checks for the existing hash before inserting.
* If the same event is sent twice, the second is ignored and logged as duplicate.
* The system does not crash on duplicates.

---

## Tenant Isolation

* Every record stores `tenant_id`.
* Sessions are unique per tenant.
* Events for different tenants are stored separately and cannot mix.

---

## How to Verify

1. Start server and worker.
2. Send an event using Postman with the Pre-request Script.
3. Watch the worker terminal — you will see job picked up and processed.
4. Open Log Viewer ([http://localhost:8000/log-viewer](http://localhost:8000/log-viewer)) to see logs.
5. Check your MySQL tables (`tenants`, `tenant_sessions`, `events`) to verify saved data.
6. Send the same event again — check logs to see duplicate ignored and DB unchanged.

---

## Assumptions & Trade-offs

* `database` queue chosen for simplicity. Use Redis for higher throughput in production.
* Payloads are Base64. For production, use HMAC-signed payloads to ensure authenticity.

---



