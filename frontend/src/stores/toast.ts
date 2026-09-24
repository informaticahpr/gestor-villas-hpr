import { defineStore } from 'pinia'
import { ref } from 'vue'

export type TipoToast = 'success' | 'error' | 'warning' | 'info'

export interface Toast {
  id: number
  tipo: TipoToast
  mensaje: string
}

let contador = 0

export const useToastStore = defineStore('toast', () => {
  const toasts = ref<Toast[]>([])

  function mostrar(tipo: TipoToast, mensaje: string, duracionMs = 4500) {
    const id = ++contador
    toasts.value.push({ id, tipo, mensaje })
    setTimeout(() => cerrar(id), duracionMs)
  }

  function cerrar(id: number) {
    toasts.value = toasts.value.filter((t) => t.id !== id)
  }

  const success = (mensaje: string) => mostrar('success', mensaje)
  const error = (mensaje: string) => mostrar('error', mensaje, 6500)
  const warning = (mensaje: string) => mostrar('warning', mensaje, 5500)
  const info = (mensaje: string) => mostrar('info', mensaje)

  return { toasts, mostrar, cerrar, success, error, warning, info }
})
