<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue'
import api from '../lib/api'
import { formatearMonto } from '../lib/format'
import type { VillaResumen } from '../types'

const props = withDefaults(
  defineProps<{
    conOpcionTodas?: boolean
    placeholder?: string
    /** Al elegir la villa con Enter, envia el formulario que la contiene (Buscar / Generar). */
    enviarConEnter?: boolean
  }>(),
  {
    conOpcionTodas: false,
    placeholder: 'Buscar villa por # o propietario...',
    enviarConEnter: false,
  },
)

/** CLV_CLIE de la villa elegida, o '' cuando no hay ninguna (= "Todas las villas"). */
const modelo = defineModel<string>({ default: '' })

const modo = ref<'todas' | 'una'>(props.conOpcionTodas && !modelo.value ? 'todas' : 'una')
const villaSeleccionada = ref<VillaResumen | null>(null)
const busqueda = ref('')
const resultados = ref<VillaResumen[]>([])
/** Resultado resaltado con las flechas (Enter lo elige). */
const resaltado = ref(-1)
const sinResultados = ref(false)
let temporizador: ReturnType<typeof setTimeout> | undefined
// una respuesta vieja (de lo que se escribio antes) no pisa a la mas reciente
let ultimaConsulta = 0

async function consultar(texto: string): Promise<VillaResumen[] | null> {
  const id = ++ultimaConsulta
  const { data } = await api.get('/api/villas', { params: { q: texto } })
  if (id !== ultimaConsulta) return null
  resultados.value = data.data
  resaltado.value = -1
  sinResultados.value = data.data.length === 0
  return data.data
}

function buscar() {
  clearTimeout(temporizador)
  sinResultados.value = false
  if (!busqueda.value.trim()) {
    ultimaConsulta++
    resultados.value = []
    return
  }
  temporizador = setTimeout(() => consultar(busqueda.value), 200)
}

function seleccionar(v: VillaResumen) {
  ultimaConsulta++
  clearTimeout(temporizador)
  villaSeleccionada.value = v
  modelo.value = v.villa
  busqueda.value = ''
  resultados.value = []
  resaltado.value = -1
  sinResultados.value = false
}

/** "z70", "Z70" y "Z-70" son la misma villa. */
const normalizar = (texto: string) => texto.toUpperCase().replace(/[\s-]/g, '')

/**
 * Enter: elige la villa resaltada con las flechas; si no, la que coincide exacto con lo escrito
 * (ej. "z70" -> Z-70) o la unica que aparece. Con varias posibles (ej. un apellido) se queda la
 * lista para elegir. Con enviarConEnter, ademas envia el formulario (Buscar / Generar).
 */
async function alPresionarEnter(e: KeyboardEvent) {
  // sin esto el Enter enviaria el formulario de afuera antes de elegir la villa
  e.preventDefault()
  const input = e.target as HTMLInputElement
  const texto = busqueda.value.trim()
  if (!texto) return

  let elegida: VillaResumen | undefined = resultados.value[resaltado.value]
  if (!elegida) {
    clearTimeout(temporizador)
    const lista = await consultar(texto)
    if (!lista) return
    elegida = lista.find((v) => normalizar(v.villa) === normalizar(texto)) ?? (lista.length === 1 ? lista[0] : undefined)
  }
  if (!elegida) return

  seleccionar(elegida)
  if (props.enviarConEnter) {
    const form = input.form
    await nextTick()
    form?.requestSubmit()
  }
}

function moverResaltado(paso: number) {
  if (!resultados.value.length) return
  const total = resultados.value.length
  resaltado.value = (resaltado.value + paso + total) % total
}

function cerrarLista() {
  resultados.value = []
  resaltado.value = -1
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
          @keydown.enter="alPresionarEnter"
          @keydown.down.prevent="moverResaltado(1)"
          @keydown.up.prevent="moverResaltado(-1)"
          @keydown.esc="cerrarLista"
        />
        <p v-if="sinResultados" class="mt-1 text-xs text-wine-600">No se encontró ninguna villa con "{{ busqueda.trim() }}".</p>
        <div
          v-if="resultados.length"
          class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-gold-300/40 bg-white shadow-lg"
        >
          <button
            v-for="(v, i) in resultados"
            :key="v.villa"
            type="button"
            class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-brand-50"
            :class="i === resaltado ? 'bg-brand-50' : ''"
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
