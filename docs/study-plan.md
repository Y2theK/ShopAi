# Docker Study Plan — Dockerizing This App, Explained Simply

A beginner-friendly walkthrough of the dockerization plan (see `DOCKERIZATION_PLAN.md`
for the technical version).

## First: the basic Docker ideas (3 words you need)

**Image** — a frozen snapshot of everything an app needs to run: the OS files, PHP,
your code, the installed composer packages. Think of it like a `.zip` of a
fully-installed machine. You build it once with a recipe file called a **Dockerfile**.

**Container** — a running copy of an image. Start it, it runs your app; stop it, and
everything inside is thrown away. Containers are disposable on purpose — that's what
makes them reliable ("it works the same everywhere").

**Volume** — because containers are disposable, anything you want to *keep* (like the
SQLite database file) must live in a volume: a folder Docker keeps on your machine,
outside the container, and plugs into it. Delete the container, the volume survives.

And one tool: **docker compose** — a YAML file that says "my app is made of these 3
containers, connected like this." Then `docker compose up` starts everything with one
command.

## What we're building

Right now the app runs as two hand-started processes:

- `php artisan serve` → backend on port 8000
- `npm run dev` → frontend on port 5173

We replace that with 3 containers:

| Container | What it does |
|---|---|
| `app` | Runs PHP and the Laravel code |
| `web` | Nginx web server — the "front door" everyone talks to |
| `queue` | Same Laravel code, but runs `queue:work` in a loop for background jobs |

Key design choice: **the browser only ever talks to `web`**, on one address
(`localhost:8080`). Nginx looks at each request: if the URL starts with `/api` or
`/sanctum`, it forwards it to the `app` container; anything else, it serves the built
Vue files.

Why this matters here: login works with cookies (Sanctum). Browsers are strict about
cookies across different addresses — the current setup needs special config (`CORS`,
`SANCTUM_STATEFUL_DOMAINS`) just because the frontend is on `:5173` and the backend on
`:8000`. Putting both behind one address makes all that fragility disappear. Cookies
just work, like a normal website.

## The steps, in order

**Step 1 — Write the backend recipe (`backend/Dockerfile`).**
Instructions Docker follows to build the backend image: "Start from an image that
already has PHP 8.4. Add the SQLite extension. Copy my code in. Run `composer install`."
The result is a self-contained image — no need to have PHP installed on whatever
machine runs it.

**Step 2 — Write a startup script (`entrypoint.sh`).**
Containers can be started fresh many times, so things that must happen *every start* go
in a small script: make sure the database file exists, run `php artisan migrate`, then
start PHP. This replaces the manual setup steps you'd normally do after cloning the repo.

**Step 3 — Write the frontend recipe (`frontend/Dockerfile`).**
Vue only needs Node.js to *build*. Once `npm run build` produces the `dist/` folder,
it's just static HTML/JS/CSS. So the recipe uses Node in a temporary first stage to
build, then copies only `dist/` into a tiny Nginx image. The final image doesn't
contain Node at all.

One catch: `VITE_API_BASE_URL` gets **baked into the JavaScript at build time** — it's
not read when the app runs. Since everything is on one address now, we set it to just
`/api/v1` (no `http://localhost:8000`), and it never needs to change.

**Step 4 — Write the Nginx config.**
The traffic-directing rule from above: `/api` and `/sanctum` → forward to the `app`
container; everything else → serve the Vue files (with a fallback to `index.html` so
Vue Router URLs like `/products/5` work when refreshed).

**Step 5 — Write `docker-compose.yml`.**
The file that ties it together: "run these 3 containers, connect them on a private
network, attach a volume for the SQLite file, expose port 8080 to my machine." It also
holds the environment settings (`APP_ENV=production`, the `APP_KEY`, etc.) — the
container equivalent of a `.env` file.

**Step 6 — Add `.dockerignore` files.**
Like `.gitignore` but for Docker builds: don't copy `node_modules`, `vendor`, or the
local `.env` into images. Keeps builds fast and keeps secrets out.

## What using it looks like when done

```bash
docker compose up --build          # build images + start all 3 containers
docker compose exec app php artisan migrate:fresh --seed   # seed data (first time)
# → open http://localhost:8080, log in with test@example.com / password
docker compose down                # stop everything
```

That's the whole workflow. No PHP, Node, or Composer needed on the machine — just
Docker. Which is the point: anyone (or any server) can run the exact app with one
command.

## Two gotchas worth understanding now

1. **The `APP_KEY` can't be baked into the image.** It's a secret, and images often get
   shared/pushed to registries. So it lives in a root `.env` file that compose reads and
   hands to the container at start time.
2. **The SQLite file lives in a volume.** If it lived inside the container, every
   `docker compose down` would wipe the data. The volume makes the database survive
   restarts and rebuilds.
