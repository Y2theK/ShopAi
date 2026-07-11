import { isAxiosError } from 'axios'
import { computed, reactive } from 'vue'
import api from './api'

type Product = { id: number; name: string; price: string; stock: number }

type PendingItem = { id: number; name: string; price: string; quantity: number }

export type DeliveryAddress = {
  phone: string
  secondary_phone: string
  address: string
  city: string
  state: string
  country: string
}

export type OrderInfo = {
  order_code: string
  status: string
  phone: string | null
  secondary_phone: string | null
  address: string | null
  city: string | null
  state: string | null
  country: string | null
}

type ChatMessage = {
  id: string
  role: 'user' | 'assistant'
  content: string
  products?: Product[]
  orderInfo?: OrderInfo
  awaitingConfirmation?: boolean
}

type ChatState = {
  messages: ChatMessage[]
  conversationId: string | null
  pendingItems: PendingItem[]
  deliveryAddress: DeliveryAddress | null
  isLoading: boolean
  isOpen: boolean
  error: string | null
}

const STORAGE_KEY = 'ecom_chat_conversation_id'

// Mirrors PlaceOrderTool limits on the backend.
const MAX_DISTINCT_ITEMS = 10
const MAX_QUANTITY_PER_ITEM = 20

const state = reactive<ChatState>({
  messages: [],
  conversationId: null,
  pendingItems: [],
  deliveryAddress: null,
  isLoading: false,
  isOpen: false,
  error: null,
})

function uuid(): string {
  return crypto.randomUUID()
}

export function useChat() {
  const messages = computed(() => state.messages)
  const pendingItems = computed(() => state.pendingItems)
  const pendingCount = computed(() =>
    state.pendingItems.reduce((sum, item) => sum + item.quantity, 0),
  )
  const pendingTotal = computed(() =>
    state.pendingItems.reduce((sum, item) => sum + Number(item.price) * item.quantity, 0),
  )
  const deliveryAddress = computed(() => state.deliveryAddress)
  const isLoading = computed(() => state.isLoading)
  const isOpen = computed(() => state.isOpen)
  const error = computed(() => state.error)

  function toggleChat() {
    state.isOpen = !state.isOpen
  }

  function clearChat() {
    state.messages = []
    state.conversationId = null
    state.pendingItems = []
    state.deliveryAddress = null
    state.error = null
    localStorage.removeItem(STORAGE_KEY)
  }

  function setDeliveryAddress(address: DeliveryAddress) {
    state.deliveryAddress = address
  }

  function initFromStorage() {
    const stored = localStorage.getItem(STORAGE_KEY)
    if (stored) {
      state.conversationId = stored
    }
  }

  function addToOrder(product: Product) {
    const existing = state.pendingItems.find((item) => item.id === product.id)

    if (existing) {
      existing.quantity = Math.min(existing.quantity + 1, MAX_QUANTITY_PER_ITEM)
      return
    }

    if (state.pendingItems.length >= MAX_DISTINCT_ITEMS) {
      state.error = `You can add up to ${MAX_DISTINCT_ITEMS} different products per order.`
      return
    }

    state.pendingItems.push({
      id: product.id,
      name: product.name,
      price: product.price,
      quantity: 1,
    })
  }

  function updateQuantity(productId: number, delta: number) {
    const item = state.pendingItems.find((i) => i.id === productId)
    if (!item) return

    const next = item.quantity + delta
    if (next < 1) {
      removeFromOrder(productId)
      return
    }
    item.quantity = Math.min(next, MAX_QUANTITY_PER_ITEM)
  }

  function removeFromOrder(productId: number) {
    state.pendingItems = state.pendingItems.filter((item) => item.id !== productId)
  }

  async function checkoutOrder() {
    if (state.pendingItems.length === 0 || state.isLoading) {
      return
    }

    const lines = state.pendingItems
      .map((item) => `${item.quantity}x ${item.name}`)
      .join(', ')

    await sendMessage(
      `I'd like to place one order for: ${lines}. That's my complete order.`,
      { confirmationRequest: true },
    )
  }

  async function confirmOrder() {
    await sendMessage('Confirm, place my order.')
  }

  async function sendMessage(
    text: string,
    options?: { confirmationRequest?: boolean },
  ): Promise<boolean> {
    if (!text.trim() || state.isLoading) {
      return false
    }

    const userMessage: ChatMessage = { id: uuid(), role: 'user', content: text }
    state.messages.push(userMessage)
    state.isLoading = true
    state.error = null

    // Only attach the address while an order is awaiting confirmation, so the
    // PII doesn't ride along on every ordinary chat message. This covers both
    // the confirm button and a typed "yes".
    const lastMessage = state.messages[state.messages.length - 2]
    const attachAddress = state.deliveryAddress && lastMessage?.awaitingConfirmation

    try {
      const response = await api.post('/chat', {
        message: text,
        conversation_id: state.conversationId,
        ...(attachAddress ? { delivery_address: state.deliveryAddress } : {}),
      })

      const data = response.data?.data ?? response.data

      // The reply to a checkout message is a confirmation prompt for the same
      // items — "Add" buttons there would restart the flow, so hide products
      // and surface a confirm action instead.
      state.messages.push({
        id: uuid(),
        role: 'assistant',
        content: data.reply,
        products:
          !options?.confirmationRequest && data.products?.length ? data.products : undefined,
        orderInfo: data.order_info ?? undefined,
        awaitingConfirmation: options?.confirmationRequest || undefined,
      })

      // The cart survives checkout + confirmation; it only clears once the
      // backend reports the order was actually placed.
      if (data.order_placed) {
        state.pendingItems = []
      }

      state.conversationId = data.conversation_id
      if (data.conversation_id) {
        localStorage.setItem(STORAGE_KEY, data.conversation_id)
      }

      return true
    } catch (err) {
      if (isAxiosError(err) && err.response?.status === 429) {
        const retryAfter = Number(err.response.headers['retry-after'])
        state.error =
          Number.isFinite(retryAfter) && retryAfter > 0
            ? `You're sending messages too quickly — try again in ${retryAfter}s.`
            : "You're sending messages too quickly — please slow down."
      } else {
        state.error = 'Failed to send message. Please try again.'
      }
      state.messages = state.messages.filter((m) => m.id !== userMessage.id)
      return false
    } finally {
      state.isLoading = false
    }
  }

  return {
    messages,
    pendingItems,
    pendingCount,
    pendingTotal,
    deliveryAddress,
    setDeliveryAddress,
    isLoading,
    isOpen,
    error,
    sendMessage,
    addToOrder,
    updateQuantity,
    removeFromOrder,
    checkoutOrder,
    confirmOrder,
    toggleChat,
    clearChat,
    initFromStorage,
  }
}
