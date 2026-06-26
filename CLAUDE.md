# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Mini SaaS-style e-commerce app. Laravel 12 REST API backend + Vue 3 TypeScript frontend. All users share the same permission level (no roles). Stack: Laravel 12, SQLite, Laravel Sanctum, Vue 3, Vite, Axios.

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

### Frontend

`src/services/auth.ts` — module-level reactive singleton managing auth state. The `useAuth()` composable is used everywhere; do not create additional auth state. The `bootstrap()` method is idempotent (deduplicated with a promise) and is called in the router guard on every navigation.

`src/services/api.ts` — preconfigured Axios instance with `withCredentials: true` and `withXSRFToken: true` for Sanctum CSRF handling. Always import this `api` instance, not raw `axios`, for authenticated requests.

Router (`src/router/index.ts`) uses `meta.requiresAuth` and `meta.guestOnly` guards; both are enforced via `router.beforeEach`.

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
