# AI Chatbot Implementation Plan

## Stack
- **Backend:** Laravel 13 + `laravel/ai` v0.8 + Anthropic (`claude-opus-4-8`)
- **Frontend:** Vue 3 + TypeScript + Tailwind CSS
- **Storage:** Conversation history persisted to SQLite via `RemembersConversations` trait (`agent_conversations` + `agent_conversation_messages` tables — already migrated)

---

## Phase 1 — Backend: Tools

Four tools + one shared context object.

### `app/Ai/AgentContext.php` *(new)*
Shared state bag passed to all tools so the controller can return products the AI looked up.
```php
class AgentContext {
    private array $products = [];
    public function addProduct(array $product): void   // deduplicates by id
    public function getProducts(): array               // returns array_values
}
```

### `app/Ai/Tools/SearchProductsTool.php` *(edit stub)*
- **Description:** "Search for products by name or keyword. Optionally filter by max price."
- **Schema:** `query` (string, required), `max_price` (number, optional)
- **handle():** `Product::scopeSearch($query)->when($maxPrice, ...)->get()`, writes each product to `$context->addProduct()`, returns formatted string list

### `app/Ai/Tools/GetProductDetailsTool.php` *(edit stub)*
- **Description:** "Get full details of a specific product by its ID."
- **Schema:** `product_id` (integer, required)
- **handle():** `Product::find($id)`, adds to context, returns "ID: X, Name: Y, Price: $Z, Stock: N"

### `app/Ai/Tools/ListProductsTool.php` *(edit stub)*
- **Description:** "List all products. Optionally filter to show only products currently in stock."
- **Schema:** `in_stock_only` (boolean, optional)
- **handle():** `Product::when($inStockOnly, fn($q) => $q->where('stock', '>', 0))->get()`, adds all to context

### `app/Ai/Tools/PlaceOrderTool.php` *(edit stub)*
- **Description:** "Place an order for one or more products on behalf of the user. Always confirm with the user before calling this."
- **Schema:** `items` (array of `{product_id: integer, quantity: integer}`, min 1, required)
- **handle():** Replicates `OrderController::store` logic — stock validation + `DB::transaction` for `Order` + `OrderItem` + `product->decrement('stock')`. Returns order summary string or error.
- **Constructor:** injects `User $user` (for `Order::create(['user_id' => $user->id])`)

---

## Phase 2 — Backend: Agent

### `app/Ai/Agents/ShoppingAssistantAgent.php` *(edit stub)*

```php
#[Provider(Lab::Anthropic)]
#[Model('claude-opus-4-8')]
class ShoppingAssistantAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(
        private User $user,
        private AgentContext $context
    ) {}

    public function instructions(): string { ... }
    public function tools(): iterable { ... }
    // No messages() — RemembersConversations provides it
}
```

**Instructions summary:** "You are a helpful shopping assistant. Use tools to search real product data. When user wants to order, confirm the exact items + total cost first, then call place_order only after they confirm."

**tools():** Returns `[new SearchProductsTool($context), new GetProductDetailsTool($context), new ListProductsTool($context), new PlaceOrderTool($user)]`

**Attributes:**
- `use Laravel\Ai\Attributes\Provider` and `use Laravel\Ai\Enums\Lab`
- `use Laravel\Ai\Attributes\Model`
- `use Laravel\Ai\Concerns\RemembersConversations`

---

## Phase 3 — Backend: Controller & Routes

### `app/Http/Controllers/Api/ChatController.php` *(edit stub)*

```
POST /api/v1/chat
Auth: Sanctum

Request validation:
  message          string, required, max:2000
  conversation_id  string, nullable

Flow:
  1. Load authenticated user
  2. Create AgentContext
  3. Create ShoppingAssistantAgent($user, $context)
  4. If conversation_id provided:
       - Verify conversation belongs to $user (query agent_conversations table)
       - If not found/wrong user → start fresh (ignore stale ID)
       - Otherwise: $agent->continue($conversationId, $user)
     Else:
       $agent->forUser($user)
  5. $response = $agent->prompt($message)
  6. Return successResponse([
       'reply'           => (string) $response,
       'conversation_id' => $response->conversationId,
       'products'        => $context->getProducts(),
     ])
```

Uses `ApiResponseTrait`. Error: wrap in try/catch, return `errorResponse()` on failure.

### `routes/api.php` *(edit)*
Add inside the existing `auth:sanctum` group:
```php
Route::post('/chat', [ChatController::class, 'chat']);
```

### `app/Models/User.php` *(edit)*
Add `use Laravel\Ai\Concerns\HasConversations;` and `use HasConversations;` in the class.

### `.env` *(manual step — tell user)*
```
ANTHROPIC_API_KEY=your_anthropic_key_here
```

---

## Phase 4 — Frontend: Chat Composable

### `src/services/chat.ts` *(new)*

Singleton pattern (module-level `state`) matching `auth.ts` style.

**Types:**
```typescript
type Product = { id: number; name: string; price: string; stock: number }
type ChatMessage = { id: string; role: 'user' | 'assistant'; content: string; products?: Product[] }
type ChatState = {
  messages: ChatMessage[]
  conversationId: string | null
  isLoading: boolean
  isOpen: boolean
  error: string | null
}
```

**State:** Module-level `reactive<ChatState>(...)` with `isOpen: false`.

**localStorage key:** `'ecom_chat_conversation_id'`

**Exported `useChat()` returns:**
| Method/Computed | Description |
|---|---|
| `messages` | `computed(() => state.messages)` |
| `isLoading` | `computed(() => state.isLoading)` |
| `isOpen` | `computed(() => state.isOpen)` |
| `error` | `computed(() => state.error)` |
| `sendMessage(text)` | Appends user message optimistically, POSTs to `/chat`, appends AI reply |
| `toggleChat()` | Flips `state.isOpen` |
| `clearChat()` | Resets messages + conversationId, removes from localStorage |
| `initFromStorage()` | Loads `conversationId` from localStorage on mount |

**sendMessage flow:**
```
1. Push { id: uuid, role: 'user', content: text } to state.messages
2. state.isLoading = true
3. POST /chat { message: text, conversation_id: state.conversationId }
4. On success: push AI message { role: 'assistant', content: data.reply, products: data.products }
            set state.conversationId = data.conversation_id
            save conversationId to localStorage
5. On error: set state.error, remove optimistic user message or mark failed
6. Finally: state.isLoading = false
```

---

## Phase 5 — Frontend: Components

### `src/components/ChatBubble.vue` *(new)*

- `position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 50`
- Round button (`w-14 h-14 rounded-full bg-blue-600 hover:bg-blue-700 shadow-lg`)
- Chat icon SVG (speech bubble)
- Only renders when `auth.isAuthenticated`
- Click calls `chat.toggleChat()`

### `src/components/ChatPopup.vue` *(new)*

- `position: fixed; bottom: 5.5rem; right: 1.5rem; z-index: 40; width: 380px; height: 500px`
- `v-show="chat.isOpen"` (keep mounted for scroll state)
- **Header:** "Shopping Assistant" + X close button
- **Message list** (`overflow-y-auto flex-1`):
  - User messages: right-aligned, `bg-blue-600 text-white rounded-tl-2xl rounded-bl-2xl rounded-tr-2xl`
  - AI messages: left-aligned, `bg-gray-100 text-gray-900 rounded-tr-2xl rounded-br-2xl rounded-tl-2xl`
  - Under each AI message: product cards (if `msg.products?.length`)
- **Product card** (inline in AI messages): `border rounded-lg p-2 flex justify-between items-center`, shows name, price, stock badge (green "In Stock" / red "Out of Stock")
- **Typing indicator** when `chat.isLoading`: three bouncing gray dots
- **Input bar** at bottom: text input + Send button, disabled while loading
- **Auto-scroll** to bottom on new message via `watchEffect` + `scrollTop = el.scrollHeight`

### `src/App.vue` *(edit)*

```vue
<script setup lang="ts">
import { RouterView } from 'vue-router'
import { useAuth } from './services/auth'
import ChatBubble from './components/ChatBubble.vue'
import ChatPopup from './components/ChatPopup.vue'
const auth = useAuth()
</script>

<template>
  <RouterView />
  <template v-if="auth.isAuthenticated.value">
    <ChatBubble />
    <ChatPopup />
  </template>
</template>
```

---

## Implementation Order

1. Phase 1 → AgentContext + 4 tool files
2. Phase 2 → ShoppingAssistantAgent
3. Phase 3 → ChatController + routes + User model
4. Run `vendor/bin/pint --dirty --format agent` (PHP style fix)
5. Phase 4 → `src/services/chat.ts`
6. Phase 5 → ChatBubble.vue, ChatPopup.vue, App.vue update
7. Manual: user adds `ANTHROPIC_API_KEY` to `.env`
8. Test: start servers (`php artisan serve` + `npm run dev`), open browser, test chatbot

## Open Questions / Assumptions
- **Model:** `claude-opus-4-8` (per claude-api skill requirement)
- **Auth:** Chatbot only shown to authenticated users; `POST /chat` is Sanctum-protected
- **Chat history:** Persisted to DB via `RemembersConversations`; conversation resumable via `conversation_id` in localStorage
- **Streaming:** Standard JSON (no SSE) — simpler for first version
- **Order scope:** AI can place orders; instructions say to confirm first
