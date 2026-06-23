# Order & Payment Management API

A Laravel 13 REST API for managing **orders** and processing **payments**, secured with **JWT** authentication. Payment gateways are pluggable via a **strategy pattern**, so adding a new gateway needs no changes to controllers, services, jobs, or events.

> For a deeper architecture tour and the full build log, see [PROJECT.md](PROJECT.md).

---

## Table of contents
1. [Features](#features-task-coverage)
2. [Tech stack](#tech-stack)
3. [Setup instructions](#setup-instructions)
4. [Authentication](#authentication)
5. [API documentation & Postman collection](#api-documentation--postman-collection)
6. [Endpoints](#endpoints)
7. [Business rules](#business-rules)
8. [Payment gateway extensibility](#payment-gateway-extensibility)
9. [Testing](#testing)
10. [Notes & assumptions](#notes--assumptions)

---

## Features (task coverage)

| Requirement | Where |
|---|---|
| Order: create (with items + server-calculated total), update, delete, list/filter by status | `app/Modules/Order` |
| Payment: process, view per-order & all, statuses & methods | `app/Modules/Payment` |
| Strategy pattern for gateways, configurable via `.env` | `Gateways/`, `config/payments.php` |
| Business rules (confirmed-only payments; no delete with payments) | `OrderService`, `PaymentService` |
| RESTful design, correct status codes, pagination | module `routes.php`, controllers |
| JWT auth + register/login | `app/Modules/Auth` |
| Input validation + meaningful errors | `Http/Requests/*` |
| API docs + Postman collection export | Scribe (`/docs`) |
| Unit + feature tests incl. gateway logic | `tests/` |

---

## Tech stack

- **Laravel 13** (PHP 8.4) · **Pest 4** tests · **PSR-12** via Laravel Pint · **Larastan** (PHPStan level 5)
- **JWT** — `php-open-source-saver/jwt-auth` (`api` guard)
- **spatie/laravel-query-builder** (filtering/sorting) · **spatie/laravel-data** (gateway DTOs)
- **Scribe** (API docs + Postman/OpenAPI export) · **Telescope** (local debugging)
- SQLite by default (easy setup); any Laravel-supported DB works.

---

## Setup instructions

**Prerequisites:** PHP 8.3+, Composer. (This project is developed with [Laravel Herd](https://herd.laravel.com); it serves the app at `http://order-payment-api.test`.)

```bash
# 1. Install dependencies
composer install

# 2. Environment
cp .env.example .env
php artisan key:generate
php artisan jwt:secret        # generates JWT_SECRET (skip if already set)

# 3. Database (SQLite by default) + demo data
#    Create the SQLite file first (migrate won't auto-create it):
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate --seed

# 4. Serve
#    - Herd: open http://order-payment-api.test
#    - or:   php artisan serve   (http://localhost:8000)
```

> **PHP via Herd is available in PowerShell** (`php …`). If `php` isn't found in another shell, use PowerShell.

### Gateway configuration (`.env`)
Gateway credentials live in `.env` and are wired through `config/payments.php`:

```dotenv
PAYMENT_DEFAULT_GATEWAY=credit_card
CREDIT_CARD_API_KEY=cc_test_key
CREDIT_CARD_API_SECRET=cc_test_secret
PAYPAL_CLIENT_ID=pp_test_client
PAYPAL_SECRET=pp_test_secret
```

The gateways are **simulated**: a charge succeeds when credentials are present and deterministically **declines when they are blank** (a real, testable failure path).

### Processing live payments — queue worker
`.env` ships with `QUEUE_CONNECTION=database`, so payment processing is dispatched to a job. Run a worker so live payments resolve from `pending` to their final status:

```bash
php artisan queue:work
```

Alternatively set `QUEUE_CONNECTION=sync` in `.env` to process inline (this is what the test suite uses).

---

## Authentication

All non-auth endpoints require a Bearer token.

```bash
# Register (or use the seeded demo account)
curl -X POST http://order-payment-api.test/api/v1/auth/register \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"name":"Jane","email":"jane@example.com","password":"secret123","password_confirmation":"secret123"}'

# Login -> returns data.access_token
curl -X POST http://order-payment-api.test/api/v1/auth/login \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"email":"jane@example.com","password":"secret123"}'

# Use the token
curl http://order-payment-api.test/api/v1/auth/me \
  -H "Authorization: Bearer <access_token>" -H "Accept: application/json"
```

**Seeded demo account:** `demo@example.com` / `password` (has orders & payments across all statuses).

---

## API documentation & Postman collection

Generated with **Scribe**:

- **Postman collection (import this):** [`docs/api/collection.json`](docs/api/collection.json) — also served live at `/docs.postman`
- **OpenAPI spec:** [`docs/api/openapi.yaml`](docs/api/openapi.yaml) — also served live at `/docs.openapi`
- **Interactive HTML docs:** `http://order-payment-api.test/docs`

Endpoints are grouped by **Authentication / Orders / Payments**, with request params and success + error response examples. Regenerate after endpoint changes:

```bash
php artisan scribe:generate
```

---

## Endpoints

All routes are prefixed with `/api/v1`. 🔒 = requires Bearer token.

**Auth:** `POST /auth/register` · `POST /auth/login` · `GET /auth/me` 🔒 · `POST /auth/logout` 🔒 · `POST /auth/refresh` 🔒

**Orders** 🔒
- `GET /orders` — paginated, user-scoped; `?filter[status]=pending|confirmed|cancelled`, `?sort=`, `?per_page=` (max 100)
- `POST /orders` — total computed server-side from items
- `GET /orders/{order}` · `PUT|PATCH /orders/{order}` · `DELETE /orders/{order}` (204; **409** if it has payments)
- `PATCH /orders/{order}/status` — validated status transition

**Payments** 🔒
- `GET /payments` — all payments across the user's orders
- `GET /payments/{payment}`
- `GET /orders/{order}/payments` — payments for one order
- `POST /orders/{order}/payments` — process a payment (**409** if the order isn't confirmed)

Standard codes: `200/201` success, `204` delete, `401` unauthenticated, `403` not owner, `404` not found, `409` business-rule conflict, `422` validation.

---

## Business rules

- **Server-authoritative totals** — order totals are recomputed from `quantity × unit_price`; any client-supplied `total` is ignored.
- **Status machine** — `pending → confirmed`, `pending → cancelled`, `confirmed → cancelled`; illegal transitions return **422**.
- **Payments require a confirmed order** — otherwise **409**.
- **No deleting an order that has payments** — **409**.
- **Ownership** — users can only access their own orders and the payments under them.

---

## Payment gateway extensibility

Gateways implement a single **Strategy** contract and are resolved from a config registry at runtime. The flow:

```
PaymentService::process(Order, PaymentMethod)
        └─ ProcessPaymentJob ──> PaymentGatewayManager::for($method)
                                     └─ resolves the class from config/payments.php
                                          └─ Gateway::charge(GatewayChargeData): GatewayResponse
```

**The contract** ([`PaymentGatewayInterface`](app/Modules/Payment/Gateways/Contracts/PaymentGatewayInterface.php)):

```php
interface PaymentGatewayInterface
{
    public function charge(GatewayChargeData $data): GatewayResponse;
    public function identifier(): string;
}
```

### How to add a new gateway (e.g. Stripe)

Adding a gateway touches **three places — none of them controllers, services, jobs, or events.**

**1. Create the strategy** — `app/Modules/Payment/Gateways/StripeGateway.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Payment\Gateways;

use App\Modules\Payment\Gateways\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\Gateways\Data\GatewayChargeData;
use App\Modules\Payment\Gateways\Data\GatewayResponse;

class StripeGateway implements PaymentGatewayInterface
{
    /** @param array<string, mixed> $config */
    public function __construct(protected array $config) {}

    public function charge(GatewayChargeData $data): GatewayResponse
    {
        // Call the real SDK here using $this->config credentials.
        if (blank($this->config['secret'] ?? null)) {
            return GatewayResponse::failed('Stripe credentials are not configured.');
        }

        return GatewayResponse::successful('pi_'.uniqid(), [
            'gateway' => $this->identifier(),
            'amount'  => $data->amount,
        ]);
    }

    public function identifier(): string
    {
        return 'stripe';
    }
}
```

**2. Register it** in [`config/payments.php`](config/payments.php) (credentials from `.env`):

```php
'stripe' => [
    'class'  => \App\Modules\Payment\Gateways\StripeGateway::class,
    'key'    => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
],
```

**3. Add the method** to [`PaymentMethod`](app/Modules/Payment/Enums/PaymentMethod.php):

```php
case Stripe = 'stripe';
```

Then add `STRIPE_KEY` / `STRIPE_SECRET` to `.env`. Clients can immediately call
`POST /orders/{order}/payments` with `{"method":"stripe"}`. (Optionally add a unit test mirroring `tests/Unit/Payment/GatewayChargeTest.php`.)

The `PaymentGatewayManager` injects each gateway's own config slice and validates the resolved class implements the interface, throwing `UnsupportedPaymentMethodException` for unknown methods.

---

## Testing

```bash
php vendor/bin/pest                                  # full suite (54 tests, 207 assertions)
php vendor/bin/pint --test                           # PSR-12 style check
php vendor/bin/phpstan analyse --memory-limit=1G     # static analysis (level 5)
```

Tests run against in-memory SQLite with `QUEUE_CONNECTION=sync`. Coverage includes:
- **Auth** — register/login/me/logout/refresh, validation, 401s.
- **Orders** — server-side totals, user-scoped pagination, status filter, ownership 403s, delete-with-payment 409, status transitions, the `OrderStatus` transition matrix.
- **Payments** — pay confirmed order (success), unconfirmed 409, ownership 403, invalid method 422, the **credentials-missing decline** path, plus gateway-manager resolution and gateway `charge()` logic.

---

## Notes & assumptions

- **Order ownership comes from the JWT user**, not the request body — an order belongs to the authenticated user (the "user details" in the task). Cross-user access returns 403.
- **Gateways are simulated** (no real network calls). Charges succeed with configured credentials and decline when they're blank, giving a deterministic, testable failure path. Real providers slot in behind the same interface.
- **Money is stored/returned as decimal strings** (`decimal:2`) to avoid float rounding (e.g. `"30.00"`).
- **Payment IDs are UUIDs**; order IDs are auto-increment integers.
- **Payment processing is queued** (`QUEUE_CONNECTION=database`); run a worker or switch to `sync` for immediate results (see [Setup](#processing-live-payments--queue-worker)).
- **List responses are cached** briefly (30s) per user + query and invalidated on writes.
- Scope is intentionally limited to the task requirements — no refunds/webhooks/real-SDK integration were added beyond the documented extension seam.
