# Dockerization Plan

Plan for containerizing the app (Laravel 13 / PHP 8.4 backend + Vue 3 / Vite frontend).
Grounded in current repo facts: SQLite database, database-driver sessions/cache/queue,
Sanctum cookie-based SPA auth (CORS locked to `FRONTEND_URL`), and `VITE_*` variables
baked into the frontend at build time.

## Recommended architecture: single origin, 3 services

Serve the built frontend and the API from **one Nginx container** so browser, cookies,
and CSRF all live on one origin — this eliminates the CORS / `SANCTUM_STATEFUL_DOMAINS` /
`SESSION_DOMAIN` cross-origin problems that typically bite Sanctum SPA setups in Docker.

```
docker-compose.yml
├── app     php:8.4-fpm-alpine  — Laravel via PHP-FPM
├── web     nginx:alpine        — serves frontend dist/ at "/", proxies /api, /sanctum → app:9000
└── queue   same image as app   — php artisan queue:work (QUEUE_CONNECTION=database)
```

The frontend is built with `VITE_API_BASE_URL=/api/v1` and `VITE_BACKEND_URL=` (relative
URLs), so no frontend code changes are needed — only build args.

## Files to create

### 1. `backend/Dockerfile` (multi-stage)

- Stage 1 (`composer:2`): `composer install --no-dev --optimize-autoloader`
  (`--no-scripts`, since artisan isn't available in that stage).
- Stage 2 (`php:8.4-fpm-alpine`): install extensions `pdo_sqlite`, `opcache`, `bcmath`,
  `pcntl`; copy app code + vendor; add a production `php.ini` (opcache on,
  `expose_php=off`); run as `www-data`.

### 2. `backend/docker/entrypoint.sh`

- Create `/var/www/database/database.sqlite` if missing (on the named volume) and fix ownership.
- `php artisan migrate --force`
- `php artisan config:cache && php artisan route:cache`
  (at runtime, not image-build, because env vars are only known then)
- `exec php-fpm` (or `queue:work` when the queue service overrides the command).

### 3. `frontend/Dockerfile` (multi-stage)

- Stage 1 (`node:22-alpine`): `npm ci && npm run build`, with `ARG VITE_API_BASE_URL` /
  `ARG VITE_BACKEND_URL` declared before the build. Build-time only — a runtime env var
  will **not** work with Vite.
- Stage 2 (`nginx:alpine`): copy `dist/` + the custom nginx config below.

### 4. `docker/nginx.conf`

- `location /` → serve frontend static files with SPA fallback
  (`try_files $uri /index.html`) so Vue Router deep links work.
- `location ~ ^/(api|sanctum|up)` → `fastcgi_pass app:9000` with Laravel's
  `public/index.php` front controller.

### 5. Root `docker-compose.yml`

- Named volumes: `sqlite-data` → `backend/database` dir, `storage-data` →
  `backend/storage/app` (+ logs).
- `app` env: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` from `.env` file at repo
  root, `APP_URL=http://localhost:8080`, `FRONTEND_URL=http://localhost:8080`,
  `SANCTUM_STATEFUL_DOMAINS=localhost:8080`.
- `queue` service: same image, command `php artisan queue:work --tries=1`, shares volumes.
- Healthcheck on `app` using Laravel's `/up` endpoint; `web` and `queue` use
  `depends_on` with `condition: service_healthy`.
- Publish `web` on `8080:80`.

### 6. `.dockerignore`

- `backend/`: vendor, node_modules, .env, storage/logs/*, database/*.sqlite, tests
- `frontend/`: node_modules, dist, .env

## Key gotchas already accounted for

- **APP_KEY**: never bake into the image. Generate once (`php artisan key:generate --show`)
  and put it in a root `.env` consumed by compose.
- **SQLite + permissions**: the *directory* containing the `.sqlite` file must be writable
  by `www-data` (SQLite creates journal files next to it) — hence the volume mounts the
  whole `database/` dir and the entrypoint chowns it.
- **Vite env vars are compile-time**: handled via build args; changing the API URL requires
  a rebuild (acceptable — it's a relative path that never changes).
- **Dev-only composer packages** (boost, pail, sail, pint) are excluded via `--no-dev`.

## Implementation order & verification

1. Backend Dockerfile + entrypoint → verify with `docker build` and a lone `docker run`
   hitting `/up`.
2. Frontend Dockerfile + nginx config → verify static serving and SPA fallback.
3. docker-compose wiring everything → `docker compose up`, then verify end-to-end:
   seed with `docker compose exec app php artisan migrate:fresh --seed`, log in at
   `http://localhost:8080` with `test@example.com` / `password`, place an order to
   confirm the DB transaction + queue path work.
4. Add a README section with the compose commands.

## Optional follow-ups (not in the initial pass)

- `docker-compose.dev.yml` override with bind mounts + `vite` dev server for hot reload —
  only worth it if you want to develop *inside* Docker. For local dev, the current
  `artisan serve` + `npm run dev` flow is faster.
- SQLite is fine for a single-host deployment; if the `app` service is ever scaled beyond
  one replica, swap the `sqlite-data` volume for a MySQL/Postgres service — the compose
  file keeps that a small, isolated change.
