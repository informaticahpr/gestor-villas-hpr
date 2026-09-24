import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * Contador simple que se incrementa cada vez que se crea/edita una villa o se
 * aplica un cargo/abono. Las vistas que muestran listas o saldos lo observan
 * para refrescarse solas, incluso si ya estaban montadas (ej. abrir "Crear
 * Villa" desde el menú mientras ya estás parado en Buscar Villa).
 */
export const useDataStore = defineStore('data', () => {
  const version = ref(0)

  function tocar() {
    version.value++
  }

  return { version, tocar }
})
