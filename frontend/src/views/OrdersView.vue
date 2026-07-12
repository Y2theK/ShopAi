<script setup lang="ts">
import axios from 'axios'
import { onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuth } from '../services/auth'
import { fetchOrders, getErrorMessage, type Order } from '../services/dashboard'

const auth = useAuth()
const router = useRouter()

const orders = ref<Order[]>([])
const loadingOrders = ref(false)
const errorMessage = ref('')

onMounted(loadOrders)

async function handleLogout() {
  await auth.logout()
  await router.push('/login')
}

async function loadOrders() {
  loadingOrders.value = true
  errorMessage.value = ''

  try {
    orders.value = await fetchOrders()
  } catch (error) {
    if (axios.isAxiosError(error) && error.response?.status === 401) {
      auth.setUser(null)
      await router.replace({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } })
      return
    }

    errorMessage.value = getErrorMessage(error, 'Unable to load your orders right now.')
  } finally {
    loadingOrders.value = false
  }
}

function itemCount(order: Order) {
  return order.items.reduce((total, item) => total + item.quantity, 0)
}

function itemSummary(order: Order) {
  return order.items
    .map((item) => `${item.product?.name ?? 'Unavailable product'} ×${item.quantity}`)
    .join(', ')
}

function deliveryRegion(order: Order) {
  return [order.city, order.state, order.country].filter(Boolean).join(', ')
}

function deliveryPhones(order: Order) {
  return [order.phone, order.secondary_phone].filter(Boolean).join(' · ')
}

function formatCurrency(value: number) {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
  }).format(value)
}

function formatDate(value: string | null) {
  if (!value) return '—'

  return new Intl.DateTimeFormat('en-US', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}
</script>

<template>
  <main class="dashboard-shell">
    <section class="dashboard-panel">
      <header class="hero">
        <div>
          <p class="eyebrow">ShopAi Store</p>
          <p class="subtitle">Review your past orders and track their status.</p>
        </div>

        <div class="hero-actions">
          <RouterLink to="/" class="reports-link">Shop</RouterLink>
          <RouterLink v-if="auth.isAdmin.value" to="/admin" class="reports-link">Admin Assistant</RouterLink>
          <button type="button" class="logout-button" @click="handleLogout">Logout</button>
        </div>
      </header>

      <div v-if="errorMessage" class="notice error">{{ errorMessage }}</div>

      <div class="orders-card">
        <div class="section-heading">
          <div>
            <p class="section-label">Orders</p>
            <h2>My orders</h2>
          </div>

          <button type="button" class="ghost-button" :disabled="loadingOrders" @click="loadOrders">
            {{ loadingOrders ? 'Refreshing...' : 'Refresh' }}
          </button>
        </div>

        <p v-if="loadingOrders" class="status-message">Loading orders...</p>
        <p v-else-if="orders.length === 0" class="status-message">
          You haven't placed any orders yet.
        </p>

        <div v-else class="table-wrap">
          <table class="orders-table">
            <thead>
              <tr>
                <th>Order Code</th>
                <th>Placed</th>
                <th>Items</th>
                <th>Delivery</th>
                <th class="numeric">Total</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="order in orders" :key="order.id">
                <td><code class="order-code">{{ order.order_code }}</code></td>
                <td>{{ formatDate(order.created_at) }}</td>
                <td>
                  <span class="item-summary" :title="itemSummary(order)">
                    {{ itemCount(order) }} item{{ itemCount(order) === 1 ? '' : 's' }}
                    <small>{{ itemSummary(order) }}</small>
                  </span>
                </td>
                <td>
                  <span v-if="order.address" class="delivery-summary">
                    {{ order.address }}
                    <small>{{ deliveryRegion(order) }}</small>
                    <small v-if="deliveryPhones(order)">{{ deliveryPhones(order) }}</small>
                  </span>
                  <span v-else class="delivery-empty">—</span>
                </td>
                <td class="numeric">{{ formatCurrency(order.total_price) }}</td>
                <td>
                  <span class="status-badge" :class="order.status">{{ order.status }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </main>
</template>

<style scoped>
.dashboard-shell {
  min-height: 100vh;
  padding: 32px 20px;
  background:
    radial-gradient(circle at top, rgba(99, 102, 241, 0.18), transparent 40%),
    linear-gradient(180deg, #0f172a 0%, #111827 100%);
}

.dashboard-panel {
  width: min(1180px, 100%);
  margin: 0 auto;
  padding: 28px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 28px;
  background: rgba(15, 23, 42, 0.78);
  backdrop-filter: blur(18px);
  box-shadow: 0 24px 80px rgba(15, 23, 42, 0.45);
  color: #f8fafc;
}

.hero,
.section-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.eyebrow {
  margin-bottom: 8px;
  font-size: 0.85rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #818cf8;
}

h2 {
  margin: 0;
}

.subtitle {
  margin-top: 12px;
  max-width: 640px;
  color: rgba(226, 232, 240, 0.78);
}

.hero-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.orders-card {
  margin-top: 28px;
  padding: 24px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 24px;
  background: rgba(15, 23, 42, 0.72);
}

.section-heading {
  margin-bottom: 18px;
}

.section-label {
  font-size: 0.82rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #818cf8;
}

.table-wrap {
  overflow-x: auto;
}

.orders-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.95rem;
}

.orders-table th,
.orders-table td {
  padding: 14px 12px;
  text-align: left;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  vertical-align: top;
}

.orders-table th {
  font-size: 0.78rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: rgba(226, 232, 240, 0.6);
}

.orders-table td.numeric,
.orders-table th.numeric {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.order-code {
  padding: 4px 8px;
  border-radius: 8px;
  background: rgba(129, 140, 248, 0.14);
  color: #c7d2fe;
  font-size: 0.88rem;
  font-weight: 600;
}

.item-summary {
  display: grid;
  gap: 4px;
}

.item-summary small {
  color: rgba(226, 232, 240, 0.6);
  font-size: 0.82rem;
}

.delivery-summary {
  display: grid;
  gap: 4px;
  max-width: 260px;
}

.delivery-summary small {
  color: rgba(226, 232, 240, 0.6);
  font-size: 0.82rem;
}

.delivery-empty {
  color: rgba(226, 232, 240, 0.4);
}

.status-badge {
  display: inline-flex;
  padding: 4px 12px;
  border-radius: 999px;
  font-size: 0.82rem;
  font-weight: 700;
  text-transform: capitalize;
  background: rgba(148, 163, 184, 0.16);
  color: #cbd5e1;
}

.status-badge.pending {
  background: rgba(250, 204, 21, 0.14);
  color: #fde047;
}

.status-badge.processing {
  background: rgba(129, 140, 248, 0.16);
  color: #a5b4fc;
}

.status-badge.shipped {
  background: rgba(56, 189, 248, 0.14);
  color: #7dd3fc;
}

.status-badge.delivered {
  background: rgba(34, 197, 94, 0.14);
  color: #86efac;
}

.status-badge.cancelled {
  background: rgba(248, 113, 113, 0.14);
  color: #fca5a5;
}

.notice {
  margin-top: 20px;
  padding: 14px 16px;
  border-radius: 16px;
  font-size: 0.95rem;
}

.notice.error {
  background: rgba(192, 38, 64, 0.1);
  color: #9f1239;
}

.status-message {
  font-size: 0.96rem;
  color: rgba(226, 232, 240, 0.72);
}

button {
  border: 0;
  border-radius: 16px;
  padding: 13px 18px;
  font-weight: 700;
  cursor: pointer;
  transition: transform 0.2s ease, opacity 0.2s ease, background 0.2s ease;
}

button:hover:not(:disabled) {
  transform: translateY(-1px);
}

button:disabled {
  opacity: 0.58;
  cursor: not-allowed;
  transform: none;
}

.logout-button {
  background: rgba(255, 255, 255, 0.08);
  color: #f8fafc;
}

.ghost-button {
  background: rgba(255, 255, 255, 0.08);
  color: #e2e8f0;
}

.reports-link {
  padding: 13px 18px;
  border-radius: 16px;
  background: rgba(129, 140, 248, 0.14);
  color: #a5b4fc;
  font-weight: 700;
  text-decoration: none;
  transition: transform 0.2s ease, background 0.2s ease;
}

.reports-link:hover {
  transform: translateY(-1px);
  background: rgba(129, 140, 248, 0.24);
}

@media (max-width: 900px) {
  .dashboard-shell {
    padding: 20px 14px;
  }

  .dashboard-panel {
    padding: 20px;
  }

  .hero,
  .section-heading {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
