# PII Masking Pipeline (Hand-Rolled)

## Context

The chat delivery-address flow already keeps form-submitted PII out of the LLM via the `AgentContext` side channel. What remains unprotected is **PII the user types into chat** ("call me at 09123456789", NRC numbers, card numbers): it goes raw to the LLM provider, is stored raw in `agent_conversations`, and is replayed raw on every later turn. We evaluated packages (`promptphp/intercept` — inbound-only, no custom patterns, 2 installs) and decided to hand-roll ~100 lines.

Key architectural finding (verified in vendor source): laravel/ai agent middleware runs **inside** `RememberConversation` (`GeneratesText::gatherMiddlewareFor` builds `[RememberConversation, ...agent middleware]`), so middleware-based inbound masking would still store raw text and leak via history replay (`$agent->messages()` bypasses middleware). **Therefore inbound masking must happen in the controllers, before `prompt()`.** Middleware is used only for the outbound leak canary + defense-in-depth.

## Deliverables

### 1. `backend/app/Ai/PiiMasker.php` (new)

Class with ordered, most-specific-first patterns (constant `PATTERNS`, name ⇒ regex), each replaced by a typed placeholder like `[nrc-redacted]`:

| name | pattern intent |
|---|---|
| `email` | standard email syntax |
| `nrc` | Myanmar NRC, Latin: `1-2 digits / township letters (Naing\|N\|Eh\|E\|Pyu\|P\|C\|T) 6 digits`, case-insensitive, optional spaces |
| `nrc_mm` | Burmese-script NRC: Burmese digits ၀-၉ + Myanmar-block township/type letters, `/u` flag |
| `ssn` | `\d{3}-\d{2}-\d{4}` |
| `mm_phone` | `09…` / `+959…` mobile, 7–9 further digits, optional space/dash separators |
| `passport` | word-bounded `[A-Z]{1,2}\d{6,7}` |
| `digit_run` | 9+ digits with optional space/dash separators (cards, bank accounts, foreign phones) |

API:
- `mask(string $text): string` — replace all matches with `[{name}-redacted]`
- `detect(string $text): array` — matched pattern **names** only (for canary logging; never the matched values)

### 2. Controllers — inbound masking (primary layer)

In `ChatController::chat` and `AdminChatController` (same shape): mask the message before the agent sees it:

```php
$message = (new PiiMasker)->mask($payload['message']);
```

…and pass `$message` to `$agent->prompt()`. This sanitizes: provider payload, `agent_conversations` storage, and all future history replays. `PromptInjectionDetector` keeps running on the raw message (masking could otherwise hide an injection inside a fake "phone number").

### 3. `backend/app/Ai/Middleware/PiiLeakCanary.php` (new)

Agent middleware (constructor takes `PiiMasker`):
- Inbound defense-in-depth: `$prompt = $prompt->revise($this->masker->mask($prompt->prompt))` — idempotent behind controller masking; guards future call sites (jobs, commands) that skip the controllers.
- Outbound canary: `return $next($prompt)->then(...)` — run `detect()` on `$response->text`; on match `Log::warning('Possible PII leak in agent reply', ['agent' => ..., 'patterns' => [...]])`. **Log pattern names only, never matched text** (same advisory philosophy as `PromptInjectionDetector`). No response mutation.

Both `ShoppingAssistantAgent` and `AdminAssistantAgent` implement `Laravel\Ai\Contracts\HasMiddleware`:

```php
public function middleware(): array
{
    return [new PiiLeakCanary(new PiiMasker)];
}
```

### 4. `backend/app/Traits/MasksPii.php` — replaces `MasksEmails`

- Keep `maskEmail()` as-is; add `maskPhone()` (keep last 2 digits: `09••••••78`).
- Class docblock states the field rule: **street-address and full-phone order fields must never be interpolated into tool results** (use the `AgentContext` side channel); city/state/country allowed in aggregate admin contexts only.
- Update `TopCustomersTool` + `RecentOrdersTool` to `use MasksPii`; delete `MasksEmails`.

### 5. Tests

Extend `tests/Feature/PiiMaskingTest.php` (keeps existing email-masking tests, switch the trait anon class to `MasksPii`):

- **Catches**: email, NRC Latin (`12/YaKaNa(Naing)123456`), NRC Burmese script, `09` phone with/without separators, `+959` phone, SSN, passport (`MD123456`), 16-digit card, 14-digit bank account.
- **Must NOT catch** (false-positive suite): prices (`$1,137.96`), quantities, order codes (`ORD-AB12CD34`), delivery dates (`12/08/2026`), short numbers, Burmese product query without NRC, category names.
- **Controller integration**: `Ai::fakeAgent(ShoppingAssistantAgent::class, [...])` (via `InteractsWithFakeAgents`), POST `/api/v1/chat` with a phone number in the message, assert the recorded prompt contains `[mm_phone-redacted]` and not the raw number.
- **Canary unit test**: call `PiiLeakCanary::handle` with a stub `$next` returning a thenable response whose text contains an email → assert `Log::warning` fired (via `Log::spy()`), with pattern names and no raw value.

### 6. Housekeeping

- `vendor/bin/pint --dirty --format agent` after PHP changes; run `php artisan test --compact` (full suite — controllers + both agents touched).
- One-line CLAUDE.md note in the backend architecture section: inbound PII masking lives in the chat controllers; `PiiLeakCanary` middleware is advisory-only.

## Verification

1. `php artisan test --compact` — all green including new PiiMasking tests.
2. Manual smoke (needs `php artisan serve` + `npm run dev` + real model): send "my phone is 09123456789 and NRC 12/YaKaNa(Naing)123456" in the shop chat → agent reply should reference redacted placeholders; check `agent_conversations` row stores `[mm_phone-redacted]`; `storage/logs/laravel.log` shows no PII-leak warnings in normal flows.
