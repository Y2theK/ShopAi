<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { useChat, type DeliveryAddress, type OrderInfo } from '../services/chat'
import { fetchCategories, type Category } from '../services/dashboard'
import { renderMarkdown } from '../utils/markdown'

const chat = useChat()
const messageList = ref<HTMLElement | null>(null)
const inputText = ref('')
const categories = ref<Category[]>([])
const isCartOpen = ref(false)
const isExpanded = ref(false)

const lastMessageId = computed(
  () => chat.messages.value[chat.messages.value.length - 1]?.id,
)

const addressForm = reactive<DeliveryAddress>({
  phone: '',
  secondary_phone: '',
  address: '',
  city: '',
  state: '',
  country: '',
})

const showAddressForm = computed(() => {
  const last = chat.messages.value[chat.messages.value.length - 1]
  return Boolean(last?.awaitingConfirmation) && !chat.isLoading.value
})

// Prefill with the last used address whenever a new confirmation prompt appears.
watch(showAddressForm, (visible) => {
  if (visible && chat.deliveryAddress.value) {
    Object.assign(addressForm, chat.deliveryAddress.value)
  }
})

const isAddressValid = computed(() =>
  [addressForm.phone, addressForm.address, addressForm.city, addressForm.state, addressForm.country]
    .every((field) => field.trim().length > 0),
)

async function handleConfirmOrder() {
  if (!isAddressValid.value || chat.isLoading.value) return

  chat.setDeliveryAddress({
    phone: addressForm.phone.trim(),
    secondary_phone: addressForm.secondary_phone.trim(),
    address: addressForm.address.trim(),
    city: addressForm.city.trim(),
    state: addressForm.state.trim(),
    country: addressForm.country.trim(),
  })

  await chat.confirmOrder()
}

onMounted(async () => {
  chat.initFromStorage()

  try {
    categories.value = await fetchCategories()
  } catch {
    categories.value = []
  }
})

async function scrollToBottom() {
  await nextTick()
  messageList.value?.scrollTo({
    top: messageList.value.scrollHeight,
    behavior: 'smooth',
  })
}

watch(() => chat.messages.value.length, scrollToBottom)
watch(() => chat.isLoading.value, scrollToBottom)

// Close the panel when the cart empties so it doesn't pop open on the next add.
watch(
  () => chat.pendingItems.value.length,
  (length) => {
    if (length === 0) {
      isCartOpen.value = false
    }
  },
)

async function handleSend() {
  const text = inputText.value.trim()
  if (!text || chat.isLoading.value) return
  inputText.value = ''
  await chat.sendMessage(text)
}

function handleKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    handleSend()
  }
}

async function handleCheckout() {
  // Collapse the cart detail once checkout is sent; items stay pending until
  // the order is actually placed.
  isCartOpen.value = false
  await chat.checkoutOrder()
}

function orderInfoRegion(info: OrderInfo) {
  return [info.city, info.state, info.country].filter(Boolean).join(', ')
}

function orderInfoPhones(info: OrderInfo) {
  return [info.phone, info.secondary_phone].filter(Boolean).join(' · ')
}

async function quickCategory(categoryName: string) {
  if (chat.isLoading.value) return
  await chat.sendMessage(`Show me ${categoryName} products`)
}
</script>

<template>
  <div v-show="chat.isOpen.value" class="chat-popup" :class="{ 'chat-popup--expanded': isExpanded }">
    <!-- Header -->
    <header class="chat-header">
      <div class="chat-header-info">
        <span class="chat-status-dot" />
        <span class="chat-header-title">Shopping Assistant</span>
      </div>
      <div class="chat-header-actions">
        <button
          class="chat-header-btn"
          :aria-label="isExpanded ? 'Exit full screen' : 'Full screen'"
          @click="isExpanded = !isExpanded"
        >
          <svg v-if="!isExpanded" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
          </svg>
          <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 9V5m0 4H5m4 0L4 4m11 5h4m-4 0V5m0 4l5-5M9 15v4m0-4H5m4 0l-5 5m11-5l5 5m-5-5v4m0-4h4" />
          </svg>
        </button>
        <button class="chat-header-btn" aria-label="Close chat" @click="chat.toggleChat()">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </header>

    <!-- Messages -->
    <div ref="messageList" class="chat-messages">
      <div v-if="chat.messages.value.length === 0" class="chat-empty">
        <p>Hi! I can help you find products and place orders.<br>What are you looking for?</p>
      </div>

      <template v-for="msg in chat.messages.value" :key="msg.id">
        <div v-if="msg.role === 'user'" class="msg-row msg-row--user">
          <div class="bubble bubble--user">{{ msg.content }}</div>
        </div>

        <div v-else class="msg-row msg-row--ai">
          <!-- eslint-disable-next-line vue/no-v-html -->
          <div class="bubble bubble--ai" v-html="renderMarkdown(msg.content)" />

          <div v-if="msg.orderInfo" class="delivery-card">
            <p class="delivery-card-title">
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              Delivery details · {{ msg.orderInfo.order_code }}
            </p>
            <p class="delivery-card-line">{{ msg.orderInfo.address }}</p>
            <p v-if="orderInfoRegion(msg.orderInfo)" class="delivery-card-line delivery-card-line--muted">
              {{ orderInfoRegion(msg.orderInfo) }}
            </p>
            <p v-if="orderInfoPhones(msg.orderInfo)" class="delivery-card-line delivery-card-line--muted">
              {{ orderInfoPhones(msg.orderInfo) }}
            </p>
          </div>

          <form
            v-if="msg.awaitingConfirmation && msg.id === lastMessageId && !chat.isLoading.value"
            class="delivery-form"
            @submit.prevent="handleConfirmOrder"
          >
            <p class="delivery-form-title">Delivery details</p>
            <div class="delivery-form-grid">
              <input v-model="addressForm.phone" type="tel" placeholder="Phone *" maxlength="30" />
              <input v-model="addressForm.secondary_phone" type="tel" placeholder="Secondary phone" maxlength="30" />
            </div>
            <textarea v-model="addressForm.address" placeholder="Full address *" rows="2" maxlength="500" />
            <div class="delivery-form-grid">
              <input v-model="addressForm.city" type="text" placeholder="City *" maxlength="100" />
              <input v-model="addressForm.state" type="text" placeholder="State *" maxlength="100" />
            </div>
            <input v-model="addressForm.country" type="text" placeholder="Country *" maxlength="100" />
            <button type="submit" class="confirm-order-btn" :disabled="!isAddressValid">
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
              Confirm order
            </button>
          </form>

          <div v-else-if="msg.products?.length && !chat.isLoading.value" class="quick-order-row">
            <button
              v-for="product in msg.products"
              :key="product.id"
              class="quick-order-btn"
              @click="chat.addToOrder(product)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              Add {{ product.name }}
            </button>
          </div>
        </div>
      </template>

      <div v-if="chat.isLoading.value" class="typing-indicator">
        <span /><span /><span />
      </div>
    </div>

    <!-- Error -->
    <div v-if="chat.error.value" class="chat-error">{{ chat.error.value }}</div>

    <!-- Pending order -->
    <div v-if="isCartOpen && chat.pendingItems.value.length > 0" class="chat-cart">
      <div v-for="item in chat.pendingItems.value" :key="item.id" class="chat-cart-item">
        <span class="chat-cart-name">{{ item.name }}</span>
        <div class="chat-cart-qty">
          <button type="button" aria-label="Decrease quantity" @click="chat.updateQuantity(item.id, -1)">−</button>
          <span>{{ item.quantity }}</span>
          <button type="button" aria-label="Increase quantity" @click="chat.updateQuantity(item.id, 1)">+</button>
        </div>
        <button
          type="button"
          class="chat-cart-remove"
          aria-label="Remove item"
          @click="chat.removeFromOrder(item.id)"
        >×</button>
      </div>
      <button
        type="button"
        class="chat-cart-checkout"
        :disabled="chat.isLoading.value"
        @click="handleCheckout"
      >
        Place order · ${{ chat.pendingTotal.value.toFixed(2) }}
      </button>
    </div>

    <!-- Quick-access categories -->
    <div v-if="categories.length > 0" class="chat-category-row">
      <button
        v-for="category in categories"
        :key="category.id"
        type="button"
        class="chat-category-chip"
        :disabled="chat.isLoading.value"
        @click="quickCategory(category.name)"
      >{{ category.name }}</button>
    </div>

    <!-- Input -->
    <div class="chat-input-bar">
      <input
        v-model="inputText"
        type="text"
        placeholder="Ask about products…"
        class="chat-input"
        :disabled="chat.isLoading.value"
        @keydown="handleKeydown"
      />
      <button
        type="button"
        class="chat-cart-btn"
        :class="{ 'chat-cart-btn--active': isCartOpen }"
        aria-label="Toggle pending order"
        @click="isCartOpen = !isCartOpen"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
        <span v-if="chat.pendingCount.value > 0" class="chat-cart-badge">{{ chat.pendingCount.value }}</span>
      </button>
      <button
        class="chat-send-btn"
        :disabled="chat.isLoading.value || !inputText.trim()"
        @click="handleSend"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
        </svg>
      </button>
    </div>
  </div>
</template>

<style scoped>
.chat-popup {
  position: fixed;
  bottom: 5.5rem;
  right: 1.5rem;
  z-index: 40;
  width: 380px;
  height: 520px;
  display: flex;
  flex-direction: column;
  border-radius: 24px;
  border: 1px solid rgba(129, 140, 248, 0.2);
  /* Near-opaque instead of translucent + backdrop-filter: blurring the
     backdrop on every scrolled frame makes the message list stutter. */
  background: rgba(15, 23, 42, 0.97);
  box-shadow:
    0 0 0 1px rgba(99, 102, 241, 0.1),
    0 24px 64px rgba(15, 23, 42, 0.6),
    0 8px 24px rgba(99, 102, 241, 0.15);
  overflow: hidden;
  color: #f1f5f9;
}

.chat-popup--expanded {
  /* Above the chat launcher (z-index 50) so the bubble can't sit on the input bar. */
  z-index: 60;
  inset: 1.25rem;
  width: auto;
  height: auto;
}

/* Full screen is wide — keep the conversation column readable and centered. */
.chat-popup--expanded .chat-messages {
  padding-inline: max(16px, calc((100% - 720px) / 2));
}

.chat-popup--expanded .chat-category-row,
.chat-popup--expanded .chat-cart,
.chat-popup--expanded .chat-error,
.chat-popup--expanded .chat-input-bar {
  padding-inline: max(14px, calc((100% - 720px) / 2));
}

/* Header */
.chat-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  background: linear-gradient(135deg, rgba(99, 102, 241, 0.25) 0%, rgba(139, 92, 246, 0.18) 100%);
  border-bottom: 1px solid rgba(129, 140, 248, 0.18);
}

.chat-header-info {
  display: flex;
  align-items: center;
  gap: 8px;
}

.chat-status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #86efac;
  box-shadow: 0 0 6px rgba(134, 239, 172, 0.7);
}

.chat-header-title {
  font-size: 0.9rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  color: #e2e8f0;
}

.chat-header-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}

.chat-header-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border: none;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.07);
  color: rgba(226, 232, 240, 0.7);
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
  padding: 0;
}

.chat-header-btn:hover {
  background: rgba(255, 255, 255, 0.13);
  color: #f1f5f9;
}

/* Messages */
.chat-messages {
  flex: 1;
  overflow-y: auto;
  /* Keep wheel/touch scrolling inside the chat from chaining to the page. */
  overscroll-behavior: contain;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  scrollbar-width: thin;
  scrollbar-color: rgba(129, 140, 248, 0.25) transparent;
}

.chat-empty {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  font-size: 0.875rem;
  line-height: 1.6;
  color: rgba(148, 163, 184, 0.8);
}

.msg-row {
  display: flex;
  flex-direction: column;
}

.msg-row--user {
  align-items: flex-end;
}

.msg-row--ai {
  align-items: flex-start;
  gap: 8px;
}

.bubble {
  max-width: 80%;
  padding: 10px 14px;
  font-size: 0.875rem;
  line-height: 1.55;
  white-space: pre-wrap;
  word-break: break-word;
}

.bubble--user {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  border-radius: 18px 4px 18px 18px;
  box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);
}

.bubble--ai {
  background: rgba(30, 41, 59, 0.88);
  color: #e2e8f0;
  border: 1px solid rgba(148, 163, 184, 0.12);
  border-radius: 4px 18px 18px 18px;
}

.bubble--ai :deep(p) {
  margin: 0 0 6px;
}
.bubble--ai :deep(p:last-child),
.bubble--ai :deep(br:last-child) {
  margin: 0;
}
.bubble--ai :deep(br) {
  display: block;
  content: '';
  margin: 2px 0;
}
.bubble--ai :deep(strong) {
  color: #c7d2fe;
  font-weight: 700;
}
.bubble--ai :deep(em) {
  color: #a5b4fc;
}
.bubble--ai :deep(code) {
  background: rgba(99, 102, 241, 0.15);
  color: #a5b4fc;
  padding: 1px 5px;
  border-radius: 4px;
  font-size: 0.82em;
}
.bubble--ai :deep(table) {
  width: 100%;
  border-collapse: collapse;
  margin: 6px 0;
  font-size: 0.8rem;
}
.bubble--ai :deep(th) {
  background: rgba(99, 102, 241, 0.18);
  color: #c7d2fe;
  font-weight: 700;
  padding: 6px 10px;
  text-align: left;
  border-bottom: 1px solid rgba(129, 140, 248, 0.25);
}
.bubble--ai :deep(td) {
  padding: 6px 10px;
  border-bottom: 1px solid rgba(148, 163, 184, 0.1);
  color: #e2e8f0;
}
.bubble--ai :deep(tr:last-child td) {
  border-bottom: none;
}
.bubble--ai :deep(tr:nth-child(even) td) {
  background: rgba(99, 102, 241, 0.06);
}
.bubble--ai :deep(.stock-in) {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
  background: rgba(34, 197, 94, 0.14);
  color: #86efac;
}
.quick-order-row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  max-width: 90%;
}

.quick-order-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 11px;
  border-radius: 999px;
  border: 1px solid rgba(129, 140, 248, 0.35);
  background: rgba(99, 102, 241, 0.1);
  color: #a5b4fc;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease, transform 0.15s ease;
  white-space: nowrap;
}

.quick-order-btn:hover {
  background: rgba(99, 102, 241, 0.22);
  border-color: rgba(129, 140, 248, 0.6);
  color: #c7d2fe;
  transform: translateY(-1px);
}

.delivery-card {
  width: 80%;
  padding: 10px 14px;
  border-radius: 4px 14px 14px 14px;
  border: 1px solid rgba(129, 140, 248, 0.25);
  background: rgba(99, 102, 241, 0.08);
}

.delivery-card-title {
  display: flex;
  align-items: center;
  gap: 5px;
  margin: 0 0 6px;
  font-size: 0.75rem;
  font-weight: 700;
  color: #c7d2fe;
}

.delivery-card-line {
  margin: 0 0 2px;
  font-size: 0.8rem;
  line-height: 1.45;
  color: #e2e8f0;
}

.delivery-card-line--muted {
  color: rgba(226, 232, 240, 0.6);
  font-size: 0.75rem;
}

.delivery-form {
  display: flex;
  flex-direction: column;
  gap: 6px;
  width: 90%;
  padding: 10px 12px;
  border-radius: 4px 14px 14px 14px;
  border: 1px solid rgba(148, 163, 184, 0.12);
  background: rgba(30, 41, 59, 0.88);
}

.delivery-form-title {
  margin: 0;
  font-size: 0.78rem;
  font-weight: 700;
  color: #c7d2fe;
}

.delivery-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px;
}

.delivery-form input,
.delivery-form textarea {
  width: 100%;
  padding: 7px 10px;
  border-radius: 8px;
  border: 1px solid rgba(148, 163, 184, 0.2);
  background: rgba(15, 23, 42, 0.7);
  color: #f1f5f9;
  font-size: 0.78rem;
  font-family: inherit;
  outline: none;
  resize: none;
  transition: border-color 0.15s ease;
}

.delivery-form input::placeholder,
.delivery-form textarea::placeholder {
  color: rgba(148, 163, 184, 0.55);
}

.delivery-form input:focus,
.delivery-form textarea:focus {
  border-color: rgba(129, 140, 248, 0.6);
}

.delivery-form .confirm-order-btn {
  justify-content: center;
  margin-top: 2px;
}

.confirm-order-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
  transform: none;
}

.confirm-order-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 14px;
  border-radius: 999px;
  border: 1px solid rgba(74, 222, 128, 0.4);
  background: rgba(34, 197, 94, 0.15);
  color: #86efac;
  font-size: 0.78rem;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease, transform 0.15s ease;
  white-space: nowrap;
}

.confirm-order-btn:hover:not(:disabled) {
  background: rgba(34, 197, 94, 0.28);
  border-color: rgba(74, 222, 128, 0.65);
  transform: translateY(-1px);
}

/* Pending order */
.chat-cart {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 10px 14px;
  max-height: 150px;
  overflow-y: auto;
  border-top: 1px solid rgba(129, 140, 248, 0.15);
  background: rgba(30, 41, 59, 0.75);
  scrollbar-width: thin;
  scrollbar-color: rgba(129, 140, 248, 0.25) transparent;
}

.chat-cart-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.78rem;
  color: #e2e8f0;
}

.chat-cart-name {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chat-cart-qty {
  display: flex;
  align-items: center;
  gap: 6px;
}

.chat-cart-qty button {
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(129, 140, 248, 0.35);
  border-radius: 6px;
  background: rgba(99, 102, 241, 0.1);
  color: #a5b4fc;
  font-size: 0.85rem;
  line-height: 1;
  cursor: pointer;
  padding: 0;
  transition: background 0.15s ease, color 0.15s ease;
}

.chat-cart-qty button:hover {
  background: rgba(99, 102, 241, 0.25);
  color: #c7d2fe;
}

.chat-cart-qty span {
  min-width: 16px;
  text-align: center;
  font-weight: 700;
  color: #c7d2fe;
}

.chat-cart-remove {
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  border-radius: 6px;
  background: transparent;
  color: rgba(226, 232, 240, 0.5);
  font-size: 1rem;
  line-height: 1;
  cursor: pointer;
  padding: 0;
  transition: background 0.15s ease, color 0.15s ease;
}

.chat-cart-remove:hover {
  background: rgba(248, 113, 113, 0.15);
  color: #fca5a5;
}

.chat-cart-checkout {
  margin-top: 2px;
  padding: 8px 12px;
  border: none;
  border-radius: 10px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  font-size: 0.8rem;
  font-weight: 700;
  cursor: pointer;
  transition: opacity 0.15s ease, transform 0.15s ease;
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35);
}

.chat-cart-checkout:hover:not(:disabled) {
  transform: translateY(-1px);
}

.chat-cart-checkout:disabled {
  opacity: 0.4;
  cursor: not-allowed;
  transform: none;
}

.bubble--ai :deep(.stock-out) {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
  background: rgba(248, 113, 113, 0.14);
  color: #fca5a5;
}


/* Typing indicator */
.typing-indicator {
  display: flex;
  gap: 5px;
  padding: 4px 2px;
}

.typing-indicator span {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #818cf8;
  animation: typing-bounce 1.2s ease-in-out infinite;
}

.typing-indicator span:nth-child(2) { animation-delay: 0.15s; }
.typing-indicator span:nth-child(3) { animation-delay: 0.3s; }

@keyframes typing-bounce {
  0%, 60%, 100% { transform: translateY(0); opacity: 0.5; }
  30% { transform: translateY(-6px); opacity: 1; }
}

/* Error */
.chat-error {
  padding: 8px 16px;
  font-size: 0.78rem;
  color: #fca5a5;
  background: rgba(248, 113, 113, 0.1);
  border-top: 1px solid rgba(248, 113, 113, 0.15);
}

/* Quick-access categories */
.chat-category-row {
  display: flex;
  gap: 6px;
  padding: 10px 14px;
  overflow-x: auto;
  scrollbar-width: none;
  border-top: 1px solid rgba(129, 140, 248, 0.15);
  background: rgba(15, 23, 42, 0.6);
}

.chat-category-row::-webkit-scrollbar {
  display: none;
}

.chat-category-chip {
  flex-shrink: 0;
  padding: 5px 11px;
  border-radius: 999px;
  border: 1px solid rgba(148, 163, 184, 0.25);
  background: rgba(30, 41, 59, 0.8);
  color: rgba(226, 232, 240, 0.8);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.chat-category-chip:hover:not(:disabled) {
  background: rgba(99, 102, 241, 0.18);
  border-color: rgba(129, 140, 248, 0.5);
  color: #c7d2fe;
}

.chat-category-chip:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Input */
.chat-input-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 14px;
  border-top: 1px solid rgba(129, 140, 248, 0.15);
  background: rgba(15, 23, 42, 0.6);
}

.chat-input {
  flex: 1;
  padding: 9px 14px;
  border-radius: 12px;
  border: 1px solid rgba(148, 163, 184, 0.2);
  background: rgba(30, 41, 59, 0.8);
  color: #f1f5f9;
  font-size: 0.875rem;
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.chat-input::placeholder {
  color: rgba(148, 163, 184, 0.55);
}

.chat-input:focus {
  border-color: rgba(129, 140, 248, 0.6);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.chat-input:disabled {
  opacity: 0.5;
}

.chat-cart-btn {
  position: relative;
  width: 38px;
  height: 38px;
  border-radius: 12px;
  border: 1px solid rgba(129, 140, 248, 0.35);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(99, 102, 241, 0.1);
  color: #a5b4fc;
  flex-shrink: 0;
  padding: 0;
  transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.chat-cart-btn:hover,
.chat-cart-btn--active {
  background: rgba(99, 102, 241, 0.25);
  border-color: rgba(129, 140, 248, 0.6);
  color: #c7d2fe;
}

.chat-cart-badge {
  position: absolute;
  top: -6px;
  right: -6px;
  min-width: 17px;
  height: 17px;
  padding: 0 4px;
  border-radius: 999px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  font-size: 0.65rem;
  font-weight: 700;
  line-height: 17px;
  text-align: center;
  box-shadow: 0 2px 6px rgba(99, 102, 241, 0.5);
}

.chat-send-btn {
  width: 38px;
  height: 38px;
  border-radius: 12px;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  flex-shrink: 0;
  transition: opacity 0.15s ease, transform 0.15s ease;
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35);
}

.chat-send-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(99, 102, 241, 0.5);
}

.chat-send-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
  transform: none;
}
</style>
