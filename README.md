# Order & Payment Management API

A Laravel 13 REST API for managing **orders** and processing **payments**, secured with **JWT** authentication. Payment gateways are pluggable via a **strategy pattern**, so adding a new gateway needs no changes to controllers, actions, jobs, or events.

> For a deeper architecture tour and the changelog, see [PROJECT.md](PROJECT.md).

---

## Table of contents
1. [Features](#features-task-coverage)
2. [Engineering highlights](#engineering-highlights)
3. [Tech stack](#tech-stack)
4. [Setup instructions](#setup-instructions)
5. [Authentication](#authentication)
6. [API documentation & Postman collection](#api-documentation--postman-collection)
7. [Endpoints](#endpoints)
8. [Business rules](#business-rules)
9. [Payment gateway extensibility](#payment-gateway-extensibility)
10. [Testing](#testing)
11. [Notes & assumptions](#notes--assumptions)

---

## Features (task coverage)

| Requirement | Where |
|---|---|
| Order: create (items + server-calculated total), update, delete, list/filter by status | `app/Modules/Order` |
| Payment: process, view per-order & all, statuses & methods | `app/Modules/Payment` |
| Strategy pattern for gateways, configurable via `.env` | `Gateways/`, `config/payments.php` |
| Business rules (confirmed-only payments; no delete with payments) | `app/Modules/*/Actions` |
| RESTful design, correct status codes, pagination | module `routes.php`, controllers |
| JWT auth + register/login | `app/Modules/Auth` |
| Input validation + meaningful errors (RFC 7807) | `Http/Requests/*`, `app/Support/Exceptions` |
| API docs + Postman collection export | Scribe (`/docs`, `docs/api/`) |
| Unit + feature tests incl. gateway logic | `tests/` (106 tests) |

---

## Engineering highlights

Beyond the core requirements, the codebase demonstrates:

- **Action pattern (CQRS-lite):** single-responsibility actions (`CreateOrderAction`, `ProcessPaymentAction`, `RefundPaymentAction`, …) with a read-side `ListOrdersQuery`; controllers use method injection to pull exactly the action each endpoint needs.
- **`Money` value object:** all monetary math in integer minor units — exact, no IEEE-754 rounding (the classic `0.1 + 0.2` bug). Serialized as fixed-point decimal strings.
- **Idempotent payments:** an `Idempotency-Key` header, a unique constraint, and a one-active-payment-per-order guard under a row lock; `ProcessPaymentJob` is `ShouldBeUnique`, retried with backoff, and only charges while still pending — no double charges from retries, redeliveries, or double-clicks.
- **Pluggable gateways (Strategy)** with **HMAC-verified settlement webhooks** (gateway-scoped, timing-safe), and **refunds** as first-class money movement (partial/full, over-refund guards).
- **RFC 7807 problem+json** errors with stable, machine-readable `code`s (e.g. `order_not_confirmed`, `refund_exceeds_payment`).
- **Operability:** per-request correlation IDs propagated into queued jobs (the `Context` facade), a wired API rate limiter, and composite indexes matched to the hot queries.
- **Enforced architecture:** Pest `arch()` tests keep gateways free of Eloquent, the Support layer module-agnostic, and the modules out of each other's HTTP layer — plus a GitHub Actions CI gate (`composer ci`).

---

## Tech stack

- **Laravel 13** (PHP 8.4) · **Pest 4** tests · **PSR-12** via Laravel Pint · **Larastan** (PHPStan level 5)
- **JWT** — `php-open-source-saver/jwt-auth` (`api` guard)
- **spatie/laravel-query-builder** (filtering/sorting) · **spatie/laravel-data** (gateway DTOs)
- **Scribe** (API docs + Postman/OpenAPI export) · **Telescope** (local debugging)
- SQLite by default (easy setup); any Laravel-supported DB works.

---

## Setup instructions

**Prerequisites:** PHP 8.3+, Composer. (Developed with [Laravel Herd](https://herd.laravel.com), which serves the app at `http://order-payment-api.test`.)

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

# 5. Process queued payments (see the queue-worker note below)
php artisan queue:work
```

### Gateway configuration (`.env`)
Gateway credentials live in `.env` and are wired through `config/payments.php`:

```dotenv
PAYMENT_DEFAULT_GATEWAY=credit_card
CREDIT_CARD_API_KEY=cc_test_key
CREDIT_CARD_API_SECRET=cc_test_secret
CREDIT_CARD_WEBHOOK_SECRET=cc_test_webhook_secret
PAYPAL_CLIENT_ID=pp_test_client
PAYPAL_SECRET=pp_test_secret
PAYPAL_WEBHOOK_SECRET=pp_test_webhook_secret
```

The gateways are **simulated**: a charge succeeds when credentials are present and deterministically **declines when they are blank** (a real, testable failure path). The `*_WEBHOOK_SECRET` values key the HMAC verification of inbound settlement webhooks.

### Processing live payments — queue worker
`.env` ships with `QUEUE_CONNECTION=database`, so `POST /orders/{order}/payments` enqueues `ProcessPaymentJob` and returns a **`pending`** payment; a worker resolves it to `successful`/`failed` and advances the order to `paid`:

```bash
php artisan queue:work
```

Alternatively set `QUEUE_CONNECTION=sync` in `.env` to process inline (this is what the test suite uses, so the charge resolves within the request).

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

**Seeded demo account:** `demo@example.com` / `password` (has orders & payments across statuses).

---

## API documentation & Postman collection

Generated with **Scribe**:

- **Postman collection (import this):** [`docs/api/collection.json`](docs/api/collection.json) — also served at `/docs.postman`
- **OpenAPI spec:** [`docs/api/openapi.yaml`](docs/api/openapi.yaml) — also served at `/docs.openapi`
- **Interactive HTML docs:** `http://order-payment-api.test/docs`

Endpoints are grouped by **Authentication / Orders / Payments**, with request params and success + error examples. Regenerate after endpoint changes with `php artisan scribe:generate`.

---

## Endpoints

All routes are prefixed with `/api/v1`. 🔒 = requires Bearer token.

**Auth:** `POST /auth/register` · `POST /auth/login` · `GET /auth/me` 🔒 · `POST /auth/logout` 🔒 · `POST /auth/refresh` 🔒

**Orders** 🔒
- `GET /orders` — paginated, user-scoped; `?filter[status]=pending|confirmed|paid|cancelled`, `?sort=`, `?per_page=` (max 100)
- `POST /orders` — total computed server-side from items
- `GET /orders/{order}` · `PUT|PATCH /orders/{order}` · `DELETE /orders/{order}` (204; **409** if it has payments)
- `PATCH /orders/{order}/status` — confirm or cancel (`paid` is reached only through the payment flow)

**Payments** 🔒
- `GET /payments` — all payments across the user's orders
- `GET /payments/{payment}`
- `GET /orders/{order}/payments` — payments for one order
- `POST /orders/{order}/payments` — process a payment (**409** if the order isn't confirmed). Send an `Idempotency-Key` header to make retries safe — a repeated key returns the original payment, never a second charge.
- `POST /payments/{payment}/refund` — full or partial refund (`{"amount": 25.00}`); **409** if not refundable, **422** on over-refund

**Webhooks** (public)
- `POST /payments/webhook/{gateway}` — gateway settlement callback, authenticated by an `X-Signature` HMAC of the raw body (no bearer token)

Standard codes: `200/201` success, `204` delete, `401` unauthenticated, `403` not owner, `404` not found, `409` business-rule conflict, `422` validation. Errors are `application/problem+json` documents.

---

## Business rules

- **Server-authoritative totals** — order totals are recomputed from `quantity × unit_price`; any client-supplied `total` is ignored.
- **Order status machine** — `pending → confirmed`, `pending → cancelled`, `confirmed → cancelled`, and `confirmed → paid` (system-driven by a successful payment only — clients cannot set `paid`). Illegal transitions return **422**.
- **Payments require a confirmed order** — otherwise **409**.
- **One charge per order** — a duplicate/retried payment request returns the existing payment instead of charging twice (idempotent).
- **No deleting an order that has payments** — **409**.
- **Refunds** — only a successful payment is refundable; partial refunds accumulate and may not exceed the captured amount.
- **Ownership** — users can only access their own orders and the payments under them.

---

## Payment gateway extensibility

Gateways implement a single **Strategy** contract and are resolved from a config registry at runtime:

```
ProcessPaymentAction → ProcessPaymentJob → PaymentGatewayManager::for($method)
                                              └─ resolves the class from config/payments.php
                                                   └─ Gateway::charge(GatewayChargeData): GatewayResponse
```

**The contract** ([`PaymentGatewayInterface`](app/Modules/Payment/Gateways/Contracts/PaymentGatewayInterface.php)):

```php
interface PaymentGatewayInterface
{
    public function charge(GatewayChargeData $data): GatewayResponse;
    public function refund(GatewayRefundData $data): GatewayResponse;
    public function identifier(): string;
    public function verifySignature(string $payload, string $signature): bool; // for webhooks
}
```

### How to add a new gateway (e.g. Stripe)

Adding a gateway touches **three places — none of them controllers, actions, jobs, or events.**

**1. Create the strategy** — `app/Modules/Payment/Gateways/StripeGateway.php` (reuse the shared HMAC trait for webhook verification):

```php
<?php

declare(strict_types=1);

namespace App\Modules\Payment\Gateways;

use App\Modules\Payment\Gateways\Concerns\VerifiesWebhookSignature;
use App\Modules\Payment\Gateways\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\Gateways\Data\GatewayChargeData;
use App\Modules\Payment\Gateways\Data\GatewayRefundData;
use App\Modules\Payment\Gateways\Data\GatewayResponse;
use Illuminate\Support\Str;

class StripeGateway implements PaymentGatewayInterface
{
    use VerifiesWebhookSignature; // hash_equals(hash_hmac('sha256', $body, $config['webhook_secret']), $sig)

    /** @param array<string, mixed> $config */
    public function __construct(protected array $config) {}

    public function charge(GatewayChargeData $data): GatewayResponse
    {
        if (blank($this->config['secret'] ?? null)) {
            return GatewayResponse::failed('Stripe credentials are not configured.');
        }

        return GatewayResponse::successful('pi_'.Str::random(24), [
            'gateway' => $this->identifier(),
            'amount' => $data->amount->toDecimalString(),
        ]);
    }

    public function refund(GatewayRefundData $data): GatewayResponse
    {
        return GatewayResponse::successful('re_'.Str::random(24), [
            'gateway' => $this->identifier(),
            'reference' => $data->reference,
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
    'class' => \App\Modules\Payment\Gateways\StripeGateway::class,
    'key' => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
],
```

**3. Add the method** to [`PaymentMethod`](app/Modules/Payment/Enums/PaymentMethod.php):

```php
case Stripe = 'stripe';
```

Add the `STRIPE_*` vars to `.env`. Clients can immediately call `POST /orders/{order}/payments` with `{"method":"stripe"}`. The `PaymentGatewayManager` injects each gateway's own config slice and throws `UnsupportedPaymentMethodException` for unknown methods.

---

## Testing

```bash
composer ci                                          # Pint + PHPStan + Pest (the CI gate)

php vendor/bin/pest                                  # full suite (106 tests, 400 assertions)
php vendor/bin/pint --test                           # PSR-12 style check
php vendor/bin/phpstan analyse --memory-limit=1G     # static analysis (level 5)
```

Tests run against in-memory SQLite with `QUEUE_CONNECTION=sync`. Coverage includes:
- **Auth** — register/login/me/logout/refresh, validation, 401s, token invalidation after logout.
- **Orders** — server-side totals, user-scoped pagination, status filter, ownership 403s, delete-with-payment 409, the status-transition matrix, and rejection of a client-driven `paid`.
- **Payments** — pay/decline, unconfirmed 409, ownership 403, invalid method 422, idempotent replay (with and without a key), the async (`Queue::fake`) contract, and the job's redelivery guard.
- **Refunds** — full/partial, over-refund (including by accumulation), not-refundable, gateway decline, ownership.
- **Webhooks** — valid/invalid signature, byte-exact verification, cross-gateway isolation, unknown gateway/reference, idempotent redelivery.
- **Money** — exact minor-unit math and half-up rounding; gateway charge/refund logic.
- **Architecture** — module-boundary `arch()` rules.

---

## Notes & assumptions

- **Order ownership comes from the JWT user**, not the request body — an order belongs to the authenticated user. Cross-user access returns 403.
- **Gateways are simulated** (no real network calls). Real providers slot in behind `PaymentGatewayInterface`.
- **Money** is modelled by a `Money` value object (integer minor units) and serialized as fixed-point decimal strings (e.g. `"30.00"`); all arithmetic is exact.
- **Payment IDs are UUIDs**; order IDs are auto-increment integers.
- **Payment processing is queued** (`QUEUE_CONNECTION=database`); run a worker or switch to `sync` for immediate results (see [Setup](#processing-live-payments--queue-worker)).
- **Errors are RFC 7807 problem documents** (`application/problem+json`) with a stable `code` clients can branch on.
- **Idempotency:** clients SHOULD send an `Idempotency-Key` on payment creation; even without one, the API will not create a second charge for an order that already has an active payment.
- **List responses are cached** briefly (30s) per user + query and invalidated on writes.
