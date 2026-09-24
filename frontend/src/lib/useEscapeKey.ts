import { onMounted, onBeforeUnmount } from 'vue'

/**
 * Ejecuta `alPresionar` cuando se pulsa Escape mientras el componente esta montado.
 * Escucha en `window`, asi funciona aunque el foco no este dentro del modal.
 *
 * Con `prioritario: true` el listener corre antes que los demas (fase de captura) y,
 * si `alPresionar` devuelve `true`, consume el evento para que no cierre tambien
 * a los modales que esten debajo.
 */
export function useEscapeKey(alPresionar: () => boolean | void, opciones: { prioritario?: boolean } = {}) {
  const prioritario = opciones.prioritario ?? false

  function onKeydown(e: KeyboardEvent) {
    if (e.key !== 'Escape' || e.defaultPrevented) return
    const consumido = alPresionar()
    if (prioritario && consumido) {
      e.preventDefault()
      e.stopImmediatePropagation()
    }
  }

  onMounted(() => window.addEventListener('keydown', onKeydown, prioritario))
  onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown, prioritario))
}
