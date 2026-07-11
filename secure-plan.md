# Rate Limiting, Security Hardening & Caching — AI-Agent E-commerce App

Implemented 2026-07-11 in two rounds. All measures verified: 48 backend tests passing, frontend type-checks + builds, Redis tags confirmed live, prune job on the daily schedule.

## Context

Before this work the API had **no rate limiting at all** (login included), the chat endpoints hit paid LLM APIs with no per-user budget, chat controllers leaked raw exception messages to clients, `PlaceOrderTool` accepted unbounded quantities from LLM output, nothing was cached, customer PII flowed unmasked to OpenAI, and conversation history accumulated in plaintext forever.

Guiding principle: **prompt-level rules are advice, code-level caps are law.** Assume the model can be talked into anything; make the blast radius small.

## Round 1 — Rate limiting, agent guardrails, caching

### Infrastructure
- Redis via `predis/predis` for cache + rate limiting: `CACHE_STORE=redis`, `REDIS_CLIENT=predis` in `.env`/`.env.example`. Sessions stay on `database`.
- Tests keep `CACHE_STORE=array` (array store supports tags — no Redis needed to run the suite).
- ⚠️ Local dev must use `redis` or `array` for cache — the `database` store throws on `Cache::tags()`.

### Rate limiting (`app/Providers/AppServiceProvider.php` + `routes/api.php`)
| Limiter | Limits | Keyed by |
|---|---|---|
| `login` | 5/min | email + IP (brute-force pair; neither IP-rotation nor victim-lockout works) |
| `api` | 60/min | user id (IP fallback) |
| `chat` | 10/min **+ 50/day** | user id — the daily window is the LLM spend budget |
| `admin-chat` | 20/min + 200/day | user id |

- Multi-window = one limiter returning an array of `Limit`s with `minute:`/`day:` key prefixes.
- Route order: `auth:sanctum` **before** `throttle:api` so the limiter sees `$request->user()`.
- 429s render through the `ApiResponseTrait` envelope via `->withExceptions()` in `bootstrap/app.php`, preserving `Retry-After`; that header is CORS-exposed (`config/cors.php`) so the SPA can show a countdown (`chat.ts` / `adminChat.ts`).

### AI agent guardrails
- `PlaceOrderTool`: max 20 units/item, 10 distinct products, no duplicate lines, $10,000 order total. Enforced in the JSON schema **and** re-validated in `handle()` (providers don't reliably honor schemas). Checks run before the DB transaction; rejections return friendly strings so the agent can explain, never throw.
- `#[MaxSteps(6)] #[MaxTokens(2000)]` on `ShoppingAssistantAgent`; `#[MaxSteps(10)] #[MaxTokens(3000)]` on `AdminAssistantAgent` — hard ceilings on the tool loop and reply size. Token values are tunable; raise if replies truncate.
- Exception leakage fixed in both chat controllers: full exception + user_id to the log, generic message to the client.
- `conversation_id` validated as UUID (server-issued; anything else is garbage input → 422).

### Caching
- `ProductController::index`: `Cache::tags(['products'])`, 60s TTL, key = `md5(search):page:perPage`, `per_page` clamped 1–100. Flushed after order placement in **both** `OrderController::store` and `PlaceOrderTool` (stock must be fresh after purchases).
- Admin analytics tools wrapped by `app/Ai/Concerns/CachesToolResults.php` (5-min TTL, `ai-tools` tag). Subtlety: on a cache hit the tool body never runs, so the trait stores `[result, charts]` and **replays the Chart.js payloads into `ChartContext`** — otherwise charts silently vanish on repeat questions.
- Deliberately uncached: `ProductLookupTool` (arbitrary search terms), `RecentOrdersTool` (freshness-sensitive).

## Round 2 — PII, retention, XSS, injection detection

### PII masking (`app/Traits/MasksEmails.php`)
`TopCustomersTool` and `RecentOrdersTool` emit `Jane Doe (j***@example.com)` — names kept for admin utility, raw emails never reach OpenAI, conversation storage, or the tool-result cache (masking happens inside the compute, before caching).

### Retention (`app/Console/Commands/PruneAgentConversations.php`)
`ai:prune-conversations {--days=30}` deletes conversations inactive past the window — messages deleted explicitly since `agent_conversation_messages` has **no cascading FK**. Scheduled daily in `routes/console.php`.

### XSS safety net (`frontend/src/utils/markdown.ts`)
Assistant replies render via `v-html` through a hand-rolled markdown renderer that escapes entities before emitting tags — safe today, but it was the *only* defense. `renderMarkdown()` output now passes through **DOMPurify** with an allowlist of exactly the emitted tags (`p, br, table, tr, th, td, strong, em, code, span` + `class`), protecting against future renderer regressions.

### Prompt-injection detection (`app/Ai/PromptInjectionDetector.php`)
11 named patterns ("ignore previous instructions", "system prompt", "developer mode", …). Both chat controllers log `Log::warning` with user_id + pattern name + endpoint — **never the message content** (the log must not become a PII sink). Detection is advisory: requests proceed, the model refuses, you gain visibility into probing. Containment stays code-level (read-only admin tools, order caps, budgets).

### Deliberately not added
- **Injection blocking** — regex blocking is easily bypassed and false-positives on legitimate messages; theater.
- **Masking customer names** — would gut the admin assistant's usefulness; emails were the sensitive part.
- **Encrypting conversation content** — requires overriding the vendor `ConversationMessage` model; retention pruning covers the main risk. Revisit if compliance demands it.
- **HTTP security headers / CSP** — explicitly descoped by user decision.

## Test coverage (all in `backend/tests/`)
- `RateLimitTest` — login/chat/admin-chat 429s, envelope shape, per-email key isolation (chat tests spam empty payloads: throttle runs before validation, so no LLM call).
- `PlaceOrderToolTest` — every cap, asserting zero DB writes on rejection.
- `ProductCacheTest` — TTL behavior, tag flush on order, distinct keys per query.
- `AdminToolCachingTest` — cached result identity + chart replay on hit.
- `PiiMaskingTest` — masked output in both tools + helper edge cases.
- `PruneAgentConversationsTest` — stale pruned with messages, fresh retained, `--days` override.
- `Unit/PromptInjectionDetectorTest` — pattern hits + benign-message false-positive checks.

## Verification
```bash
cd backend && php artisan test --compact     # 48 passing, no Redis needed
redis-cli ping                               # PONG required for local dev
php artisan schedule:list                    # ai:prune-conversations daily
cd frontend && npm run type-check && npm run build
```
Manual: 6 bad logins → 429 envelope with Retry-After; spam chat → countdown message; `redis-cli --scan --pattern '*products*'` keys vanish after placing an order; same admin analytics question twice → instant second reply with chart intact; forced agent error → generic 500, full trace in `storage/logs/laravel.log`.
