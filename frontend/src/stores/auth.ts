import { defineStore } from 'pinia'
import { ref } from 'vue'
import api, { fetchCsrfCookie } from '../lib/api'

export interface AuthUser {
  id: number
  name: string
  email: string
  rol: string | null
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const cargando = ref(false)
  const listo = ref(false)

  function esDirectorOAdmin(): boolean {
    return user.value?.rol === 'Director' || user.value?.rol === 'Admin'
  }

  function esAdmin(): boolean {
    return user.value?.rol === 'Admin'
  }

  function esSupervisor(): boolean {
    return user.value?.rol === 'Supervisor'
  }

  /** Solo lectura: villas, estado de cuenta por villa y reimpresion. No crea, carga, anula ni cobra nada. */
  function esConsultor(): boolean {
    return user.value?.rol === 'Consultor'
  }

  /** Configuración completa: Director y Admin. El Supervisor solo ve "Villas alquiladas a producción". */
  function puedeVerConfiguracion(): boolean {
    return esDirectorOAdmin() || esSupervisor()
  }

  async function fetchUser(): Promise<void> {
    try {
      const { data } = await api.get('/api/user')
      user.value = data.user
    } catch {
      user.value = null
    } finally {
      listo.value = true
    }
  }

  async function login(usuario: string, password: string): Promise<void> {
    cargando.value = true
    try {
      await fetchCsrfCookie()
      const { data } = await api.post('/api/login', { usuario, password })
      user.value = data.user
    } finally {
      cargando.value = false
    }
  }

  async function logout(): Promise<void> {
    await api.post('/api/logout')
    user.value = null
  }

  return { user, cargando, listo, esDirectorOAdmin, esAdmin, esSupervisor, esConsultor, puedeVerConfiguracion, fetchUser, login, logout }
})
