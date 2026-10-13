import { defineStore } from 'pinia'
import { ref } from 'vue'
import api, { fetchCsrfCookie } from '../lib/api'

/** Claves de Configuración → Permisos (ver RolPermiso::CATALOGO en el backend). */
export type Permiso =
  | 'villas.crear'
  | 'villas.editar'
  | 'movimientos.aplicar'
  | 'movimientos.anular'
  | 'inicio.cobranza'
  | 'inicio.completo'
  | 'reportes.generales'
  | 'config.conceptos'
  | 'config.cuotas'
  | 'config.alquiladas'
  | 'config.formas_pago'
  | 'config.bitacora'
  | 'usuarios.ver'
  | 'usuarios.gestionar'

/** Pestañas de Configuración que dependen de un permiso (Permisos es solo del Administrador). */
const PERMISOS_DE_CONFIGURACION: Permiso[] = [
  'usuarios.ver',
  'usuarios.gestionar',
  'config.conceptos',
  'config.cuotas',
  'config.alquiladas',
  'config.formas_pago',
  'config.bitacora',
]

export interface AuthUser {
  id: number
  name: string
  email: string
  rol: string | null
  /** Lo que su cargo puede hacer; el Administrador los trae todos. El servidor igual revisa cada acción. */
  permisos: Permiso[]
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const cargando = ref(false)
  const listo = ref(false)

  function esAdmin(): boolean {
    return user.value?.rol === 'Admin'
  }

  function puede(permiso: Permiso): boolean {
    return user.value?.permisos?.includes(permiso) ?? false
  }

  function puedeVerConfiguracion(): boolean {
    return esAdmin() || PERMISOS_DE_CONFIGURACION.some(puede)
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

  return { user, cargando, listo, esAdmin, puede, puedeVerConfiguracion, fetchUser, login, logout }
})
