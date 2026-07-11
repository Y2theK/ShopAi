Rate Limiting, Security Hardening & Caching for AI-Agent E-commerce App

Context

The API currently has no rate limiting at all (even /auth/login is unthrottled), the chat endpoints hit paid LLM APIs with no per-user budget, chat controllers leak raw exception messages to clients, PlaceOrderTool accepts unbounded quantities/item counts from LLM output, and nothing is cached (product listing and admin analytics aggregates hit SQLite on every call). Goal: production-grade guardrails appropriate for an AI-agent-powered e-commerce API.

User decisions: Redis for cache + rate limiting · security scope = AI agent guardrails + auth hardening (explicitly NOT HTTP security headers) · cache scope = products + admin analytics.

Verified facts: framework is Laravel 13.x; phpunit.xml sets CACHE_STORE=array (array store supports tags, so tests need no Redis); phpredis extension not installed → use predis; laras]/#[MaxTokens] attributes; named rate limiters mayreturn an array of Limits (multi-window in one limiter); IntegerType/ArrayType support min()/max(); config/cors.php has empty
exposed_headers so the frontend can't read Retry-Afe-line change.

Phase 1 — Redis via predis

1. cd backend && composer require predis/predis
2. backend/.env + backend/.env.example: CACHE_STORE=redis, REDIS_CLIENT=predis (host/port scaffolding already present). Sessions stay
on database (out of scope).
3. No config file changes needed — config/cache.php already defines the redis store; the rate limiter uses the default cache store.
4. Tests keep CACHE_STORE=array (tags work).

Phase 2 — Rate limiting

2a. Limiter definitions in backend/app/Providers/Ap

RateLimiter::for('login', fn (Request $r) =>
    Limit::perMinute(5)->by(Str::lower((string) $r->input('email')).'|'.$r->ip()));

RateLimiter::for('api', fn (Request $r) =>
    Limit::perMinute(60)->by($r->user()?->id ?? $r-

RateLimiter::for('chat', function (Request $r) {
    $key = $r->user()?->id ?? $r->ip();
    return [
        Limit::perMinute(10)->by('minute:'.$key),
        Limit::perDay(50)->by('day:'.$key),      //
    ];
});

RateLimiter::for('admin-chat', function (Request $r
    $key = $r->user()?->id ?? $r->ip();
    return [Limit::perMinute(20)->by('minute:'.$keyy:'.$key)];
});

2b. Routes (backend/routes/api.php): throttle:login on POST /login; throttle:api added alongside auth:sanctum on the main group AND the
/auth/me+/auth/logout group (auth before throttle s throttle:chat on /chat; throttle:admin-chat on/admin/chat (stacks with throttle:api — intended).

2c. Consistent 429 envelope — in backend/bootstrap/app.php ->withExceptions(), render ThrottleRequestsException for api/* requests as
the ApiResponseTrait-shaped JSON (code: 429, succesafter seconds, time), passing $e->getHeaders() throughso Retry-After/X-RateLimit-* are preserved.

2d. backend/config/cors.php: 'exposed_headers' => ['Retry-After'] — required for the frontend countdown (functional need, not the
deselected headers scope).

Phase 3 — AI agent guardrails

3a. backend/app/Ai/Tools/PlaceOrderTool.php — const20, MAX_DISTINCT_ITEMS = 10, MAX_ORDER_TOTAL =10_000.0. Enforce in the JSON schema (->min(1)->max(...) on quantity int and items array) AND in handle() (defense in depth — providers
don't always honor schemas): reject >10 items, duple 1–20 (check before the stock check atPlaceOrderTool.php:42), total > cap before DB::transaction (line 49). Rejections return friendly strings (the tool's existing error
style), never throw.

3b. Agent caps — add #[MaxSteps(6)] #[MaxTokens(200, #[MaxSteps(10)] #[MaxTokens(3000)] toAdminAssistantAgent (Laravel\Ai\Attributes\*). Token numbers are conservative guesses — raise if replies truncate.

3c. Fix exception leakage in ChatController.php and AdminChatController.php catch blocks: Log::error(...) with user_id + exception,
return generic errorResponse('The assistant is unav again shortly.', 500).

3d. Tighten conversation_id validation to ['nullabl chat controllers (IDs are server-issued UUIDs;malformed → 422 instead of silently starting a new conversation).

Phase 4 — Caching

4a. ProductController::index — Cache::tags(['products'])->remember(...) keyed by md5(search):page:perPage, TTL 60s; clamp per_page to
1–100 (also bounds key cardinality). Cache the reso::collection($products)->resolve() + meta), notresource objects. Rationale: ProductResource exposes stock, which changes on every order → tag-flush for immediacy, TTL bounds
staleness from out-of-band edits.

Invalidation: Cache::tags(['products'])->flush() afrderController::store and PlaceOrderTool::handle.

4b. New trait backend/app/Ai/Concerns/CachesToolRes, Closure $compute, int $ttl = 300, ?ChartContext$context = null): string using Cache::tags(['ai-tools']) with manual get/put. Critical subtlety: on a cache hit the tool body never
runs, so ChartContext::addChart() never fires and tx: on miss, diff $context->getCharts() countbefore/after compute, store ['result' => ..., 'charts' => ...]; on hit, replay stored charts into the context via addChart().

4c. Apply to admin analytics tools — wrap each handle() body: extract to private compute(), call
$this->cached("admin-tools:<name>:<params>", fn () $this->context). Apply to: SalesSummaryTool (key bydays), MonthlySalesTrendTool (months), BestSellingProductsTool, InventorySummaryTool, TopCustomersTool, CustomerSummaryTool,
LowStockProductsTool. Tools without ChartContext paol (arbitrary search terms) and RecentOrdersTool(freshness-sensitive). TTL-only expiry — 5-min-stale analytics is acceptable; admin-only so no per-user key.

Phase 5 — Frontend 429 UX

frontend/src/services/chat.ts + adminChat.ts: in the existing catch, use isAxiosError(err) && err.response?.status === 429 → set
state.error to "You're sending messages too quicklys." (fallback message if header missing/unreadable).Keep existing optimistic-message rollback. No interceptor, no component changes (ChatPopup.vue / AdminAssistantView.vue already render
error).

Phase 6 — Tests (backend/tests/Feature/, follow Admle)

1. RateLimitTest.php — login: 5 bad attempts → 422,try-After; different email same IP still allowed. Chat: 10 empty-payload POSTs → 422 (throttle runs before validation — no LLM fake needed), 11th → 429. Admin chat: 21st → 429.
2. PlaceOrderToolTest.php — direct tool invocation ejected, 11 items rejected, duplicate product_idsrejected, total-over-cap rejected (no Order rows created), valid order succeeds + decrements stock.
3. ProductCacheTest.php — cached list omits newly cacing an order flushes the tag and the next GET seesfresh data; different search/page → distinct entries.
4. AdminToolCachingTest.php — same tool+params twich → identical (cached) string AND second contextreceives replayed chart; different params bypass cache.

Critical files

- backend/app/Providers/AppServiceProvider.php — limiters
- backend/routes/api.php + backend/bootstrap/app.ph render
- backend/app/Ai/Tools/PlaceOrderTool.php — caps + cache flush
- backend/app/Ai/Agents/{ShoppingAssistantAgent,Admeps/MaxTokens
- backend/app/Http/Controllers/Api/{ChatController,AdminChatController}.php — error leak fix, uuid rule
- backend/app/Http/Controllers/Api/ProductControlle cache + flush
- backend/app/Ai/Concerns/CachesToolResults.php (new) + 7 admin tools
- frontend/src/services/{chat,adminChat}.ts — 429 U
- backend/.env, backend/.env.example, backend/config/cors.php

Conventions (backend/CLAUDE.md)

Run vendor/bin/pint --dirty --format agent after PHP edits; PHPUnit class tests via php artisan make:test --phpunit; run targeted tests
with php artisan test --compact --filter=...; use Bchanges.

Verification

1. cd backend && php artisan test --compact (all nehe, no Redis needed).
2. Start Redis (redis-server or docker) + redis-cli ping → PONG; php artisan config:clear.
3. Manual: 6 rapid bad-credential logins via curl →r; spam the chat UI → rate-limit message withcountdown; redis-cli --scan --pattern '*products*' shows keys after GET /products and gone after placing an order; ask the admin
assistant the same analytics question twice → secon rendered; force an agent error (bad API key) → generic 500 message in API, full exception in storage/logs/laravel.log.

Caveats

- Once Cache::tags() is in the code, local dev must run CACHE_STORE=redis (or array) — the database store throws on tags. .env change
in Phase 1 handles this; flag it in the summary for
- MaxTokens values are guesses; mechanism verified, numbers tunable.
- New dependency predis/predis — approved via the u

Plan approved — starting implementation. First, let meest-practices skill (required by backend/CLAUDE.md) and kick off the predis install in parallel.