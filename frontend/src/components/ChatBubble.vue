<script setup lang="ts">
import { useChat } from '../services/chat'

const chat = useChat()
</script>

<template>
  <div class="chat-launcher">
    <span class="chat-tooltip">Shopping Assistant</span>
    <button
      class="chat-bubble"
      :class="{ open: chat.isOpen.value }"
      aria-label="Open shopping assistant"
      @click="chat.toggleChat()"
    >
      <span class="pulse-ring" />
      <!-- Chat icon -->
      <svg
        v-if="!chat.isOpen.value"
        xmlns="http://www.w3.org/2000/svg"
        class="bubble-icon"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        stroke-width="2"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"
        />
      </svg>
      <!-- Close icon -->
      <svg
        v-else
        xmlns="http://www.w3.org/2000/svg"
        class="bubble-icon"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        stroke-width="2.5"
      >
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>
  </div>
</template>

<style scoped>
.chat-launcher {
  position: fixed;
  bottom: 1.5rem;
  right: 1.5rem;
  z-index: 50;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 10px;
}

.chat-tooltip {
  background: rgba(15, 23, 42, 0.9);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(129, 140, 248, 0.25);
  color: #e2e8f0;
  font-size: 0.82rem;
  font-weight: 600;
  letter-spacing: 0.03em;
  padding: 6px 12px;
  border-radius: 999px;
  box-shadow: 0 4px 16px rgba(99, 102, 241, 0.2);
  opacity: 0;
  transform: translateY(4px);
  transition: opacity 0.2s ease, transform 0.2s ease;
  pointer-events: none;
  white-space: nowrap;
}

.chat-launcher:hover .chat-tooltip {
  opacity: 1;
  transform: translateY(0);
}

.chat-bubble {
  position: relative;
  width: 60px;
  height: 60px;
  border-radius: 50%;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  box-shadow:
    0 0 0 0 rgba(99, 102, 241, 0.4),
    0 8px 32px rgba(99, 102, 241, 0.45),
    0 2px 8px rgba(0, 0, 0, 0.3);
  transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.25s ease;
}

.chat-bubble:hover {
  transform: scale(1.08) translateY(-2px);
  box-shadow:
    0 0 0 0 rgba(99, 102, 241, 0.4),
    0 12px 40px rgba(99, 102, 241, 0.55),
    0 4px 12px rgba(0, 0, 0, 0.3);
}

.chat-bubble:active {
  transform: scale(0.95);
}

.chat-bubble.open {
  background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
}

.bubble-icon {
  width: 26px;
  height: 26px;
  color: white;
  transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.15s ease;
}

.pulse-ring {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  opacity: 0.5;
  animation: pulse 2.5s cubic-bezier(0.4, 0, 0.6, 1) infinite;
  pointer-events: none;
}

.chat-bubble.open .pulse-ring {
  animation: none;
  opacity: 0;
}

@keyframes pulse {
  0%, 100% {
    transform: scale(1);
    opacity: 0.45;
  }
  50% {
    transform: scale(1.55);
    opacity: 0;
  }
}
</style>
