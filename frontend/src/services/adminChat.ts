import { computed, reactive } from 'vue'
import api from './api'

export type ChartPayload = {
  type: 'bar' | 'line'
  title: string
  labels: string[]
  datasets: Array<{ label: string; data: number[] }>
}

type AdminMessage = {
  id: string
  role: 'user' | 'assistant'
  content: string
  charts?: ChartPayload[]
}

type AdminChatState = {
  messages: AdminMessage[]
  conversationId: string | null
  isLoading: boolean
  error: string | null
}

const STORAGE_KEY = 'ecom_admin_conversation_id'

const state = reactive<AdminChatState>({
  messages: [],
  conversationId: null,
  isLoading: false,
  error: null,
})

function uuid(): string {
  return crypto.randomUUID()
}

export function useAdminChat() {
  const messages = computed(() => state.messages)
  const isLoading = computed(() => state.isLoading)
  const error = computed(() => state.error)

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

    const userMessage: AdminMessage = { id: uuid(), role: 'user', content: text }
    state.messages.push(userMessage)
    state.isLoading = true
    state.error = null

    try {
      const response = await api.post('/admin/chat', {
        message: text,
        conversation_id: state.conversationId,
      })

      const data = response.data?.data ?? response.data

      state.messages.push({
        id: uuid(),
        role: 'assistant',
        content: data.reply,
        charts: data.charts?.length ? data.charts : undefined,
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
    error,
    sendMessage,
    clearChat,
    initFromStorage,
  }
}
