# Deployment Plan — Render (free tier) + Neon Postgres

Decided 2026-07-12. Everything runs in one place: a single free Render web
service hosting the whole app (SPA + API in one container), with Neon as the
free non-expiring Postgres and Render Key Value as the Redis cache.
MySQL has been removed from the project entirely — Postgres everywhere.

## Architecture

```
Browser
   │
   ▼
Render Web Service (free) — one container built from the root Dockerfile
   ├─ nginx  → serves the built Vue SPA (/var/www/spa)
   │          proxies /api, /sanctum, /up → php-fpm on 127.0.0.1:9000
   └─ php-fpm → Laravel API
        ├─ Neon Postgres  (DB_URL, free, does not expire)
        └─ Render Key Value (REDIS_URL, free 25MB — cache tags need Redis)
```

- **One origin** for SPA + API → Sanctum cookie auth works with no CORS or
  cross-site cookie configuration.
- **`QUEUE_CONNECTION=sync`** — nothing in the app dispatches queued jobs, so
  no (paid) background worker is needed.
- Local development is unchanged: `docker-compose.yml` runs its own
  `postgres:17-alpine`, Redis, php-fpm, queue worker, and nginx.

## Key files

| File | Purpose |
|---|---|
| `Dockerfile` (repo root) | All-in-one production image: builds the Vue app, installs Laravel with `pdo_pgsql`, adds nginx. Default command `app-web`. |
| `.dockerignore` (repo root) | Mirrors `backend/.dockerignore` + `frontend/.dockerignore`; per-app ignore files do NOT apply when building from the repo root. Without it, host-generated `backend/bootstrap/cache/*.php` (referencing dev-only Laravel Boost) breaks the image. |
| `backend/docker/nginx-fullstack.conf` | nginx vhost for the all-in-one image (SPA + fastcgi proxy). |
| `backend/docker/app-web.sh` | Starts php-fpm + nginx in one container; rewrites the nginx listen port to Render's injected `$PORT`. |
| `backend/docker/entrypoint.sh` | Runs migrations + `config:cache` before `php-fpm`/`app-web`; supports one-time `SEED_ON_BOOT=true` seeding. |
| `backend/bootstrap/app.php` | `trustProxies(at: '*')` so Laravel sees HTTPS behind Render's proxy. |

## Deploy steps

1. **Push** the branch to GitHub (Render builds from the repo).
2. **Neon** (neon.tech): create a free project → copy the connection string
   (`postgresql://...?sslmode=require`).
3. **Render → New → Key Value**: free instance → copy the *Internal Key Value
   URL* (`redis://red-xxxx:6379`).
4. **Render → New → Web Service**: pick the repo.
   - Language: **Docker** · Root Directory: *(empty — repo root)*
   - Docker Command: *(empty — image default `app-web`)*
   - Instance type: **Free** · Health Check Path: **`/up`**
   - Environment variables:

   ```
   APP_KEY=              # cd backend && php artisan key:generate --show
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://<service>.onrender.com
   FRONTEND_URL=https://<service>.onrender.com
   SANCTUM_STATEFUL_DOMAINS=<service>.onrender.com
   DB_CONNECTION=pgsql
   DB_URL=               # Neon connection string
   CACHE_STORE=redis
   REDIS_CLIENT=predis
   REDIS_URL=            # Render Key Value internal URL
   SESSION_DRIVER=database
   QUEUE_CONNECTION=sync
   LOG_CHANNEL=stderr
   SEED_ON_BOOT=true     # FIRST DEPLOY ONLY — remove once live
   OPENROUTER_API_KEY=   # chat agents: OpenRouter is the primary provider
   OPENAI_API_KEY=       # ...and OpenAI is the automatic failover
   ```

   Optional chat provider overrides (defaults shown; the primary model must
   support tool calling — see openrouter.ai/models?supported_parameters=tools):

   ```
   AI_CHAT_PROVIDER=openrouter
   AI_CHAT_MODEL=google/gemma-4-31b-it:free
   AI_CHAT_FALLBACK_PROVIDER=openai
   AI_CHAT_FALLBACK_MODEL=       # empty = OpenAI's default model
   ```

   Failover fires when the primary provider is rate-limited (429), out of
   credits (402), or overloaded (503) — a misconfigured key (401) fails
   loudly instead of silently burning the fallback provider's credits.

5. Deploy → wait for the health check → open the URL → log in
   (`test@example.com` / `password`).
6. **Delete `SEED_ON_BOOT`** so the seeder does not run again
   (Render's free tier has no shell, which is why seeding is env-gated).

## Local development (root `.env`)

`docker-compose.yml` reads only these variables from the root `.env`
(see `.env.example`):

```
APP_KEY=              # required
OPENROUTER_API_KEY=   # required for chat agents (primary provider)
OPENAI_API_KEY=       # required for chat agents (failover provider)
ANTHROPIC_API_KEY=    # optional
DB_PASSWORD=          # optional, defaults to ecom-local-pw
AI_CHAT_*             # optional provider/model overrides, see .env.example
```

After editing `.env`, run `docker compose up -d` (recreate — a plain
`restart` does not re-read environment values).

## Free-tier caveats

- The web service **sleeps after 15 idle minutes**; the next request takes
  ~30–60s to wake it.
- The container disk is **ephemeral** — fine here (sessions in Postgres,
  cache in Redis, no user uploads).
- Render Key Value free tier has **no persistence** — acceptable, it is a
  cache only.
- Free tier has **no shell/SSH** — one-off artisan commands must go through
  a temporary env-gated hook like `SEED_ON_BOOT`.

## Troubleshooting

- **Chat returns 500 / "Failed to send message", log shows provider 401** —
  the provider's API key (`OPENROUTER_API_KEY` for primary, `OPENAI_API_KEY`
  for failover) is missing or invalid in the service environment.
- **Chat replies but never calls tools / returns tool-call errors** — the
  configured OpenRouter model does not support tool calling; set
  `AI_CHAT_MODEL` to one that does.
- **`Class "Laravel\Boost\BoostServiceProvider" not found`** — stale host
  `backend/bootstrap/cache/*.php` leaked into the image; ensure the root
  `.dockerignore` is intact.
- **Healthcheck fails but the app works** — check IPv4 vs IPv6: inside
  Alpine containers `localhost` resolves to `::1` first; use `127.0.0.1`.
