import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000',
  withCredentials: true,
  withXSRFToken: true,
})

export function fetchCsrfCookie() {
  return api.get('/sanctum/csrf-cookie')
}

export default api
