import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface OpcionesConfirmar {
  titulo: string
  mensaje: string
  textoConfirmar?: string
  /** Estilo rojo para acciones destructivas (eliminar). */
  peligro?: boolean
}

export interface OpcionesPassword {
  titulo: string
  mensaje: string
}

export type DialogoActivo =
  | ({ tipo: 'confirmar' } & OpcionesConfirmar & { resolver: (ok: boolean) => void })
  | ({ tipo: 'password' } & OpcionesPassword & { resolver: (password: string | null) => void })

/**
 * Reemplaza los confirm()/prompt() nativos del navegador por modales propios.
 * Se usa con await: `if (!(await dialog.confirmar({...}))) return`.
 */
export const useDialogStore = defineStore('dialog', () => {
  const activo = ref<DialogoActivo | null>(null)

  function cancelarActivo() {
    const d = activo.value
    if (!d) return
    if (d.tipo === 'confirmar') d.resolver(false)
    else d.resolver(null)
    activo.value = null
  }

  function confirmar(opciones: OpcionesConfirmar): Promise<boolean> {
    cancelarActivo()
    return new Promise((resolver) => {
      activo.value = { tipo: 'confirmar', ...opciones, resolver }
    })
  }

  function pedirPassword(opciones: OpcionesPassword): Promise<string | null> {
    cancelarActivo()
    return new Promise((resolver) => {
      activo.value = { tipo: 'password', ...opciones, resolver }
    })
  }

  function cerrar(valor: boolean | string | null) {
    const d = activo.value
    if (!d) return
    activo.value = null
    if (d.tipo === 'confirmar') d.resolver(valor === true)
    else d.resolver(typeof valor === 'string' ? valor : null)
  }

  return { activo, confirmar, pedirPassword, cerrar }
})
