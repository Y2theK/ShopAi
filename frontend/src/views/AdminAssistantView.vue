<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import ChartCard from '../components/ChartCard.vue'
import { useAdminChat } from '../services/adminChat'
import { renderMarkdown } from '../utils/markdown'

const chat = useAdminChat()
const messageList = ref<HTMLElement | null>(null)
const inputText = ref('')

const suggestions = [
  'How is the store doing overall?',
  'What are the best selling products?',
  'Which products are low on stock?',
  'How many products do we have in total?',
  'Who are our top customers?',
  'Show me the latest orders',
]

onMounted(() => {
  chat.initFromStorage()
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
</script>

<template>
  <main class="admin-shell">
    <section class="admin-panel">
      <header class="admin-header">
        <div>
          <p class="eyebrow">Admin</p>
          <h1>Admin Assistant</h1>
          <p class="subtitle">Ask about sales, inventory, customers, and orders.</p>
        </div>

        <div class="header-actions">
          <button
            v-if="chat.messages.value.length"
            type="button"
            class="ghost-button"
            @click="chat.clearChat()"
          >
            New conversation
          </button>
          <RouterLink to="/" class="ghost-button">Back to store</RouterLink>
        </div>
      </header>

      <div ref="messageList" class="admin-messages">
        <div v-if="chat.messages.value.length === 0" class="admin-empty">
          <p>Hi! I can answer questions about your store's sales, inventory, customers, and orders.</p>
          <div class="suggestion-row">
            <button
              v-for="suggestion in suggestions"
              :key="suggestion"
              type="button"
              class="suggestion-btn"
              @click="chat.sendMessage(suggestion)"
            >
              {{ suggestion }}
            </button>
          </div>
        </div>

        <template v-for="msg in chat.messages.value" :key="msg.id">
          <div v-if="msg.role === 'user'" class="msg-row msg-row--user">
            <div class="bubble bubble--user">{{ msg.content }}</div>
          </div>

          <div v-else class="msg-row msg-row--ai">
            <!-- eslint-disable-next-line vue/no-v-html -->
            <div class="bubble bubble--ai" v-html="renderMarkdown(msg.content)" />

            <div v-if="msg.charts?.length" class="chart-stack">
              <ChartCard
                v-for="(chart, index) in msg.charts"
                :key="`${msg.id}-chart-${index}`"
                :chart="chart"
              />
            </div>
          </div>
        </template>

        <div v-if="chat.isLoading.value" class="typing-indicator">
          <span /><span /><span />
        </div>
      </div>

      <div v-if="chat.error.value" class="admin-error">{{ chat.error.value }}</div>

      <div class="admin-input-bar">
        <input
          v-model="inputText"
          type="text"
          placeholder="Ask about sales, inventory, customers, orders…"
          class="admin-input"
          :disabled="chat.isLoading.value"
          @keydown="handleKeydown"
        />
        <button
          type="button"
          class="admin-send-btn"
          :disabled="chat.isLoading.value || !inputText.trim()"
          @click="handleSend"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
          </svg>
        </button>
      </div>
    </section>
  </main>
</template>

<style scoped>
.admin-shell {
  min-height: 100vh;
  padding: 32px 20px;
  background:
    radial-gradient(circle at top, rgba(99, 102, 241, 0.18), transparent 40%),
    linear-gradient(180deg, #0f172a 0%, #111827 100%);
}

.admin-panel {
  width: min(880px, 100%);
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  height: calc(100vh - 64px);
  min-height: 480px;
  padding: 28px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 28px;
  /* Near-opaque instead of translucent + backdrop-filter: blurring the
     backdrop on every scrolled frame makes the message list stutter. */
  background: rgba(15, 23, 42, 0.94);
  box-shadow: 0 24px 80px rgba(15, 23, 42, 0.45);
  color: #f8fafc;
}

.admin-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding-bottom: 20px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.eyebrow {
  margin-bottom: 8px;
  font-size: 0.85rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #818cf8;
}

h1 {
  margin: 0;
  font-size: 1.6rem;
  line-height: 1.1;
}

.subtitle {
  margin-top: 8px;
  color: rgba(226, 232, 240, 0.78);
}

.header-actions {
  display: flex;
  gap: 10px;
  flex-shrink: 0;
}

.ghost-button {
  padding: 10px 16px;
  border: 0;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.08);
  color: #e2e8f0;
  font-size: 0.85rem;
  font-weight: 700;
  text-decoration: none;
  cursor: pointer;
  transition: transform 0.2s ease, background 0.2s ease;
}

.ghost-button:hover {
  transform: translateY(-1px);
  background: rgba(255, 255, 255, 0.14);
}

.admin-messages {
  flex: 1;
  overflow-y: auto;
  /* Keep wheel/touch scrolling inside the panel from chaining to the page. */
  overscroll-behavior: contain;
  padding: 20px 4px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  scrollbar-width: thin;
  scrollbar-color: rgba(129, 140, 248, 0.25) transparent;
}

.admin-empty {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 18px;
  text-align: center;
  color: rgba(148, 163, 184, 0.85);
}

.suggestion-row {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 8px;
  max-width: 560px;
}

.suggestion-btn {
  padding: 8px 14px;
  border: 1px solid rgba(129, 140, 248, 0.35);
  border-radius: 999px;
  background: rgba(99, 102, 241, 0.1);
  color: #a5b4fc;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.suggestion-btn:hover {
  background: rgba(99, 102, 241, 0.22);
  border-color: rgba(129, 140, 248, 0.6);
  color: #c7d2fe;
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
  gap: 10px;
}

.bubble {
  max-width: 82%;
  padding: 11px 15px;
  font-size: 0.9rem;
  line-height: 1.55;
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
.bubble--ai :deep(p:last-child) {
  margin: 0;
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
  font-size: 0.85rem;
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

.chart-stack {
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: 82%;
}

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

.admin-error {
  padding: 10px 16px;
  font-size: 0.82rem;
  color: #fca5a5;
  background: rgba(248, 113, 113, 0.1);
  border-radius: 12px;
}

.admin-input-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding-top: 14px;
  border-top: 1px solid rgba(129, 140, 248, 0.15);
}

.admin-input {
  flex: 1;
  padding: 11px 16px;
  border-radius: 14px;
  border: 1px solid rgba(148, 163, 184, 0.2);
  background: rgba(30, 41, 59, 0.8);
  color: #f1f5f9;
  font-size: 0.9rem;
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.admin-input::placeholder {
  color: rgba(148, 163, 184, 0.55);
}

.admin-input:focus {
  border-color: rgba(129, 140, 248, 0.6);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.admin-input:disabled {
  opacity: 0.5;
}

.admin-send-btn {
  width: 42px;
  height: 42px;
  border-radius: 14px;
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

.admin-send-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(99, 102, 241, 0.5);
}

.admin-send-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
  transform: none;
}

@media (max-width: 640px) {
  .admin-shell {
    padding: 20px 14px;
  }

  .admin-panel {
    padding: 20px;
    height: calc(100vh - 40px);
  }

  .admin-header {
    flex-direction: column;
  }

  .bubble,
  .chart-stack {
    max-width: 100%;
    width: 100%;
  }
}
</style>
