# REST API v1

Base URL: `https://<your-domain>/api/v1`  ·  Format: JSON (UTF-8)  ·  Auth: Laravel Sanctum bearer tokens

Every request should send `Accept: application/json`. All data is automatically limited to the company (tenant) of the token's user; a token can never read another company's records.

## Authentication

### Create a token

`POST /auth/token`

```json
{ "email": "reception@alnoor-auto.om", "password": "…", "device_name": "reception-tablet-1", "code": "123456" }
```

`code` is required only when the user has two-factor authentication enabled.

Response `200`:

```json
{ "token": "12|Jx0…", "abilities": ["read", "write"] }
```

- Staff tokens get `read` + `write`; customer-portal users get `read` only (plus booking their own appointments).
- Tokens expire after 90 days. Every attempt is recorded in the login history.
- Wrong credentials: `422 {"message": "These credentials do not match our records."}`.
- Rate limit: the `login` limiter (5 attempts per minute per email + IP).

Tokens can also be created and revoked from **Profile → API Tokens** in the web interface.

### Use the token

```
Authorization: Bearer 12|Jx0…
Accept: application/json
```

### Revoke the current token

`DELETE /auth/token` → `{"ok": true}`

## Conventions

| Topic | Rule |
|---|---|
| Pagination | `?page=2&per_page=50` (max 100). Responses use Laravel's paginator: `data`, `current_page`, `last_page`, `total`, `links` |
| Money | strings with 3 decimals (OMR), e.g. `"57.750"` |
| Dates | `YYYY-MM-DD`; timestamps ISO-8601 |
| Idempotency | send `Idempotency-Key: <uuid>` on every `POST`. A repeated key returns `409 {"message": "This request was already processed."}` instead of creating a duplicate |
| Permissions | the user's role permissions apply exactly as in the web UI. Missing permission → `403` |
| Validation | `422` with `{"message": …, "errors": {"field": ["…"]}}` |
| Business rule violation | `422` with `{"message": "…"}` (e.g. insufficient stock, invalid status change) |
| Rate limit | `api` limiter: 120 requests per minute per user → `429` |

## Endpoints

### Session

| Method | Path | Description |
|---|---|---|
| GET | `/me` | current user: `id, name, email, company_id, branch_id, customer_id, roles` |

### Customers

| Method | Path | Notes |
|---|---|---|
| GET | `/customers?q=` | search by name or phone |
| GET | `/customers/{id}` | with `vehicles` and current `balance` |
| POST | `/customers` | `write` ability, permission `customers.create` |

```json
POST /customers
{ "name": "Said Al Amri", "phone": "99112233", "type": "individual", "email": "said@example.om", "vat_number": null }
```

Omani numbers are normalised to `+968XXXXXXXX`. Response `201` with the created customer (a code is generated).

### Vehicles

| Method | Path | Notes |
|---|---|---|
| GET | `/vehicles?plate=&vin=` | exact plate or VIN filter |
| GET | `/vehicles/{id}` | with make, model and job cards |
| POST | `/vehicles` | `customer_id`, `plate_number` required; `plate_code`, `vin` (17 chars), `vehicle_make_id`, `vehicle_model_id`, `year`, `odometer` |

### Spare parts & stock

| Method | Path | Notes |
|---|---|---|
| GET | `/parts?q=&make_id=&model_id=&year=&vin=&brand_id=&part_category_id=&in_stock=1` | active parts; `q` searches name, SKU, barcode, OEM / alternative numbers, serial |
| GET | `/parts/{id}` | with brand, category, alternatives, vehicle compatibility and stock per warehouse |
| GET | `/stock?warehouse_id=&part_id=` | stock levels: on hand, reserved, average cost (permission `stock.view`) |

### Job cards

| Method | Path | Notes |
|---|---|---|
| GET | `/job-cards?status=` | latest first |
| GET | `/job-cards/{id}` | with tasks, parts, status history |
| POST | `/job-cards` | permission `reception.create` |
| POST | `/job-cards/{id}/status` | permission `job_cards.change_status`; workflow and business guards apply |

```json
POST /job-cards
{ "customer_id": 12, "vehicle_id": 40, "customer_complaint": "A/C not cooling", "priority": "high", "odometer_in": 81500, "promised_at": "2026-11-26 17:00" }

POST /job-cards/77/status
{ "status": "in_progress", "notes": "Started by bay 3" }
```

Status values: `pending_inspection, under_inspection, awaiting_quotation, awaiting_customer_approval, approved, awaiting_spare_parts, in_progress, on_hold, quality_inspection, ready_for_delivery, delivered, completed, cancelled`.

### Invoices

| Method | Path | Notes |
|---|---|---|
| GET | `/invoices?from=&to=` | issue date range |
| GET | `/invoices/{id}` | with items, payment allocations and `balance_due` |

Customer-portal tokens only ever see the customer's own vehicles, job cards, invoices and appointments.

### Appointments

| Method | Path | Notes |
|---|---|---|
| GET | `/appointments?from=` | upcoming from date (default today) |
| POST | `/appointments` | `starts_at` (future), `duration` (min, default 60), `vehicle_id`, `service_id`, `notes`; staff must send `customer_id` |

Booking checks working hours, holidays, department capacity and daily workshop capacity; a full slot returns `422`.

### Accounting

| Method | Path | Notes |
|---|---|---|
| GET | `/accounting/journal?from=&to=` | journal entries with lines, for external accounting systems (permission `journal_entries.export`) |

### Attendance devices (no user token)

`POST /attendance/punch` with header `X-Device-Token: <token>` (configured in **Integrations → Attendance devices**):

```json
{ "employee_code": "EMP-0003", "timestamp": "2026-11-25T07:58:00+04:00", "type": "in" }
```

Response `{"ok": true, "attendance_id": 812}`. Unknown token → `401`.

## Webhooks (outgoing)

Configure in **Administration → Webhooks**: URL, events, signing secret. Each delivery is a `POST` with JSON:

```json
{ "event": "invoice.posted", "data": { "id": 551, "number": "INV-2026-000551", "total": "87.675", "customer_id": 12 }, "sent_at": "2026-11-25T10:15:00+04:00" }
```

Headers: `X-Event`, `X-Signature: sha256=<HMAC-SHA256(raw body, secret)>`, `User-Agent: GarageERP-Webhooks/1.0`. Verify the signature before trusting the payload. A non-2xx response is retried by the queue up to 5 times (after 10 s, 1 min, 5 min, 15 min, 1 h); each attempt and the last HTTP status are stored on the webhook.

Events: `invoice.posted`, `payment.received`, `job_card.status_changed`, or `*` for all.

## Example (curl)

```bash
TOKEN=$(curl -s -X POST https://erp.example.om/api/v1/auth/token \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"reception@alnoor-auto.om","password":"Demo@12345","device_name":"cli"}' | jq -r .token)

curl -s https://erp.example.om/api/v1/job-cards?status=in_progress \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'
```
