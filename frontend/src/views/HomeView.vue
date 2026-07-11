<script setup lang="ts">
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuth } from '../services/auth'
import { fetchCategories, fetchProducts, getErrorMessage, placeOrder, type Category, type Product } from '../services/dashboard'

const auth = useAuth()
const user = auth.user
const router = useRouter()

const products = ref<Product[]>([])
const categories = ref<Category[]>([])
const activeCategory = ref('')
const selectedQuantities = ref<Record<number, number>>({})
const loadingProducts = ref(false)
const placingOrder = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const selectedItems = computed(() =>
  products.value
    .map((product) => {
      const quantity = selectedQuantities.value[product.id] ?? 0

      return {
        ...product,
        quantity,
      }
    })
    .filter((product) => product.quantity > 0),
)

const selectedCount = computed(() =>
  selectedItems.value.reduce((total, product) => total + product.quantity, 0),
)

const selectedTotal = computed(() =>
  selectedItems.value.reduce((total, product) => total + product.price * product.quantity, 0),
)

const canPlaceOrder = computed(() =>
  !loadingProducts.value
  && !placingOrder.value
  && selectedItems.value.length > 0,
)

onMounted(async () => {
  await Promise.all([loadProducts(), loadCategories()])
})

async function handleLogout() {
  await auth.logout()
  await router.push('/login')
}

async function loadProducts() {
  loadingProducts.value = true
  errorMessage.value = ''

  try {
    products.value = await fetchProducts(activeCategory.value || undefined)
    selectedQuantities.value = {}
  } catch (error) {
    if (axios.isAxiosError(error) && error.response?.status === 401) {
      auth.setUser(null)
      await router.replace({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } })
      return
    }

    errorMessage.value = getErrorMessage(error, 'Unable to load products right now.')
  } finally {
    loadingProducts.value = false
  }
}

async function loadCategories() {
  try {
    categories.value = await fetchCategories()
  } catch {
    categories.value = []
  }
}

async function selectCategory(slug: string) {
  if (activeCategory.value === slug || loadingProducts.value) return

  activeCategory.value = slug
  await loadProducts()
}

function updateQuantity(productId: number, quantity: number) {
  if (quantity <= 0) {
    delete selectedQuantities.value[productId]
    return
  }

  selectedQuantities.value[productId] = quantity
}

async function handlePlaceOrder() {
  if (!canPlaceOrder.value) return

  placingOrder.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const response = await placeOrder(
      selectedItems.value.map((product) => ({
        product_id: product.id,
        quantity: product.quantity,
      })),
    )

    successMessage.value = response.message || 'Order created successfully.'
    await loadProducts()
  } catch (error) {
    if (axios.isAxiosError(error) && error.response?.status === 401) {
      auth.setUser(null)
      await router.replace({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } })
      return
    }

    errorMessage.value = getErrorMessage(error, 'Unable to place the order right now.')
  } finally {
    placingOrder.value = false
  }
}

function formatCurrency(value: number) {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
  }).format(value)
}
</script>

<template>
  <main class="dashboard-shell">
    <section class="dashboard-panel">
      <header class="hero">
        <div>
          <p class="eyebrow">Ecom Store</p>
          <p class="subtitle">
           Select products, review totals, and submit the order in one view.
          </p>
        </div>

        <div class="hero-actions">
          <RouterLink v-if="auth.isAdmin.value" to="/admin" class="reports-link">Admin Assistant</RouterLink>
          <button type="button" class="logout-button" @click="handleLogout">Logout</button>
        </div>
      </header>

      <p v-if="auth.isLoading.value || !auth.isInitialized.value" class="status-message">
        Loading...
      </p>

      <template v-else>
        <div v-if="errorMessage" class="notice error">{{ errorMessage }}</div>
        <div v-if="successMessage" class="notice success">{{ successMessage }}</div>

        <section class="content-grid">
          <div class="catalog-card">
            <div class="section-heading">
              <div>
                <p class="section-label">Products</p>
                <h2>Available inventory</h2>
              </div>

              <button type="button" class="ghost-button" :disabled="loadingProducts" @click="loadProducts">
                {{ loadingProducts ? 'Refreshing...' : 'Refresh' }}
              </button>
            </div>

            <nav v-if="categories.length > 0" class="category-tabs" aria-label="Filter products by category">
              <button
                type="button"
                class="category-tab"
                :class="{ active: activeCategory === '' }"
                @click="selectCategory('')"
              >All</button>
              <button
                v-for="category in categories"
                :key="category.id"
                type="button"
                class="category-tab"
                :class="{ active: activeCategory === category.slug }"
                @click="selectCategory(category.slug)"
              >{{ category.name }}</button>
            </nav>

            <p v-if="loadingProducts" class="status-message">Loading products...</p>
            <p v-else-if="products.length === 0" class="status-message">No products are available.</p>

            <div v-else class="product-list">
              <article v-for="product in products" :key="product.id" class="product-row">
                <div class="product-copy">
                  <span v-if="product.category" class="category-label">{{ product.category.name }}</span>
                  <h3>{{ product.name }}</h3>
                  <p>{{ formatCurrency(product.price) }}</p>
                  <span class="stock-badge" :class="{ low: product.stock <= 3 }">
                    {{ product.stock }} in stock
                  </span>
                </div>

                <div class="quantity-control">
                  <span>Qty</span>
                  <div class="quantity-stepper">
                    <button
                      class="step-btn"
                      :disabled="(selectedQuantities[product.id] ?? 0) <= 0 || placingOrder"
                      @click="updateQuantity(product.id, (selectedQuantities[product.id] ?? 1) - 1)"
                    >−</button>
                    <input
                      :value="selectedQuantities[product.id] ?? 0"
                      type="number"
                      min="0"
                      :max="product.stock"
                      :disabled="product.stock === 0 || placingOrder"
                      @input="updateQuantity(product.id, Number(($event.target as HTMLInputElement).value))"
                    />
                    <button
                      class="step-btn"
                      :disabled="(selectedQuantities[product.id] ?? 0) >= product.stock || placingOrder"
                      @click="updateQuantity(product.id, (selectedQuantities[product.id] ?? 0) + 1)"
                    >+</button>
                  </div>
                </div>
              </article>
            </div>
          </div>

          <aside class="summary-card">
            <div class="section-heading compact">
              <div>
                <p class="section-label">Summary</p>
                <h2>Current order</h2>
              </div>
            </div>

            <p v-if="selectedItems.length === 0" class="status-message">
              Select at least one product to prepare an order.
            </p>

            <div v-else class="summary-list">
              <article v-for="item in selectedItems" :key="item.id" class="summary-item">
                <div>
                  <h3>{{ item.name }}</h3>
                  <p>{{ item.quantity }} × {{ formatCurrency(item.price) }}</p>
                </div>
                <strong>{{ formatCurrency(item.price * item.quantity) }}</strong>
              </article>
            </div>

            <dl class="totals">
              <div>
                <dt>Items</dt>
                <dd>{{ selectedCount }}</dd>
              </div>
              <div>
                <dt>Total</dt>
                <dd>{{ formatCurrency(selectedTotal) }}</dd>
              </div>
            </dl>

            <button type="button" class="primary-button" :disabled="!canPlaceOrder" @click="handlePlaceOrder">
              {{ placingOrder ? 'Placing order...' : 'Place Order' }}
            </button>
          </aside>
        </section>
      </template>
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
.section-heading,
.product-row,
.summary-item,
.totals div {
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

h1,
h2,
h3 {
  margin: 0;
}

h1 {
  font-size: 2rem;
  line-height: 1.1;
}

.subtitle {
  margin-top: 12px;
  max-width: 640px;
  color: rgba(226, 232, 240, 0.78);
}

.content-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.8fr) minmax(300px, 1fr);
  gap: 24px;
  margin-top: 28px;
}

.catalog-card,
.summary-card {
  padding: 24px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 24px;
  background: rgba(15, 23, 42, 0.72);
}

.section-heading {
  margin-bottom: 18px;
}

.section-heading.compact {
  margin-bottom: 12px;
}

.section-label {
  font-size: 0.82rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #818cf8;
}

.category-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 18px;
}

.category-tab {
  padding: 8px 14px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.06);
  color: rgba(226, 232, 240, 0.78);
  font-size: 0.88rem;
  font-weight: 600;
}

.category-tab:hover:not(:disabled) {
  background: rgba(129, 140, 248, 0.15);
  color: #a5b4fc;
  transform: none;
}

.category-tab.active {
  background: rgba(129, 140, 248, 0.22);
  color: #c7d2fe;
  box-shadow: inset 0 0 0 1px rgba(129, 140, 248, 0.5);
}

.product-list {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
}

.summary-list {
  display: grid;
  gap: 14px;
}

.product-row {
  display: grid;
  align-content: space-between;
  gap: 18px;
  padding: 18px;
  border-radius: 18px;
  background: rgba(30, 41, 59, 0.88);
}

.summary-item {
  padding: 18px;
  border-radius: 18px;
  background: rgba(30, 41, 59, 0.88);
}

.product-copy {
  display: grid;
  gap: 6px;
}

.product-copy p,
.summary-item p,
.status-message,
.totals dt {
  color: rgba(226, 232, 240, 0.72);
}

.category-label {
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #818cf8;
}

.stock-badge {
  display: inline-flex;
  width: fit-content;
  padding: 4px 10px;
  border-radius: 999px;
  background: rgba(34, 197, 94, 0.14);
  color: #86efac;
  font-size: 0.85rem;
  font-weight: 700;
}

.stock-badge.low {
  background: rgba(248, 113, 113, 0.14);
  color: #fca5a5;
}

.quantity-control {
  display: grid;
  gap: 8px;
  font-size: 0.9rem;
  color: #e2e8f0;
}

.quantity-stepper {
  display: grid;
  grid-template-columns: 36px 1fr 36px;
  border: 1px solid rgba(148, 163, 184, 0.28);
  border-radius: 14px;
  overflow: hidden;
  background: rgba(15, 23, 42, 0.9);
}

.quantity-stepper input {
  width: 100%;
  min-width: 0;
  padding: 10px 0;
  border: none;
  border-radius: 0;
  background: transparent;
  color: #f8fafc;
  text-align: center;
  font-size: 0.95rem;
  font-weight: 600;
  -moz-appearance: textfield;
}

.quantity-stepper input::-webkit-inner-spin-button,
.quantity-stepper input::-webkit-outer-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

.quantity-stepper input:focus {
  outline: none;
  box-shadow: inset 0 0 0 2px #818cf8;
}

.step-btn {
  height: 40px;
  border: none;
  border-radius: 0;
  padding: 0;
  background: transparent;
  color: #e2e8f0;
  font-size: 1.15rem;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.15s ease, color 0.15s ease;
}

.step-btn:hover:not(:disabled) {
  background: rgba(129, 140, 248, 0.15);
  color: #818cf8;
}

.step-btn:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.totals {
  display: grid;
  gap: 12px;
  margin: 22px 0;
}

.totals div {
  padding-top: 12px;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.totals dt,
.totals dd {
  margin: 0;
}

.totals dd {
  font-weight: 700;
  color: #f8fafc;
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

.notice.success {
  background: rgba(22, 163, 74, 0.1);
  color: #166534;
}

.status-message {
  font-size: 0.96rem;
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

.logout-button {
  background: rgba(255, 255, 255, 0.08);
  color: #f8fafc;
}

.hero-actions {
  display: flex;
  align-items: center;
  gap: 10px;
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

.ghost-button {
  background: rgba(255, 255, 255, 0.08);
  color: #e2e8f0;
}

.primary-button {
  width: 100%;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: white;
}

button:disabled {
  opacity: 0.58;
  cursor: not-allowed;
  transform: none;
}

@media (max-width: 900px) {
  .dashboard-shell {
    padding: 20px 14px;
  }

  .dashboard-panel {
    padding: 20px;
  }

  .content-grid {
    grid-template-columns: 1fr;
  }

  .hero,
  .section-heading,
  .summary-item,
  .totals div {
    align-items: flex-start;
  }

  .hero,
  .section-heading {
    flex-direction: column;
  }

  .product-list {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .product-list {
    grid-template-columns: 1fr;
  }
}
</style>
