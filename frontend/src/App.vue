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
