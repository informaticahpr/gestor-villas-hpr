import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useUiStore = defineStore('ui', () => {
  const mostrarCrearVilla = ref(false)
  const mostrarMovimiento = ref(false)

  return { mostrarCrearVilla, mostrarMovimiento }
})
