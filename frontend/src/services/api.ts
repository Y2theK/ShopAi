import axios from 'axios'

export const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000/api/v1'

export function getBackendUrl() {
  const backendUrl = import.meta.env.VITE_BACKEND_URL

  if (backendUrl) {
    return backendUrl
  }

  return new URL(apiBaseUrl, window.location.origin).origin
}

const api = axios.create({
  baseURL: apiBaseUrl,
  headers: {
    'Content-Type': 'application/json',
  },
  withCredentials: true,
  withXSRFToken: true,
})

export default api
