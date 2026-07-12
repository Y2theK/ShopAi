# Docker Setup — What Was Implemented & How to Run It

Implementation of `DOCKERIZATION_PLAN.md` (beginner walkthrough in `study-plan.md`).

## What was created

| File | Purpose |
|---|---|
| `backend/Dockerfile` | PHP 8.4-FPM image, multi-stage composer install (no dev packages) |
| `backend/docker/entrypoint.sh` | On start: creates dirs, fixes permissions, waits for MySQL, runs migrations, caches config |
| `backend/docker/php.ini` | Production PHP settings (opcache on) |
| `frontend/Dockerfile` | Node 22 builds the Vue app → tiny Nginx image serves it |
| `frontend/docker/nginx.conf` | Serves the SPA; forwards `/api`, `/sanctum`, `/up` to the backend |
| `docker-compose.yml` | Wires the 5 containers (`app`, `queue`, `web`, `mysql`, `redis`) + volumes |
| `.env` (root) | Holds the generated `APP_KEY` — already filled in, gitignored |
| `.dockerignore` × 2, root `.gitignore`, `.env.example` | Build hygiene |
| `frontend/src/services/api.ts` | One-line fix so relative API URLs work (dev behavior unchanged) |

Already handled during setup:

- The root `.env` **already has a generated APP_KEY**.
- A broken `~/.docker/cli-plugins/docker-buildx` symlink left over from Docker
  Desktop was removed (it shadowed the real buildx at
  `/usr/libexec/docker/cli-plugins/` and broke all builds).

## Run instructions

```bash
cd ~/Desktop/tutorials/ecom

# 1. Build images + start all 3 containers (first build takes a few minutes)
docker compose up --build -d

# 2. Watch until everything is up — wait for "Healthy" on app and web
docker compose ps

# 3. Seed the demo data (first time only)
docker compose exec app php artisan migrate:fresh --seed
```

Then open **http://localhost:8080** and log in with `test@example.com` /
`password`. Test the real flow: log in, view products, place an order.

## Daily use

```bash
docker compose up -d       # start
docker compose logs -f     # watch logs
docker compose down        # stop (data survives in volumes)
docker compose down -v     # stop AND delete the database volume
```

## Troubleshooting

```bash
docker compose logs app      # backend errors (migrations, PHP)
docker compose logs web      # nginx errors
docker compose logs queue    # queue worker
```

Two failure modes worth knowing:

- If `app` never turns healthy, its log shows why — it only reports healthy
  after migrations finish.
- If the page loads but login fails, check `docker compose logs app` for the
  request error — with `LOG_CHANNEL=stderr` all Laravel logs go straight to
  the container output.

## Deviations from the original plan

- **MySQL and Redis replaced the original SQLite/database-cache plan** — the
  app caches products/categories/AI-tool results with cache *tags*, which the
  `database` cache store doesn't support (Redis does), and data now lives in a
  `mysql` container (`mysql-data` volume). `DB_PASSWORD` in the root `.env` is
  optional; it defaults to `ecom-local-pw` for local use.
- **`route:cache` is skipped** — `routes/web.php` has a closure route, which
  can't be serialized. `config:cache` still runs.
- **nginx.conf lives in `frontend/docker/`** instead of a root `docker/`
  folder, so the frontend image can copy it within its own build context.
