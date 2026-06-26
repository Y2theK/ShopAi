import { computed, reactive } from 'vue'
import api from './api'

type Product = { id: number; name: string; price: string; stock: number }

type ChatMessage = {
  id: string
  role: 'user' | 'assistant'
  content: string
  products?: Product[]
}

type ChatState = {
  messages: ChatMessage[]
  conversationId: string | null
  isLoading: boolean
  isOpen: boolean
  error: string | null
}

const STORAGE_KEY = 'ecom_chat_conversation_id'

const state = reactive<ChatState>({
  messages: [],
  conversationId: null,
  isLoading: false,
  isOpen: false,
  error: null,
})

function uuid(): string {
  return crypto.randomUUID()
}

export function useChat() {
  const messages = computed(() => state.messages)
  const isLoading = computed(() => state.isLoading)
  const isOpen = computed(() => state.isOpen)
  const error = computed(() => state.error)

  function toggleChat() {
    state.isOpen = !state.isOpen
  }

  function clearChat() {
    state.messages = []
    state.conversationId = null
    state.error = null
    localStorage.removeItem(STORAGE_KEY)
  }

  function initFromStorage() {
    const stored = localStorage.getItem(STORAGE_KEY)
    if (stored) {
      state.conversationId = stored
    }
  }

  async function sendMessage(text: string) {
    if (!text.trim() || state.isLoading) {
      return
    }

    const userMessage: ChatMessage = { id: uuid(), role: 'user', content: text }
    state.messages.push(userMessage)
    state.isLoading = true
    state.error = null

    try {
      const response = await api.post('/chat', {
        message: text,
        conversation_id: state.conversationId,
      })

      const data = response.data?.data ?? response.data

      state.messages.push({
        id: uuid(),
        role: 'assistant',
        content: data.reply,
        products: data.products?.length ? data.products : undefined,
      })

      state.conversationId = data.conversation_id
      if (data.conversation_id) {
        localStorage.setItem(STORAGE_KEY, data.conversation_id)
      }
    } catch {
      state.error = 'Failed to send message. Please try again.'
      state.messages = state.messages.filter((m) => m.id !== userMessage.id)
    } finally {
      state.isLoading = false
    }
  }

  return {
    messages,
    isLoading,
    isOpen,
    error,
    sendMessage,
    toggleChat,
    clearChat,
    initFromStorage,
  }
}
