<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { formatearFecha, enmascararFecha, parsearFecha } from '../lib/fechaFormato'
import { hoyLocal } from '../lib/fecha'
import { useEscapeKey } from '../lib/useEscapeKey'

/**
 * Campo de fecha que siempre se escribe y se ve como DD/MM/AAAA, sin depender del idioma del
 * navegador (el <input type="date"> nativo muestra MM/DD/AAAA en un navegador en ingles).
 *
 * `v-model` maneja la fecha como AAAA-MM-DD (como la API) o '' si esta vacia o incompleta.
 * Las barras se ponen solas al escribir, y el boton de la derecha abre un calendario.
 * Los errores (fecha inexistente, fuera de `min`/`max`) usan la validacion nativa del formulario,
 * asi que impiden el envio igual que cualquier otro campo.
 *
 * El calendario es propio y no el del navegador: el de Safari (Mac y iPhone) no se cierra al
 * elegir un dia y, abierto desde un campo oculto, a veces ni responde. Se dibuja en <body>
 * (Teleport) porque los modales tienen backdrop-blur, que recortaria un popup `fixed` dentro de ellos.
 */
defineOptions({ inheritAttrs: false })

const props = defineProps<{
  modelValue: string
  min?: string
  max?: string
  required?: boolean
  disabled?: boolean
}>()

const emit = defineEmits<{ 'update:modelValue': [valor: string] }>()

const texto = ref(formatearFecha(props.modelValue))
const contenedor = ref<HTMLDivElement | null>(null)
const entrada = ref<HTMLInputElement | null>(null)
const popup = ref<HTMLDivElement | null>(null)

// lo ultimo que emitio este componente; sirve para distinguir un cambio externo del v-model
let ultimoEmitido = props.modelValue

watch(
  () => props.modelValue,
  (nuevo) => {
    if (nuevo === ultimoEmitido) return
    ultimoEmitido = nuevo
    texto.value = formatearFecha(nuevo)
    validar()
  },
)
watch(() => [props.min, props.max], validar)

function mensajeDeError(): string {
  if (texto.value === '') return '' // si es obligatorio, lo avisa el navegador
  const iso = parsearFecha(texto.value)
  if (!iso) return 'Escribe una fecha válida con el formato DD/MM/AAAA.'
  if (props.min && iso < props.min) return `La fecha no puede ser anterior al ${formatearFecha(props.min)}.`
  if (props.max && iso > props.max) return `La fecha no puede ser posterior al ${formatearFecha(props.max)}.`
  return ''
}

function validar() {
  entrada.value?.setCustomValidity(mensajeDeError())
}

function emitir(valor: string) {
  ultimoEmitido = valor
  emit('update:modelValue', valor)
}

function alEscribir(evento: Event) {
  const campo = evento.target as HTMLInputElement
  texto.value = enmascararFecha(campo.value)
  campo.value = texto.value // fuerza lo enmascarado aunque Vue no detecte cambio
  emitir(parsearFecha(texto.value) ?? '')
  validar()
}

// --- calendario ---

const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
const DIAS_SEMANA = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá', 'Do']
const ANCHO_POPUP = 288

const abierto = ref(false)
const anio = ref(0)
const mes = ref(0) // 0-11
const posicion = ref({ top: 0, left: 0 })
const hoy = ref(hoyLocal())

const dosDigitos = (n: number) => String(n).padStart(2, '0')
const aIso = (a: number, m: number, d: number) => `${a}-${dosDigitos(m + 1)}-${dosDigitos(d)}`

const fueraDeRango = (iso: string) => (!!props.min && iso < props.min) || (!!props.max && iso > props.max)

/** Celdas del mes visible, semanas de lunes a domingo; null = hueco antes del dia 1. */
const celdas = computed(() => {
  const primerDia = new Date(Date.UTC(anio.value, mes.value, 1)).getUTCDay() // 0 = domingo
  const huecos = (primerDia + 6) % 7
  const diasDelMes = new Date(Date.UTC(anio.value, mes.value + 1, 0)).getUTCDate()
  const lista: (string | null)[] = Array(huecos).fill(null)
  for (let d = 1; d <= diasDelMes; d++) lista.push(aIso(anio.value, mes.value, d))
  return lista
})

/** Años del selector: un siglo atras (fechas de nacimiento) y unos años adelante, dentro de min/max. */
const anios = computed(() => {
  const actual = Number(hoy.value.slice(0, 4))
  const desde = Math.max(props.min ? Number(props.min.slice(0, 4)) : actual - 100, 1900)
  const hasta = Math.min(props.max ? Number(props.max.slice(0, 4)) : actual + 10, 2100)
  const lista: number[] = []
  for (let a = hasta; a >= desde; a--) lista.push(a)
  if (!lista.includes(anio.value)) lista.push(anio.value) // el mes mostrado siempre debe estar en la lista
  return lista.sort((x, y) => y - x)
})

const puedeIrAtras = computed(() => !props.min || aIso(anio.value, mes.value, 1) > props.min)
const puedeIrAdelante = computed(() => {
  if (!props.max) return true
  const ultimo = new Date(Date.UTC(anio.value, mes.value + 1, 0)).getUTCDate()
  return aIso(anio.value, mes.value, ultimo) < props.max
})

function cambiarMes(delta: number) {
  const total = anio.value * 12 + mes.value + delta
  anio.value = Math.floor(total / 12)
  mes.value = total % 12
}

function colocar() {
  const caja = contenedor.value?.getBoundingClientRect()
  if (!caja) return
  const altoPopup = popup.value?.offsetHeight ?? 340
  const margen = 8
  const cabeAbajo = caja.bottom + 4 + altoPopup <= window.innerHeight - margen
  const top = cabeAbajo || caja.top - altoPopup - 4 < margen ? caja.bottom + 4 : caja.top - altoPopup - 4
  const left = Math.min(Math.max(caja.left, margen), window.innerWidth - ANCHO_POPUP - margen)
  posicion.value = { top, left: Math.max(left, margen) }
}

async function abrirCalendario() {
  if (props.disabled) return
  if (abierto.value) return cerrar()
  hoy.value = hoyLocal()
  // muestra el mes de la fecha elegida; si no hay, el de hoy (o el de `min` si hoy queda antes)
  let base = parsearFecha(texto.value) ?? hoy.value
  if (!parsearFecha(texto.value) && props.min && base < props.min) base = props.min
  if (!parsearFecha(texto.value) && props.max && base > props.max) base = props.max
  anio.value = Number(base.slice(0, 4))
  mes.value = Number(base.slice(5, 7)) - 1
  entrada.value?.blur() // en el iPhone esconde el teclado numerico
  abierto.value = true
  await nextTick()
  colocar()
}

function cerrar() {
  abierto.value = false
}

function elegir(iso: string) {
  if (fueraDeRango(iso)) return
  texto.value = formatearFecha(iso)
  emitir(iso)
  validar()
  cerrar()
}

function limpiar() {
  texto.value = ''
  emitir('')
  validar()
  cerrar()
}

// un toque fuera del campo y del calendario lo cierra
function alTocarFuera(evento: PointerEvent) {
  if (!abierto.value) return
  const destino = evento.target as Node
  if (popup.value?.contains(destino) || contenedor.value?.contains(destino)) return
  cerrar()
}

// al hacer scroll (de la pagina o de un modal) el calendario sigue al campo
function alMoverse(evento: Event) {
  if (!abierto.value || popup.value?.contains(evento.target as Node)) return
  colocar()
}

useEscapeKey(() => {
  if (!abierto.value) return false
  cerrar()
  return true // no cierra tambien el modal de abajo
}, { prioritario: true })

onMounted(() => {
  validar()
  document.addEventListener('pointerdown', alTocarFuera, true)
  window.addEventListener('scroll', alMoverse, true)
  window.addEventListener('resize', alMoverse)
})

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', alTocarFuera, true)
  window.removeEventListener('scroll', alMoverse, true)
  window.removeEventListener('resize', alMoverse)
})
</script>

<template>
  <div ref="contenedor" class="relative">
    <input
      ref="entrada"
      v-bind="$attrs"
      type="text"
      inputmode="numeric"
      autocomplete="off"
      maxlength="10"
      placeholder="DD/MM/AAAA"
      style="padding-right: 2.25rem"
      :value="texto"
      :required="required"
      :disabled="disabled"
      @input="alEscribir"
      @blur="validar"
    />
    <button
      type="button"
      tabindex="-1"
      title="Elegir en el calendario"
      :aria-expanded="abierto"
      :disabled="disabled"
      class="absolute inset-y-0 right-0 flex items-center px-2.5 text-espresso-800/50 hover:text-brand-700 disabled:cursor-not-allowed disabled:opacity-40"
      @click="abrirCalendario"
    >
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4">
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"
        />
      </svg>
    </button>

    <Teleport to="body">
      <div
        v-if="abierto"
        ref="popup"
        role="dialog"
        aria-label="Calendario"
        class="fixed z-[80] rounded-xl border border-espresso-800/10 bg-white p-3 text-sm text-espresso-800 shadow-xl"
        :style="{ top: `${posicion.top}px`, left: `${posicion.left}px`, width: `${ANCHO_POPUP}px` }"
      >
        <div class="mb-2 flex items-center gap-1">
          <button
            type="button"
            title="Mes anterior"
            :disabled="!puedeIrAtras"
            class="rounded-lg p-1.5 hover:bg-cream-100 disabled:opacity-30"
            @click="cambiarMes(-1)"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
          </button>
          <select v-model.number="mes" aria-label="Mes" class="min-w-0 flex-1 rounded-lg border border-espresso-800/15 bg-white px-1.5 py-1 text-sm">
            <option v-for="(nombre, i) in MESES" :key="nombre" :value="i">{{ nombre }}</option>
          </select>
          <select v-model.number="anio" aria-label="Año" class="rounded-lg border border-espresso-800/15 bg-white px-1.5 py-1 text-sm">
            <option v-for="a in anios" :key="a" :value="a">{{ a }}</option>
          </select>
          <button
            type="button"
            title="Mes siguiente"
            :disabled="!puedeIrAdelante"
            class="rounded-lg p-1.5 hover:bg-cream-100 disabled:opacity-30"
            @click="cambiarMes(1)"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
          </button>
        </div>

        <div class="grid grid-cols-7 text-center text-xs font-medium text-espresso-800/50">
          <div v-for="d in DIAS_SEMANA" :key="d" class="py-1">{{ d }}</div>
        </div>
        <div class="grid grid-cols-7 gap-0.5">
          <template v-for="(iso, i) in celdas" :key="iso ?? `hueco-${i}`">
            <div v-if="!iso" />
            <button
              v-else
              type="button"
              :disabled="fueraDeRango(iso)"
              class="h-9 rounded-lg text-sm disabled:cursor-not-allowed disabled:text-espresso-800/25"
              :class="
                iso === modelValue
                  ? 'bg-brand-600 font-semibold text-white'
                  : iso === hoy
                    ? 'font-semibold text-brand-700 ring-1 ring-brand-300 hover:bg-brand-50'
                    : 'hover:bg-cream-100'
              "
              @click="elegir(iso)"
            >
              {{ Number(iso.slice(8)) }}
            </button>
          </template>
        </div>

        <div class="mt-2 flex justify-between border-t border-espresso-800/10 pt-2 text-xs">
          <button
            type="button"
            class="rounded-lg px-2 py-1 font-medium text-brand-700 hover:bg-brand-50 disabled:opacity-30"
            :disabled="fueraDeRango(hoy)"
            @click="elegir(hoy)"
          >
            Hoy
          </button>
          <button
            v-if="!required && texto !== ''"
            type="button"
            class="rounded-lg px-2 py-1 text-espresso-800/60 hover:bg-cream-100"
            @click="limpiar"
          >
            Borrar
          </button>
        </div>
      </div>
    </Teleport>
  </div>
</template>
