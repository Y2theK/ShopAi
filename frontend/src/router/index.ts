import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from '../services/auth'
import LoginView from '../views/LoginView.vue'
import HomeView from '../views/HomeView.vue'

const router = createRouter({
  history: createWebHistory('/'),
  routes: [
    {
      path: '/',
      name: 'dashboard',
      component: HomeView,
      meta: { requiresAuth: true },
    },
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { guestOnly: true },
    },
    {
      path: '/orders',
      name: 'orders',
      component: () => import('../views/OrdersView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/admin',
      name: 'admin',
      component: () => import('../views/AdminAssistantView.vue'),
      meta: { requiresAuth: true, adminOnly: true },
    },
  ],
})

function requiresAuth(path: typeof router.currentRoute.value) {
  return path.matched.some((record) => record.meta.requiresAuth)
}

function isGuestOnly(path: typeof router.currentRoute.value) {
  return path.matched.some((record) => record.meta.guestOnly)
}

function isAdminOnly(path: typeof router.currentRoute.value) {
  return path.matched.some((record) => record.meta.adminOnly)
}

router.beforeEach(async (to) => {
  const auth = useAuth()

  try {
    await auth.bootstrap()
  } catch {
    auth.setUser(null)
  }

  if (requiresAuth(to) && !auth.isAuthenticated.value) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (isGuestOnly(to) && auth.isAuthenticated.value) {
    return { name: 'dashboard' }
  }

  if (isAdminOnly(to) && !auth.isAdmin.value) {
    return { name: 'dashboard' }
  }

  return true
})

export default router
