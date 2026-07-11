import { computed, reactive } from 'vue'
import axios from 'axios'
import api, { getBackendUrl } from './api'

export type AuthUser = {
  id: number
  name: string
  email: string
  is_admin: boolean
}

type AuthState = {
  user: AuthUser | null
  initialized: boolean
  loading: boolean
}

const state = reactive<AuthState>({
  user: null,
  initialized: false,
  loading: false,
})

const backendUrl = getBackendUrl()
let bootstrapPromise: Promise<AuthUser | null> | null = null

function isAuthUser(value: unknown): value is AuthUser {
  if (!value || typeof value !== 'object') {
    return false
  }

  const candidate = value as Partial<AuthUser>

  return (
    typeof candidate.id === 'number'
    && typeof candidate.name === 'string'
    && typeof candidate.email === 'string'
    && typeof candidate.is_admin === 'boolean'
  )
}

function extractUser(payload: unknown): AuthUser | null {
  const response = payload as {
    data?: {
      data?: unknown
      user?: unknown
    }
  }

  if (response.data && typeof response.data === 'object') {
    const nestedResponse = response.data as {
      data?: unknown
      user?: unknown
    }

    if (isAuthUser(nestedResponse.user)) {
      return nestedResponse.user
    }

    if (isAuthUser(nestedResponse.data)) {
      return nestedResponse.data
    }
  }

  if (response.data?.data && typeof response.data.data === 'object') {
    const nested = response.data.data as { user?: unknown }

    if (isAuthUser(nested.user)) {
      return nested.user
    }

    if (isAuthUser(response.data.data)) {
      return response.data.data
    }
  }

  if (isAuthUser(response.data?.user)) {
    return response.data.user
  }

  if (isAuthUser(payload)) {
    return payload
  }

  return null
}

function isExpectedGuestResponse(error: unknown) {
  return axios.isAxiosError(error) && [401, 419].includes(error.response?.status ?? 0)
}

function setUser(user: AuthUser | null) {
  state.user = user
  state.initialized = true
}

export function useAuth() {
  const user = computed(() => state.user)
  const isAuthenticated = computed(() => state.user !== null)
  const isAdmin = computed(() => state.user?.is_admin === true)
  const isLoading = computed(() => state.loading)
  const isInitialized = computed(() => state.initialized)

  async function ensureCsrfCookie() {
    await axios.get(`${backendUrl}/sanctum/csrf-cookie`, {
      withCredentials: true,
    })
  }

  async function fetchUser() {
    try {
      const response = await api.get('/auth/me')
      const user = extractUser(response.data.data)

      if (!user) {
        throw new Error('Auth endpoint did not return a valid user payload.')
      }

      setUser(user)
      return user
    } catch (error) {
      if (!isExpectedGuestResponse(error)) {
        throw error
      }

      setUser(null)
      return null
    }
  }

  async function bootstrap() {
    if (state.initialized) {
      return state.user
    }

    if (bootstrapPromise) {
      return bootstrapPromise
    }

    bootstrapPromise = (async () => {
      state.loading = true

      try {
        // Ensure CSRF cookie is present (helps browser send session cookie on refresh)
        try {
          await ensureCsrfCookie()
        } catch (error) {
          if (!isExpectedGuestResponse(error)) {
            throw error
          }
        }

        return await fetchUser()
      } finally {
        state.loading = false
        bootstrapPromise = null
      }
    })()

    return bootstrapPromise
  }

  async function login(email: string, password: string) {
    state.loading = true

    try {
      await ensureCsrfCookie()
      const response = await api.post('/auth/login', { email, password })
      const user = extractUser(response.data.data)

      if (!user) {
        throw new Error('Login succeeded but no valid user payload was returned.')
      }

      setUser(user)
      return user
    } finally {
      state.loading = false
    }
  }

  async function logout() {
    state.loading = true

    try {
      await api.post('/auth/logout')
      setUser(null)
    } catch (error) {
      if (axios.isAxiosError(error) && error.response?.status === 401) {
        setUser(null)
        return
      }

      throw error
    } finally {
      state.loading = false
    }
  }

  return {
    user,
    isAuthenticated,
    isAdmin,
    isLoading,
    isInitialized,
    setUser,
    bootstrap,
    fetchUser,
    login,
    logout,
  }
}
