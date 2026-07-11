import axios from 'axios'
import api from './api'

export type Category = {
  id: number
  name: string
  slug: string
}

export type Product = {
  id: number
  name: string
  price: number
  stock: number
  category: Category | null
}

export type OrderItem = {
  product_id: number
  quantity: number
  price: number
  product: {
    id: number
    name: string
    price: number
  } | null
}

export type Order = {
  id: number
  user_id: number
  order_code: string
  status: string
  total_price: number
  phone: string | null
  secondary_phone: string | null
  address: string | null
  city: string | null
  state: string | null
  country: string | null
  created_at: string | null
  items: OrderItem[]
}

type ApiEnvelope<T> = {
  message?: string
  data?: T
}

type ProductsPayload = {
  data?: Product[]
}

type OrdersPayload = {
  data?: Order[]
}

export async function fetchProducts(category?: string) {
  const response = await api.get<ApiEnvelope<ProductsPayload>>('/products', {
    params: category ? { category } : undefined,
  })
  return response.data.data?.data ?? []
}

export async function fetchCategories() {
  const response = await api.get<ApiEnvelope<Category[]>>('/categories')
  return response.data.data ?? []
}

export async function fetchOrders() {
  const response = await api.get<ApiEnvelope<OrdersPayload>>('/orders')
  return response.data.data?.data ?? []
}

export async function placeOrder(items: Array<{ product_id: number, quantity: number }>) {
  const response = await api.post<ApiEnvelope<Order>>('/orders', { items })
  return response.data
}

export function getErrorMessage(error: unknown, fallback: string) {
  if (axios.isAxiosError(error)) {
    const message = error.response?.data?.message

    if (typeof message === 'string' && message.trim() !== '') {
      return message
    }
  }

  if (error instanceof Error && error.message.trim() !== '') {
    return error.message
  }

  return fallback
}
