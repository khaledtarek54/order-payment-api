# Order & Payment API

A modular Laravel 13 REST API for managing **orders** and processing **payments**, secured with JWT auth. Built as a DDD-lite ("modular monolith") application: each domain owns its models, services, HTTP layer, and tests under `app/Modules`, sharing a small `app/Support` layer.

- **Base URL (Herd):** `http://order-payment-api.test`
- **API prefix:** `/api/v1`
- **Health check:** `GET http://order-payment-api.test/api/v1/health` → `{"data":{"status":"ok"}}`

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
9. [Recent changes (build log)](#recent-changes-build-log)
10. [What's next (roadmap)](#whats-next-roadmap)

---

## Tech stack

| Concern | Choice |
|---|---|
| Framework | Laravel 13 (PHP 8.4) |
| Auth | JWT — `php-open-source-saver/jwt-auth` (`api` guard) |
| Query/filtering | `spatie/laravel-query-builder` v7 |
| DTOs | `spatie/laravel-data` |
| API docs | `knuckleswtf/scribe` |
| Debugging | `laravel/telescope` (at `/telescope`) |
| Tests | Pest 4 (`pestphp/pest`) |
| Static analysis | Larastan / PHPStan level 5 |
| Formatting | Laravel Pint (PSR-12) |
| Local env | Laravel Herd |

---

## Architecture

The app is split into a **shared spine** and a **per-module HTTP surface**.

- **Spine** (`app/Support` + the non-HTTP parts of each module): models, enums, services (business logic), payment gateways, policies, domain exceptions, jobs, events, listeners, providers, config. Controllers stay thin and call into this.
- **HTTP surface** (`app/Modules/*/Http`): Controllers, FormRequests (validation), API Resources (serialization), and each module's `routes.php`.

**Request flow:**
```
routes/api.php (prefix v1)
  └─ app/Modules/<Module>/routes.php   (auth:api / throttle middleware)
       └─ Controller (extends App\Support\Http\Controllers\ApiController)
            ├─ FormRequest        → validation (422 on failure)
            ├─ $this->authorize() → OrderPolicy ownership checks (403)
            ├─ Service            → business logic / persistence
            │    └─ may throw a DomainException (rendered by global handler)
            └─ API Resource       → JSON response (wrapped in "data")
```

Each module registers itself through a `ServiceProvider` (`bootstrap/providers.php`); routes are composed in [routes/api.php](routes/api.php) under the `/api/v1` prefix.

---

## Directory layout

```
app/
├─ Models/User.php                      # JWT subject; ->orders()
├─ Support/                             # shared, cross-module
│  ├─ Http/Controllers/ApiController.php    # base controller (authorize + validate)
│  ├─ Http/Responses/ApiResponse.php        # success/message/error envelopes
│  └─ Exceptions/{ApiExceptionHandler,DomainException}.php
└─ Modules/
   ├─ Auth/
   │  ├─ Http/{Controllers,Requests,Resources}/
   │  ├─ Services/AuthService.php
   │  ├─ Providers/AuthServiceProvider.php
   │  └─ routes.php
   ├─ Order/
   │  ├─ Http/{Controllers,Requests,Resources}/
   │  ├─ Models/{Order,OrderItem}.php
   │  ├─ Enums/OrderStatus.php
   │  ├─ Services/OrderService.php
   │  ├─ Policies/OrderPolicy.php          # ownership authorization
   │  ├─ Observers/OrderObserver.php       # cache invalidation
   │  ├─ Support/OrderCache.php
   │  ├─ Exceptions/{OrderHasPaymentsException,InvalidOrderStatusTransitionException}.php
   │  └─ routes.php
   └─ Payment/
      ├─ Http/{Controllers,Requests,Resources}/
      ├─ Models/Payment.php               # UUID primary key
      ├─ Enums/{PaymentStatus,PaymentMethod}.php
      ├─ Services/PaymentService.php
      ├─ Gateways/                        # CreditCard / Paypal + manager
      ├─ Jobs/ProcessPaymentJob.php
      ├─ Events/PaymentProcessed.php
      ├─ Listeners/UpdateOrderAfterPayment.php
      ├─ Exceptions/{OrderNotConfirmed,UnsupportedPaymentMethod}Exception.php
      └─ routes.php

database/
├─ factories/{Order,OrderItem,Payment}Factory.php
└─ migrations/  orders, order_items, payments, telescope_entries

tests/
├─ Feature/{Auth,Order,Payment}/   # HTTP tests (RefreshDatabase)
└─ Unit/{Order,Payment}/           # pure-logic tests
```

---

## API reference

All routes are prefixed with `/api/v1`. 🔒 = requires `Authorization: Bearer <token>`.

### Auth
| Method | Path | Name | Notes |
|---|---|---|---|
| POST | `/auth/register` | `auth.register` | `throttle:auth` (10/min). Returns user + JWT |
| POST | `/auth/login` | `auth.login` | `throttle:auth`. 401 on bad credentials |
| GET  | `/auth/me` 🔒 | `auth.me` | Current user |
| POST | `/auth/logout` 🔒 | `auth.logout` | Invalidates token |
| POST | `/auth/refresh` 🔒 | `auth.refresh` | Returns a fresh token |

### Orders
| Method | Path | Name | Notes |
|---|---|---|---|
| GET | `/orders` 🔒 | `orders.index` | Paginated, user-scoped. `?filter[status]=`, `?sort=`, `?per_page=` (max 100) |
| POST | `/orders` 🔒 | `orders.store` | Total computed server-side |
| GET | `/orders/{order}` 🔒 | `orders.show` | Owner only (403 otherwise) |
| PUT/PATCH | `/orders/{order}` 🔒 | `orders.update` | Owner only |
| DELETE | `/orders/{order}` 🔒 | `orders.destroy` | 204; **409 if payments exist** |
| PATCH | `/orders/{order}/status` 🔒 | `orders.status.update` | Validated status transitions |

### Payments
| Method | Path | Name | Notes |
|---|---|---|---|
| GET | `/payments` 🔒 | `payments.index` | All payments across the user's orders |
| GET | `/payments/{payment}` 🔒 | `payments.show` | Owner only |
| GET | `/orders/{order}/payments` 🔒 | `orders.payments.index` | Payments for one order |
| POST | `/orders/{order}/payments` 🔒 | `orders.payments.store` | Process payment. **409 if order not confirmed** |

### Example: create an order
```bash
curl -X POST http://order-payment-api.test/api/v1/orders \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"notes":"first order","items":[{"product_name":"Widget","quantity":3,"unit_price":10}]}'
# => 201 { "data": { "id":1, "status":"pending", "total":"30.00", ... } }
```

---

## Domain rules

- **Server-authoritative totals.** Order totals are always recomputed from line items (`quantity × unit_price`); any client-supplied `total` is ignored.
- **Order status machine** (`OrderStatus`): `pending → confirmed`, `pending → cancelled`, `confirmed → cancelled`. Backwards/illegal transitions → **422**.
- **Delete guard:** an order with any payment cannot be deleted → **409** (`OrderHasPaymentsException`).
- **Payment precondition:** only **confirmed** orders can be paid → **409** (`OrderNotConfirmedException`).
- **Payment processing:** `PaymentService::process()` creates a `pending` Payment, dispatches `ProcessPaymentJob` (runs inline under `QUEUE_CONNECTION=sync`), charges via the selected gateway, and ends as `successful` or `failed`. Missing gateway credentials → simulated decline (`failed`).
- **Ownership:** users only see/mutate their own orders and the payments nested under them (`OrderPolicy`).
- **List caching:** order listings are cached per user + query fingerprint (30s) and invalidated on writes by `OrderObserver`.

---

## Conventions

- **Response envelope** (`App\Support\Http\Responses\ApiResponse`): success payloads expose a `data` key (API Resources wrap automatically); errors expose `message` (+ optional `errors`).
- **Validation** lives in FormRequest classes; failures auto-return **422** with field errors.
- **Domain exceptions** are never caught in controllers — a global handler maps each `DomainException` to its HTTP status.
- **Every PHP file** starts with `declare(strict_types=1);`, follows PSR-12, and uses typed properties/returns, constructor promotion, and trailing commas.
- **API docs:** controllers carry Scribe annotations (`@group`, `@bodyParam`, `@response`, `@authenticated`).

---

## Running locally

The app runs under **Laravel Herd** at `http://order-payment-api.test`.

First-time setup (if cloning fresh):
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret          # if JWT_SECRET is not set
php artisan migrate
```

> ⚠️ On this machine PHP is provided by Herd and is available in **PowerShell** (`php …`), not in the Bash shell. Run all `php`/`composer`/`vendor/bin` commands from PowerShell.

Useful commands:
```bash
php artisan migrate:fresh --seed # reset the dev DB and load demo data
php artisan route:list --path=api
php artisan scribe:generate      # build API docs
php artisan queue:work           # REQUIRED to process payments (see note)
```

### Demo account & data
`php artisan db:seed` (or `migrate:fresh --seed`) creates a stable login plus assorted data:

- **`demo@example.com` / `password`** — 4 orders (pending, confirmed+paid, confirmed+declined, cancelled) and 2 payments.
- 4 additional random users, each with 1–3 orders and payments on their confirmed orders.

### API docs
Browsable docs are generated to `/docs` → **`http://order-payment-api.test/docs`**. A Postman collection and OpenAPI spec are written to `storage/app/private/scribe/`. Regenerate with `php artisan scribe:generate` after changing endpoints.

### ⚠️ Queue worker required for payments
`.env` uses `QUEUE_CONNECTION=database`, so `POST /orders/{order}/payments` dispatches `ProcessPaymentJob` to the queue and the payment stays **`pending`** until a worker runs it:
```bash
php artisan queue:work
```
(The test suite uses `QUEUE_CONNECTION=sync` so jobs run inline — that's why tests see final statuses immediately. The seeder writes payments in their final state directly, so seeded data needs no worker.) To make live payments resolve synchronously instead, set `QUEUE_CONNECTION=sync` in `.env`.

---

## Testing & quality

Current status: **all green.**

| Check | Command | Result |
|---|---|---|
| Tests | `php vendor/pestphp/pest/bin/pest` | **54 passed, 207 assertions** |
| Static analysis | `php vendor/bin/phpstan analyse --memory-limit=1G` | **0 errors (level 5)** |
| Formatting | `php vendor/bin/pint --test` | **clean** |

Tests run against an in-memory SQLite DB with the queue set to `sync` (see [phpunit.xml](phpunit.xml)). Feature tests use `RefreshDatabase`; global helpers `createUser()` and `actingAsUser()` are defined in [tests/Pest.php](tests/Pest.php). Coverage spans happy paths, validation (422), auth (401), authorization (403), and the domain-rule edge cases above.

---

## Recent changes (build log)

### 2026-06-23 — HTTP surface completed + verified
The project was built in two passes. The **spine** (models, enums, services, gateways, policies, jobs, events, providers, config, factories, Pest setup) existed already. This session added the **entire HTTP surface and test suite**:

- **Auth module:** `AuthController` (register/login/me/logout/refresh), `RegisterRequest`, `LoginRequest`, `UserResource`, routes, `AuthenticationTest`.
- **Order module:** `OrderController` + `OrderStatusController`, `Store`/`Update`/`UpdateStatus` requests, `OrderResource` + `OrderItemResource`, routes, `OrderManagementTest`, `OrderStatusTransitionTest`, `OrderStatusEnumTest`.
- **Payment module:** `PaymentController` (index/indexForOrder/store/show), `ProcessPaymentRequest`, `PaymentResource`, routes, `PaymentProcessingTest`, `PaymentGatewayManagerTest`, `GatewayChargeTest`.

**Bugs found & fixed during verification:**
1. `OrderService::paginate()` passed **arrays** to `allowedFilters()`/`allowedSorts()`, but spatie/laravel-query-builder v7 made them **variadic** — caused a 500 on every order listing. Fixed to spread args and reordered the QueryBuilder-native calls before `where()`.
2. Order/Payment models lacked `@property` annotations for enum-cast attributes, so static analysis treated `$this->status` as a raw `string`. Added explicit `@property` docblocks.

**Tooling added:** project-level [phpstan.neon](phpstan.neon) (Larastan was a dependency but had no config).

**Verified live on Herd:** `register → me → create order` round-trip succeeded, with the order total correctly computed server-side.

### 2026-06-23 — Docs + demo data
- **API docs generated** via Scribe → `/docs` (+ Postman collection & OpenAPI spec under `storage/app/private/scribe/`).
- **`APP_URL` fixed** to `http://order-payment-api.test` so doc examples use the Herd host.
- **`DatabaseSeeder` added** with a stable `demo@example.com` account and orders/payments across all statuses; verified live.
- **Documented the queue-worker requirement** for live payment processing (see [Running locally](#running-locally)).

---

## Status & scope

**The task specification is fully implemented** (Order/Payment CRUD, JWT auth, validation, strategy-pattern gateways configurable via `.env`, pagination, business rules, RESTful design, API docs + Postman export, unit & feature tests, and a README covering setup + gateway extensibility + assumptions).

Scope is intentionally held to the task document — the following are **out of scope** and were deliberately *not* built, but the architecture leaves room for them:

- Refunds / payment webhooks.
- Real payment-provider SDK integration (gateways are simulated behind `PaymentGatewayInterface`).
- CI pipeline, cursor pagination, observability hardening.

> Tip: reset/refresh the dev data anytime with `php artisan migrate:fresh --seed`.
