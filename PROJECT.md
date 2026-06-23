# Order & Payment API — Architecture

A modular Laravel 13 REST API for managing **orders** and processing **payments**, secured with JWT. Built as a "modular monolith": each domain (Auth / Order / Payment) is a self-contained slice under `app/Modules`, sharing a small `app/Support` layer. This document is the architecture reference; see [README.md](README.md) for setup and the API guide.

- **API prefix:** `/api/v1` · **Base URL (Herd):** `http://order-payment-api.test`
- **Health:** `GET /api/v1/health` → `{"data":{"status":"ok"}}`

---

## Table of contents
1. [Tech stack](#tech-stack)
2. [Architecture](#architecture)
3. [Directory layout](#directory-layout)
4. [API reference](#api-reference)
5. [Domain rules](#domain-rules)
6. [Conventions](#conventions)
7. [Running locally](#running-locally)
8. [Testing & quality](#testing--quality)
9. [Changelog](#changelog)

---

## Tech stack

| Concern | Choice |
|---|---|
| Framework | Laravel 13 (PHP 8.4) |
| Auth | JWT — `php-open-source-saver/jwt-auth` (`api` guard) |
| Query/filtering | `spatie/laravel-query-builder` v7 |
| DTOs | `spatie/laravel-data` |
| API docs | `knuckleswtf/scribe` |
| Debugging | `laravel/telescope` (local only, at `/telescope`) |
| Tests | Pest 4 |
| Static analysis | Larastan / PHPStan level 5 |
| Formatting | Laravel Pint (PSR-12) |
| CI | GitHub Actions (`composer ci`) |

---

## Architecture

The app separates **HTTP concerns** from **domain logic**, and **writes** from **reads**:

- **HTTP surface** (`app/Modules/*/Http`): thin Controllers, FormRequests (validation), API Resources (serialization), and each module's `routes.php`.
- **Domain (write side)** — `app/Modules/*/Actions`: single-responsibility **Action** classes (`CreateOrderAction`, `ChangeOrderStatusAction`, `ProcessPaymentAction`, `RefundPaymentAction`, `SettlePaymentAction`, …), each exposing one `execute()`. Controllers inject exactly the action they need.
- **Domain (read side)** — `app/Modules/Order/Queries/ListOrdersQuery`: the read model, kept apart from the write actions (CQRS-lite).
- **Gateways (Strategy)** — `app/Modules/Payment/Gateways`: a `PaymentGatewayInterface` with `CreditCardGateway`/`PaypalGateway`, resolved from `config/payments.php` by `PaymentGatewayManager`. Gateways speak in immutable DTOs (`GatewayChargeData`, `GatewayRefundData`, `GatewayResponse`), never Eloquent.
- **Shared layer** — `app/Support`: base `ApiController`, the `ApiResponse` envelope/`problem()` builder, the RFC 7807 exception handler, the `Money` value object + `MoneyCast`, and the request-id middleware. This layer never depends on a feature module (enforced by an arch test).

**Request flow:**
```
routes/api.php (prefix v1, api middleware: request-id + throttle:api)
  └─ app/Modules/<Module>/routes.php   (auth:api where applicable)
       └─ Controller (extends ApiController)  — thin
            ├─ FormRequest        → validation (422 problem+json on failure)
            ├─ $this->authorize() → OrderPolicy ownership checks (403)
            ├─ Action / Query     → domain logic + persistence
            │    └─ may throw a DomainException → RFC 7807 problem+json
            └─ API Resource       → success JSON (wrapped in "data")
```

**Async payment flow:** `ProcessPaymentAction` creates a `pending` Payment and dispatches `ProcessPaymentJob`. The job charges the gateway (outside the DB transaction), persists the outcome under a row lock, and fires `PaymentProcessed`; the `UpdateOrderAfterPayment` listener advances the order to `paid` on success. Real gateways settle later via a signed webhook (`POST /payments/webhook/{gateway}` → `SettlePaymentAction`).

Each module self-registers via a `ServiceProvider` (`bootstrap/providers.php`).

---

## Directory layout

```
app/
├─ Models/User.php                         # JWT subject; ->orders()
├─ Support/                                # shared, module-agnostic
│  ├─ Http/Controllers/ApiController.php       # base controller (authorize + validate)
│  ├─ Http/Middleware/AssignRequestId.php      # X-Request-Id + Context propagation
│  ├─ Http/Responses/ApiResponse.php           # success envelope + problem() (RFC 7807)
│  ├─ Casts/MoneyCast.php                       # decimal column <-> Money
│  ├─ ValueObjects/Money.php                    # integer minor units
│  └─ Exceptions/{ApiExceptionHandler,DomainException}.php
└─ Modules/
   ├─ Auth/      Actions/RegisterUserAction · Http/{Controllers,Requests,Resources} · routes.php
   ├─ Order/
   │  ├─ Actions/{CreateOrder,UpdateOrder,ChangeOrderStatus,DeleteOrder,SyncOrderItems}Action.php
   │  ├─ Queries/ListOrdersQuery.php
   │  ├─ Http/{Controllers,Requests,Resources}/
   │  ├─ Models/{Order,OrderItem}.php · Enums/OrderStatus.php
   │  ├─ Policies/OrderPolicy.php · Observers/OrderObserver.php · Support/OrderCache.php
   │  ├─ Exceptions/{OrderHasPayments,InvalidOrderStatusTransition}Exception.php
   │  └─ routes.php
   └─ Payment/
      ├─ Actions/{ProcessPayment,RefundPayment,SettlePayment}Action.php
      ├─ Http/{Controllers,Requests,Resources,Middleware}/   # incl. VerifyGatewaySignature
      ├─ Models/Payment.php (UUID pk) · Enums/{PaymentStatus,PaymentMethod}.php
      ├─ Gateways/  Contracts/ · Concerns/VerifiesWebhookSignature · Data/ · CreditCard/Paypal + Manager
      ├─ Jobs/ProcessPaymentJob.php · Events/{PaymentProcessed,PaymentRefunded}.php
      ├─ Listeners/UpdateOrderAfterPayment.php · Exceptions/ · routes.php

database/  factories/{Order,OrderItem,Payment}Factory · migrations · seeders/DatabaseSeeder
tests/     Feature/{Auth,Order,Payment} · Unit/{Order,Payment,Support} · Arch/ArchTest
docs/api/  collection.json (Postman) · openapi.yaml
```

---

## API reference

All routes are prefixed with `/api/v1`. 🔒 = requires `Authorization: Bearer <token>`.

### Auth
| Method | Path | Notes |
|---|---|---|
| POST | `/auth/register` | `throttle:auth` (10/min). Returns user + JWT |
| POST | `/auth/login` | `throttle:auth`. 401 (`invalid_credentials`) on bad creds |
| GET | `/auth/me` 🔒 | Current user |
| POST | `/auth/logout` 🔒 | Blacklists the token |
| POST | `/auth/refresh` 🔒 | Returns a fresh token |

### Orders 🔒
| Method | Path | Notes |
|---|---|---|
| GET | `/orders` | Paginated, user-scoped. `?filter[status]=`, `?sort=`, `?per_page=` (max 100) |
| POST | `/orders` | Total computed server-side |
| GET / PUT / PATCH / DELETE | `/orders/{order}` | Owner only; DELETE 204, **409** if payments exist |
| PATCH | `/orders/{order}/status` | Confirm/cancel only (`paid` is system-driven) |

### Payments 🔒 (+ public webhook)
| Method | Path | Notes |
|---|---|---|
| GET | `/payments` | All payments across the user's orders |
| GET | `/payments/{payment}` | Owner only |
| GET | `/orders/{order}/payments` | Payments for one order |
| POST | `/orders/{order}/payments` | Process payment; **409** if not confirmed. Honours `Idempotency-Key` |
| POST | `/payments/{payment}/refund` | Full/partial refund; **409** not-refundable, **422** over-refund |
| POST | `/payments/webhook/{gateway}` | **Public**; HMAC `X-Signature` verified |

---

## Domain rules

- **Server-authoritative totals** — recomputed from line items via the `Money` value object (integer minor units); client `total` ignored.
- **Order status machine** (`OrderStatus`): `pending → confirmed`, `pending → cancelled`, `confirmed → cancelled`, `confirmed → paid`. `paid` is reached **only** through the payment flow; the public status endpoint accepts only `confirmed`/`cancelled`. Illegal transitions → **422**.
- **Payments require a confirmed order** → **409** otherwise.
- **Idempotent charge** — at most one active payment per order; a repeated request (with or without an `Idempotency-Key`) replays the original rather than charging again. The job is `ShouldBeUnique` and only charges while the payment is still `pending`.
- **Delete guard** — an order with any payment cannot be deleted → **409**.
- **Refunds** — only `successful`/`partially_refunded` payments are refundable; partial refunds accumulate (`refunded_amount`) and may not exceed the captured amount (**422**); a gateway decline → **502**.
- **Ownership** — users only access their own orders and the payments under them (`OrderPolicy`).
- **List caching** — order listings cached per user + query fingerprint (30s), invalidated on writes via `OrderObserver` bumping an atomic version counter.

---

## Conventions

- **Success envelope** (`ApiResponse`): payloads expose a `data` key (API Resources wrap automatically).
- **Error envelope:** RFC 7807 `application/problem+json` (`type`, `title`, `status`, `detail`, `code`, optional `errors`). `DomainException` subclasses expose a stable `errorCode()`.
- **Validation** lives in FormRequest classes; failures return **422** with field errors under `errors`.
- **Money** is never a float: the `Money` VO does integer-minor-unit math; `MoneyCast` maps it to/from `decimal` columns.
- **Every PHP file** declares `strict_types`, follows PSR-12, and is fully typed (enforced by Pint + arch tests).
- **API docs:** controllers carry Scribe annotations (`@group`, `@bodyParam`, `@response`, `@authenticated`, `@header`).

---

## Running locally

See [README — Setup](README.md#setup-instructions) for the full walkthrough. Quick reference:

```bash
composer install && cp .env.example .env
php artisan key:generate && php artisan jwt:secret
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate --seed
php artisan queue:work          # required to resolve queued payments
```

> PHP is provided by Herd and is on the PATH in **PowerShell**. Demo login: `demo@example.com` / `password`.

---

## Testing & quality

Current status: **all green.**

| Check | Command | Result |
|---|---|---|
| Tests | `php vendor/bin/pest` | **106 passed, 400 assertions** |
| Static analysis | `php vendor/bin/phpstan analyse --memory-limit=1G` | **0 errors (level 5)** |
| Formatting | `php vendor/bin/pint --test` | **clean** |
| All of the above | `composer ci` | green (also run in GitHub Actions) |

Tests use in-memory SQLite with `QUEUE_CONNECTION=sync`; Feature tests use `RefreshDatabase`. Coverage spans happy paths, validation (422), auth (401), authorization (403), idempotency, refunds, webhook signature/isolation, the async (`Queue::fake`) contract, exact money math, and module-boundary architecture rules.

---

## Changelog

### Enhancements
- **Action pattern (CQRS-lite):** domain logic moved into single-responsibility action classes; read side split into `ListOrdersQuery`. Controllers use method injection.
- **`Money` value object + `MoneyCast`:** all monetary math in integer minor units; exact parsing/rounding, no float error.
- **Idempotent payments + reliable job:** `Idempotency-Key`, a one-active-payment-per-order guard, and a `ShouldBeUnique` job with retries/backoff and a "still pending?" guard — no double charges.
- **Gateway settlement webhooks:** public, HMAC-verified (`hash_equals`), gateway-scoped, idempotent.
- **Refunds:** first-class money movement — partial/full, over-refund guards, `Refunded`/`PartiallyRefunded` statuses, `PaymentRefunded` event.
- **RFC 7807 problem+json** errors with stable codes; `OrderStatus::Paid` advanced by the payment listener.
- **Operability:** wired `api` rate limiter, `X-Request-Id` correlation propagated to jobs, composite DB indexes, GitHub Actions CI, and Pest `arch()` boundary tests.

### Core
- Order CRUD with server-authoritative totals and a status state machine; ownership policies; user-scoped, cached, filterable listings.
- Payment processing via strategy-pattern gateways configured in `.env`; JWT auth (register/login/me/logout/refresh) with rate limiting.
- Scribe API docs + Postman/OpenAPI export; seeders with a stable demo account.
