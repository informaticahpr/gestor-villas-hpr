<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '../lib/api'
import { formatearMonto } from '../lib/format'
import type { VillaResumen } from '../types'

const props = withDefaults(
  defineProps<{
    conOpcionTodas?: boolean
    placeholder?: string
  }>(),
  {
    conOpcionTodas: false,
    placeholder: 'Buscar villa por # o propietario...',
  },
)

/** CLV_CLIE de la villa elegida, o '' cuando no hay ninguna (= "Todas las villas"). */
const modelo = defineModel<string>({ default: '' })

const modo = ref<'todas' | 'una'>(props.conOpcionTodas && !modelo.value ? 'todas' : 'una')
const villaSeleccionada = ref<VillaResumen | null>(null)
const busqueda = ref('')
const resultados = ref<VillaResumen[]>([])
let temporizador: ReturnType<typeof setTimeout> | undefined

function buscar() {
  clearTimeout(temporizador)
  if (!busqueda.value.trim()) {
    resultados.value = []
    return
  }
  temporizador = setTimeout(async () => {
    const { data } = await api.get('/api/villas', { params: { q: busqueda.value } })
    resultados.value = data.data
  }, 200)
}

function seleccionar(v: VillaResumen) {
  villaSeleccionada.value = v
  modelo.value = v.villa
  busqueda.value = ''
  resultados.value = []
}

function limpiar() {
  villaSeleccionada.value = null
  modelo.value = ''
}

function elegirTodas() {
  modo.value = 'todas'
  limpiar()
}

// si el componente arranca (o se vuelve a montar, ej. al cambiar de pestaña)
// con un valor ya elegido desde afuera, recupera los datos para mostrar el chip
onMounted(async () => {
  if (!modelo.value || villaSeleccionada.value) return
  const { data } = await api.get('/api/villas', { params: { q: modelo.value } })
  villaSeleccionada.value = data.data.find((v: VillaResumen) => v.villa === modelo.value) ?? null
})
</script>

<template>
  <div>
    <div v-if="conOpcionTodas" class="mb-1.5 flex gap-4 border-b border-espresso-800/10 text-sm">
      <button
        type="button"
        class="border-b-2 pb-1.5 font-medium"
        :class="modo === 'todas' ? 'border-brand-600 text-brand-700' : 'border-transparent text-espresso-800/40'"
        @click="elegirTodas"
      >
        Todas las villas
      </button>
      <button
        type="button"
        class="border-b-2 pb-1.5 font-medium"
        :class="modo === 'una' ? 'border-brand-600 text-brand-700' : 'border-transparent text-espresso-800/40'"
        @click="modo = 'una'"
      >
        Una villa
      </button>
    </div>

    <div v-if="modo === 'una'" class="pt-1">
      <div
        v-if="villaSeleccionada"
        class="flex items-center justify-between rounded-lg border border-brand-300 bg-brand-50 px-3 py-2 text-sm"
      >
        <span class="font-medium text-espresso-800">
          {{ villaSeleccionada.villa }} — {{ villaSeleccionada.nombre_completo }}
        </span>
        <button type="button" class="text-espresso-800/50 hover:text-wine-600" @click="limpiar">
          Cambiar
        </button>
      </div>
      <div v-else class="relative">
        <input
          v-model="busqueda"
          type="text"
          :placeholder="placeholder"
          class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2.5 text-sm placeholder:text-espresso-800/30 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
          @input="buscar"
        />
        <div
          v-if="resultados.length"
          class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-gold-300/40 bg-white shadow-lg"
        >
          <button
            v-for="v in resultados"
            :key="v.villa"
            type="button"
            class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-brand-50"
            @click="seleccionar(v)"
          >
            <span class="font-medium text-espresso-800">{{ v.villa }} — {{ v.nombre_completo }}</span>
            <span class="text-espresso-800/40">{{ formatearMonto(v.saldo) }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
