<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue'
import { useChat } from '../services/chat'
import { renderMarkdown } from '../utils/markdown'

const chat = useChat()
const messageList = ref<HTMLElement | null>(null)
const inputText = ref('')

onMounted(() => {
  chat.initFromStorage()
})

async function scrollToBottom() {
  await nextTick()
  if (messageList.value) {
    messageList.value.scrollTop = messageList.value.scrollHeight
  }
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

async function quickOrder(productName: string) {
  await chat.sendMessage(`I'd like to order 1 ${productName}`)
}
</script>

<template>
  <div v-show="chat.isOpen.value" class="chat-popup">
    <!-- Header -->
    <header class="chat-header">
      <div class="chat-header-info">
        <span class="chat-status-dot" />
        <span class="chat-header-title">Shopping Assistant</span>
      </div>
      <button class="chat-close-btn" aria-label="Close chat" @click="chat.toggleChat()">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
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

          <div v-if="msg.products?.length && !chat.isLoading.value" class="quick-order-row">
            <button
              v-for="product in msg.products"
              :key="product.id"
              class="quick-order-btn"
              @click="quickOrder(product.name)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
              Order {{ product.name }}
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
  background: rgba(15, 23, 42, 0.92);
  backdrop-filter: blur(20px);
  box-shadow:
    0 0 0 1px rgba(99, 102, 241, 0.1),
    0 24px 64px rgba(15, 23, 42, 0.6),
    0 8px 24px rgba(99, 102, 241, 0.15);
  overflow: hidden;
  color: #f1f5f9;
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

.chat-close-btn {
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

.chat-close-btn:hover {
  background: rgba(255, 255, 255, 0.13);
  color: #f1f5f9;
}

/* Messages */
.chat-messages {
  flex: 1;
  overflow-y: auto;
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
