# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Mini SaaS-style e-commerce app. Laravel 12 REST API backend + Vue 3 TypeScript frontend. Users share the same permission level except for an `is_admin` boolean flag on `users`, which gates admin-only features (the admin assistant agent). Stack: Laravel 12, SQLite, Laravel Sanctum, Vue 3, Vite, Axios.

## Commands

### Backend (`cd backend`)
```bash
php artisan serve              # dev server on :8000
php artisan migrate:fresh --seed  # reset DB with seed data
php artisan test               # run all tests
php artisan test --filter=<name>  # run a single test
php artisan test tests/Feature/ExampleTest.php  # run a specific file
```

### Frontend (`cd frontend`)
```bash
npm run dev        # dev server on :5173
npm run build      # type-check + build to dist/
npm run type-check # TypeScript check only
```

## Architecture

### Backend

All API routes are prefixed `/api/v1/`. Auth routes (`/auth/*`) use Sanctum token-based auth. All other routes require `auth:sanctum` middleware.

Controllers use `ApiResponseTrait` (`app/Traits/ApiResponseTrait.php`) for all JSON responses — always call `$this->successResponse(...)` or `$this->errorResponse(...)`, never return raw `response()->json()`.

Order creation (`OrderController::store`) validates stock before writing, then wraps the Order + OrderItem inserts + stock decrement in a single `DB::transaction`.

Two laravel/ai agents live in `app/Ai/Agents/`: `ShoppingAssistantAgent` (`POST /chat`, all users) and `AdminAssistantAgent` (`POST /admin/chat`, admin-only via the `admin` middleware alias → `EnsureUserIsAdmin`). Tools live in `app/Ai/Tools/`. Both use a side-channel context object (`AgentContext` collects products, order info, and the checkout delivery address; `ChartContext` collects chart payloads) that tools populate during the run and the controller returns alongside the agent's text reply. The admin agent is read-only by design — its tools query sales, inventory, customers, and orders but never mutate data.

PII protection (`pii-masking.md` has the full design): chat controllers mask structured PII (`app/Ai/PiiMasker` — email, Myanmar NRC, MM phone, SSN, passport, long digit runs) in the user message **before** `prompt()`, so provider payloads, stored transcripts, and history replays are all sanitized — inbound masking must stay in the controllers because `RememberConversation` runs outside agent middleware and stores the raw prompt. `PiiLeakCanary` middleware on both agents is advisory-only: it re-masks inbound as defense-in-depth and logs pattern names (never values) if a reply contains raw PII. Tool results must never interpolate street addresses or unmasked phones (`MasksPii` trait, `AgentContext` side channel); customer emails in admin tools are masked via `maskEmail()`.

Prompt injection defense (`prompt-injection-hardening.md` has the full design) is containment-first: detection (`PromptInjectionDetector`) is advisory-only and never blocks a single message. `TextNormalizer` (NFKC + zero-width stripping) runs inside the detector and `PiiMasker` to defeat unicode evasion. The controllers add a behavioral throttle (3 flagged messages / 10 min → 429 via `RateLimiter`), `PromptInjectionCanary` middleware covers non-controller call sites, and `FlagsSuspiciousToolData` flags injection markers in tool results that embed customer-controlled text (indirect injection).

### Frontend

`src/services/auth.ts` — module-level reactive singleton managing auth state. The `useAuth()` composable is used everywhere; do not create additional auth state. The `bootstrap()` method is idempotent (deduplicated with a promise) and is called in the router guard on every navigation.

`src/services/api.ts` — preconfigured Axios instance with `withCredentials: true` and `withXSRFToken: true` for Sanctum CSRF handling. Always import this `api` instance, not raw `axios`, for authenticated requests.

Router (`src/router/index.ts`) uses `meta.requiresAuth`, `meta.guestOnly`, and `meta.adminOnly` guards; all are enforced via `router.beforeEach`. `/admin` (admin-only) hosts the admin assistant chat with Chart.js visualizations (`ChartCard.vue` renders chart payloads from `ChartContext`).

### Auth Flow
1. Frontend fetches `/sanctum/csrf-cookie` (via `ensureCsrfCookie`) before any login or bootstrap.
2. Login POSTs to `/api/v1/auth/login`; response payload is parsed by `extractUser()` which handles the nested `data.data` envelope from `ApiResponseTrait`.
3. Subsequent requests carry the session cookie automatically.

## Database

SQLite file at `backend/database/database.sqlite`. Schema: `users`, `products`, `orders`, `order_items`. Seed data is in `DatabaseSeeder` + `ProductSeeder`.

## Environment

- Backend: copy `backend/.env.example` → `backend/.env`, run `php artisan key:generate`
- Frontend: copy `frontend/.env.example` → `frontend/.env`; set `VITE_API_BASE_URL` and `VITE_BACKEND_URL`
- Demo login: `test@example.com` / `password`
- Demo admin login: `admin@example.com` / `password`
