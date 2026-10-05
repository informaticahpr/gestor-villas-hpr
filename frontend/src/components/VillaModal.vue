<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import api from '../lib/api'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import { useDataStore } from '../stores/data'
import { useDialogStore } from '../stores/dialog'
import { formatearMonto } from '../lib/format'
import { mensajeDeError } from '../lib/errors'
import { useEscapeKey } from '../lib/useEscapeKey'
import { abrirReporte, type FormatoExportacion } from '../lib/exportar'
import { soloDigitos, mayusculas } from '../lib/filtrosCampo'
import ExportarBotones from './ExportarBotones.vue'
import ObservacionModal from './ObservacionModal.vue'
import FechaInput from './FechaInput.vue'
import PersonaCampos from './PersonaCampos.vue'
import { formatearFecha } from '../lib/fechaFormato'
import type { VillaDetalle, EstadoCuenta, MovimientoFila, DatosPersona, Propietario, HistorialVilla, PersonaAnterior } from '../types'

const props = defineProps<{ villaId?: string }>()
const emit = defineEmits<{ close: []; saved: [] }>()

const auth = useAuthStore()
const toast = useToastStore()
const dataStore = useDataStore()
const dialog = useDialogStore()

const esEdicion = computed(() => Boolean(props.villaId))
const editando = ref(false)
const soloLectura = computed(() => esEdicion.value && !editando.value)
const puedeEditar = computed(() => auth.esDirectorOAdmin())

type Pestana = 'propietario' | 'encargado' | 'villa' | 'reporte' | 'historial'
const tab = ref<Pestana>('propietario')
const pestanas = computed(() => {
  const lista: { id: Pestana; titulo: string }[] = [
    { id: 'propietario', titulo: 'Datos de Propietario' },
    { id: 'encargado', titulo: 'Datos de Encargado' },
    { id: 'villa', titulo: 'Datos de Villa' },
  ]
  if (esEdicion.value) {
    lista.push({ id: 'reporte', titulo: 'Reporte (último año)' })
    lista.push({ id: 'historial', titulo: 'Historial de Villa' })
  }
  return lista
})

const cargando = ref(false)
const guardando = ref(false)
const estadoCuenta = ref<EstadoCuenta | null>(null)
const periodo = ref<{ desde: string; hasta: string } | null>(null)
const movimientoDetalle = ref<MovimientoFila | null>(null)
const saldo = ref(0)

/** Las pestañas del formulario (las otras, Reporte e Historial, son solo de consulta). */
const esPestanaDeDatos = computed(() => tab.value === 'propietario' || tab.value === 'encargado' || tab.value === 'villa')

// ---------------------------------------------------------------- historial de la villa
// Propietarios y encargados anteriores, con sus datos tal como estaban registrados cuando salieron.

const historial = ref<HistorialVilla | null>(null)
const subtabHistorial = ref<'linea' | 'propietarios' | 'encargados'>('linea')
/** Nombres del propietario/encargado actuales tal como se cargaron (no lo que se este editando). */
const actuales = ref<{ propietario: string; encargado: string | null }>({ propietario: '', encargado: null })

interface EventoHistorial {
  clave: string
  tipo: 'propietario' | 'encargado'
  nombre: string
  desde: string | null
  hasta: string | null
  registradoPor: string | null
}

/** Linea de tiempo: primero los actuales, luego los anteriores del cambio mas reciente al mas antiguo. */
const lineaDeTiempo = computed<EventoHistorial[]>(() => {
  const h = historial.value
  if (!h) return []
  const eventos: EventoHistorial[] = [
    { clave: 'p-actual', tipo: 'propietario', nombre: actuales.value.propietario, desde: h.propietario_desde, hasta: null, registradoPor: null },
  ]
  if (actuales.value.encargado)
    eventos.push({ clave: 'e-actual', tipo: 'encargado', nombre: actuales.value.encargado, desde: h.encargado_desde, hasta: null, registradoPor: null })
  const anteriores = [...h.propietarios, ...h.encargados]
    .sort((a, b) => b.hasta.localeCompare(a.hasta) || b.id - a.id)
    .map((p) => ({ clave: `h-${p.id}`, tipo: p.tipo, nombre: p.nombre_completo || '—', desde: p.desde, hasta: p.hasta, registradoPor: p.registrado_por }))
  return [...eventos, ...anteriores]
})

function textoPeriodo(desde: string | null, hasta: string | null): string {
  const d = desde ? formatearFecha(desde) : '¿?'
  return hasta ? `${d} — ${formatearFecha(hasta)}` : `Desde ${d} — actual`
}

const camposFicha: { campo: keyof PersonaAnterior; etiqueta: string; soloEncargado?: boolean }[] = [
  { campo: 'DNI', etiqueta: 'DNI / Pasaporte' },
  { campo: 'PARENTESCO', etiqueta: 'Parentesco / Vínculo', soloEncargado: true },
  { campo: 'FECHA_NAC', etiqueta: 'Fecha de nacimiento' },
  { campo: 'TELF', etiqueta: 'Celular 1' },
  { campo: 'CELULAR', etiqueta: 'Celular 2' },
  { campo: 'OTRO_TEL', etiqueta: 'Otro' },
  { campo: 'MAIL', etiqueta: 'Correo Electrónico 1' },
  { campo: 'MAIL2', etiqueta: 'Correo Electrónico 2' },
]

function valorFicha(p: PersonaAnterior, campo: keyof PersonaAnterior): string {
  const v = p[campo]
  if (v === null || v === '') return '—'
  return campo === 'FECHA_NAC' ? formatearFecha(String(v)) : String(v)
}

// ---------------------------------------------------------------- formulario

const personaVacia = () => ({ NOMBRES: '', APELLIDOS: '', DNI: '', PARENTESCO: '', TELF: '', CELULAR: '', OTRO_TEL: '', MAIL: '', MAIL2: '', FECHA_NAC: '' })
const deApi = (d: DatosPersona | null) => {
  const v = personaVacia()
  if (d) for (const k of Object.keys(v) as (keyof typeof v)[]) v[k] = d[k] ?? ''
  return v
}

const clvClie = ref('')
const propietario = reactive(personaVacia())
const encargado = reactive(personaVacia())
const villa = reactive({
  DIR: '',
  FCONTRUC: '',
  NOMED: '',
  CLAVE_CATASTRAL: '',
  DESCRIPCION_IP: '',
  OBSERVACION: '',
  NOHAB: '',
  NOBATH: '',
  APLICOBRO: true,
  CUOTA_ESPECIAL: false,
})

// ---------------------------------------------------------------- propietario nuevo / ya registrado
// Un propietario puede tener varias villas: se puede elegir uno ya registrado en vez de capturarlo.

const modoPropietario = ref<'nuevo' | 'existente'>('nuevo')
const propietarioSeleccionado = ref<Propietario | null>(null)
/** Propietario que tenia la villa al abrirla (para saber si se cambio). */
const propietarioOriginalId = ref<number | null>(null)
const busqueda = ref('')
const resultados = ref<Propietario[]>([])
const buscando = ref(false)
let temporizador: ReturnType<typeof setTimeout> | undefined

watch(busqueda, (q) => {
  clearTimeout(temporizador)
  if (q.trim().length < 2) {
    resultados.value = []
    return
  }
  temporizador = setTimeout(async () => {
    buscando.value = true
    try {
      const { data } = await api.get('/api/propietarios', { params: { q: q.trim() } })
      resultados.value = data.data
    } finally {
      buscando.value = false
    }
  }, 250)
})

function seleccionarPropietario(p: Propietario) {
  propietarioSeleccionado.value = p
  Object.assign(propietario, deApi(p))
  busqueda.value = ''
  resultados.value = []
}

function cambiarModoPropietario(modo: 'nuevo' | 'existente') {
  modoPropietario.value = modo
  propietarioSeleccionado.value = null
  Object.assign(propietario, personaVacia())
}

/** Otras villas del propietario elegido (sin contar la que se esta viendo). */
const otrasVillas = computed(() => (propietarioSeleccionado.value?.villas ?? []).filter((v) => v !== props.villaId))

/** El Supervisor puede vincular un propietario ya registrado, pero no modificar sus datos. */
const propietarioBloqueado = computed(() => propietarioSeleccionado.value !== null && !puedeEditar.value)

// ---------------------------------------------------------------- carga y guardado

async function cargar() {
  if (!props.villaId) return
  cargando.value = true
  try {
    const { data } = await api.get(`/api/villas/${props.villaId}`)
    const v: VillaDetalle = data.villa
    clvClie.value = v.CLV_CLIE
    Object.assign(propietario, deApi(v.propietario))
    Object.assign(encargado, deApi(v.encargado))
    Object.assign(villa, {
      DIR: v.villa.DIR ?? '',
      FCONTRUC: v.villa.FCONTRUC ?? '',
      NOMED: v.villa.NOMED ?? '',
      CLAVE_CATASTRAL: v.villa.CLAVE_CATASTRAL ?? '',
      DESCRIPCION_IP: v.villa.DESCRIPCION_IP ?? '',
      OBSERVACION: v.villa.OBSERVACION ?? '',
      NOHAB: v.villa.NOHAB?.toString() ?? '',
      NOBATH: v.villa.NOBATH?.toString() ?? '',
      APLICOBRO: v.villa.APLICOBRO,
      CUOTA_ESPECIAL: v.villa.CUOTA_ESPECIAL,
    })
    propietarioSeleccionado.value = v.propietario
    propietarioOriginalId.value = v.propietario?.id ?? null
    respuestaOtroPropietario = null
    encargadoYaPreguntado = false
    modoPropietario.value = v.propietario ? 'existente' : 'nuevo'
    saldo.value = v.SALDO
    estadoCuenta.value = data.estado_cuenta
    periodo.value = data.periodo
    historial.value = data.historial
    actuales.value = {
      propietario: v.propietario?.nombre_completo ?? '—',
      encargado: v.encargado ? `${v.encargado.NOMBRES ?? ''} ${v.encargado.APELLIDOS ?? ''}`.trim() || 'Sin nombre' : null,
    }
  } finally {
    cargando.value = false
  }
}

function activarEdicion() {
  editando.value = true
}

async function cancelarEdicion() {
  editando.value = false
  await cargar()
}

/** Revisa lo obligatorio y, si falta algo, lleva a la pestaña donde esta. */
function faltaAlgo(): boolean {
  const faltas: { tab: Pestana; mensaje: string }[] = []
  if (!esEdicion.value && !clvClie.value.trim()) faltas.push({ tab: tab.value, mensaje: 'Indica el número de villa.' })
  if (!propietario.NOMBRES.trim() || !propietario.APELLIDOS.trim())
    faltas.push({ tab: 'propietario', mensaje: 'Indica los nombres y apellidos del propietario.' })
  if (!propietario.TELF && !propietario.CELULAR && !propietario.OTRO_TEL)
    faltas.push({ tab: 'propietario', mensaje: 'Indica al menos un teléfono del propietario.' })
  if (!propietario.MAIL && !propietario.MAIL2)
    faltas.push({ tab: 'propietario', mensaje: 'Indica al menos un correo del propietario.' })
  if (!villa.DIR.trim()) faltas.push({ tab: 'villa', mensaje: 'Indica la ubicación de la villa.' })
  if (!villa.FCONTRUC) faltas.push({ tab: 'villa', mensaje: 'Indica la fecha de entrega de la villa.' })

  if (faltas.length === 0) return false
  tab.value = faltas[0].tab
  toast.warning(faltas[0].mensaje)
  return true
}

/** Un error del servidor ("propietario.MAIL", "villa.DIR"...) lleva a la pestaña del campo. */
function irAPestanaDelError(e: any) {
  const campo = Object.keys(e?.response?.data?.errors ?? {})[0] ?? ''
  const seccion = campo.split('.')[0]
  if (seccion === 'propietario' || seccion === 'encargado' || seccion === 'villa') tab.value = seccion
}

/**
 * Si al propietario ya registrado se le cambio el nombre o el DNI, puede ser otra persona (la villa
 * cambio de dueño) o una correccion. Se pregunta, porque no es lo mismo: si es otra persona se crea un
 * propietario nuevo y el anterior queda intacto y en el historial; si es correccion se editan sus datos
 * (en todas sus villas). Devuelve null si se cancela.
 */
async function preguntarSiEsOtroPropietario(): Promise<boolean | null> {
  const antes = propietarioSeleccionado.value
  if (!antes || !puedeEditar.value) return false
  const nombre = (p: { NOMBRES: string | null; APELLIDOS: string | null }) => `${p.NOMBRES ?? ''} ${p.APELLIDOS ?? ''}`.trim()
  const cambioNombre = nombre(antes) !== nombre(propietario)
  const cambioDni = (antes.DNI ?? '') !== propietario.DNI
  if (!cambioNombre && !cambioDni) return false

  const respuesta = await dialog.elegir({
    titulo: '¿Cambió el propietario?',
    mensaje:
      `Cambiaste ${cambioNombre ? 'el nombre' : 'el DNI/Pasaporte'} de ${antes.nombre_completo}` +
      (cambioNombre ? ` a ${nombre(propietario)}` : '') +
      '. ¿Es otra persona (la villa cambió de dueño) o solo estás corrigiendo sus datos?' +
      (otrasVillas.value.length ? ` Si es una corrección, también se aplica a sus otras villas (${otrasVillas.value.join(', ')}).` : ''),
    opciones: [
      { valor: 'correccion', texto: 'Solo corregí sus datos' },
      { valor: 'otra', texto: 'Es otro propietario', principal: true },
    ],
  })
  return respuesta === null ? null : respuesta === 'otra'
}

// Respuestas ya dadas en esta edicion, para no volver a preguntar al guardar de nuevo (por ejemplo,
// despues de pasar a la pestaña del encargado). Se reinician al cargar la villa.
let respuestaOtroPropietario: boolean | null = null
let encargadoYaPreguntado = false

/**
 * Si la villa cambia de propietario y tiene encargado, pregunta si se quita. Devuelve 'quitado' (se
 * limpio y hay que llenar la pestaña del encargado antes de guardar), 'seguir' o null (cancelado).
 */
async function preguntarPorEncargado(): Promise<'quitado' | 'seguir' | null> {
  const nombreEncargado = actuales.value.encargado
  if (encargadoYaPreguntado || !nombreEncargado) return 'seguir'
  const tieneDatos = Object.values(encargado).some((v) => v)
  if (!tieneDatos) return 'seguir'

  const respuesta = await dialog.elegir({
    titulo: '¿Quitar al encargado anterior?',
    mensaje:
      `La villa cambia de propietario y su encargado actual es ${nombreEncargado}. ¿Quieres quitarlo? ` +
      'Pasará al historial de la villa y podrás registrar un encargado nuevo o dejarlo vacío.',
    opciones: [
      { valor: 'mantener', texto: 'No, mantenerlo' },
      { valor: 'quitar', texto: 'Sí, quitarlo', principal: true },
    ],
  })
  if (respuesta === null) return null
  encargadoYaPreguntado = true
  if (respuesta === 'mantener') return 'seguir'

  Object.assign(encargado, personaVacia())
  tab.value = 'encargado'
  toast.info('Encargado anterior quitado. Llena los datos del nuevo encargado (opcional) y presiona Guardar.')
  return 'quitado'
}

async function guardar() {
  if (faltaAlgo()) return

  if (esEdicion.value && respuestaOtroPropietario === null) {
    const r = await preguntarSiEsOtroPropietario()
    if (r === null) return
    respuestaOtroPropietario = r
  }
  const esOtraPersona = respuestaOtroPropietario === true

  // ¿cambia el propietario? (propietario nuevo, otro ya registrado, o "es otro propietario")
  const cambiaPropietario =
    esEdicion.value && (esOtraPersona || (propietarioSeleccionado.value?.id ?? null) !== propietarioOriginalId.value)
  if (cambiaPropietario) {
    const r = await preguntarPorEncargado()
    if (r !== 'seguir') return
  }

  const entero = (v: string) => (v === '' ? null : Number(v))
  const payload = {
    CLV_CLIE: clvClie.value,
    propietario: {
      ...propietario,
      id: esOtraPersona ? null : (propietarioSeleccionado.value?.id ?? null),
    },
    encargado: { ...encargado },
    villa: { ...villa, NOHAB: entero(villa.NOHAB), NOBATH: entero(villa.NOBATH) },
  }

  guardando.value = true
  try {
    if (esEdicion.value) {
      await api.put(`/api/villas/${props.villaId}`, payload)
      toast.success(`Villa ${clvClie.value} actualizada correctamente.`)
    } else {
      await api.post('/api/villas', payload)
      toast.success(`Villa ${clvClie.value} creada correctamente.`)
    }
    editando.value = false
    dataStore.tocar()
    emit('saved')
    // recarga para ver los datos guardados y el historial actualizado sin cerrar el modal
    if (esEdicion.value) await cargar()
  } catch (e: any) {
    irAPestanaDelError(e)
    toast.error(mensajeDeError(e, 'No se pudo guardar la villa.'))
  } finally {
    guardando.value = false
  }
}

// Exporta el mismo periodo que muestra la pestaña, tal como lo calculo el servidor (VillaController::show).
function exportarReporte(formato: FormatoExportacion) {
  if (!periodo.value) return
  abrirReporte('estado-cuenta', formato, { villa: clvClie.value, ...periodo.value })
}

function claseSaldo(valor: number): string {
  if (valor > 0) return 'text-wine-600'
  if (valor < 0) return 'text-emerald-600'
  return 'text-espresso-700'
}

onMounted(cargar)

// Escape hace lo mismo que el boton de cierre visible en el pie del modal.
useEscapeKey(() => {
  if (esPestanaDeDatos.value && !soloLectura.value && esEdicion.value) cancelarEdicion()
  else emit('close')
})

function valor(e: Event): string {
  return (e.target as HTMLInputElement).value
}

const claseInput =
  'w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2.5 text-sm placeholder:text-espresso-800/30 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200 disabled:bg-cream-200 disabled:text-espresso-800/50'
const claseLabel = 'mb-1.5 block text-sm font-medium text-espresso-700'
const claseCheck = 'h-4 w-4 rounded border-espresso-800/25 text-brand-600 focus:ring-2 focus:ring-brand-300'
</script>

<template>
  <Teleport to="body">
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-espresso-900/50 p-4 backdrop-blur-sm">
    <div class="flex max-h-[90vh] w-full max-w-3xl flex-col rounded-2xl bg-cream-50 shadow-2xl shadow-espresso-900/20">
      <div class="rounded-t-2xl border-b border-gold-300/30 bg-gradient-to-r from-brand-50/70 to-cream-50 px-6 pt-4">
        <div class="flex items-start justify-between gap-4">
          <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-display text-lg font-semibold text-espresso-800">
              {{ esEdicion ? `Villa ${clvClie}` : 'Nueva Villa' }}
            </h2>
            <span v-if="soloLectura" class="rounded-full bg-espresso-800/8 px-2 py-0.5 text-xs font-medium text-espresso-800/50">
              Solo lectura
            </span>
          </div>

          <div v-if="esEdicion" class="text-right">
            <p class="text-xs font-medium uppercase tracking-wide text-espresso-800/40">Saldo a la fecha</p>
            <p class="font-display text-2xl font-bold" :class="claseSaldo(saldo)">{{ formatearMonto(saldo) }}</p>
          </div>
        </div>

        <!-- Numero de villa: arriba de las pestañas, solo al crear (despues ya no se puede cambiar) -->
        <div v-if="!esEdicion" class="mt-3 flex items-center gap-3">
          <label for="villa-numero" class="text-sm font-medium text-espresso-700"># Villa<span class="text-wine-500"> *</span></label>
          <input
            id="villa-numero"
            :value="clvClie"
            maxlength="5"
            placeholder="A-1"
            class="w-32 rounded-lg border border-espresso-800/15 bg-white px-3 py-2 text-sm font-semibold placeholder:font-normal placeholder:text-espresso-800/30 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
            @input="clvClie = mayusculas(valor($event))"
          />
        </div>

        <div class="mt-3 flex gap-5 overflow-x-auto text-sm">
          <button
            v-for="p in pestanas"
            :key="p.id"
            type="button"
            class="whitespace-nowrap border-b-2 pb-2 font-medium transition-colors"
            :class="tab === p.id ? 'border-brand-600 text-brand-700' : 'border-transparent text-espresso-800/45 hover:text-espresso-800/70'"
            @click="tab = p.id"
          >
            {{ p.titulo }}
          </button>
        </div>
      </div>

      <div class="overflow-y-auto px-6 py-5">
        <div v-if="cargando" class="py-10 text-center text-espresso-800/40">Cargando...</div>

        <form v-else-if="esPestanaDeDatos" @submit.prevent="guardar">
          <fieldset :disabled="soloLectura" class="contents">
            <!-- ======================= Datos de Propietario ======================= -->
            <div v-if="tab === 'propietario'" class="space-y-5">
              <div v-if="!soloLectura" class="flex flex-wrap items-center gap-2">
                <div class="inline-flex rounded-lg border border-espresso-800/15 bg-white p-0.5 text-sm">
                  <button
                    type="button"
                    class="rounded-md px-3 py-1.5 font-medium transition"
                    :class="modoPropietario === 'nuevo' ? 'bg-brand-100 text-brand-800' : 'text-espresso-800/60 hover:text-espresso-800'"
                    @click="cambiarModoPropietario('nuevo')"
                  >
                    Propietario nuevo
                  </button>
                  <button
                    type="button"
                    class="rounded-md px-3 py-1.5 font-medium transition"
                    :class="modoPropietario === 'existente' ? 'bg-brand-100 text-brand-800' : 'text-espresso-800/60 hover:text-espresso-800'"
                    @click="cambiarModoPropietario('existente')"
                  >
                    Propietario ya registrado
                  </button>
                </div>
              </div>

              <!-- buscador de propietario ya registrado -->
              <!-- Los resultados van en el flujo normal (no flotando sobre el modal, que tiene scroll
                   y los cortaba/encimaba): ocupan su propio espacio debajo del buscador. -->
              <div v-if="modoPropietario === 'existente' && !propietarioSeleccionado && !soloLectura" class="min-h-[20rem]">
                <label :class="claseLabel">Buscar propietario</label>
                <div class="relative">
                  <svg viewBox="0 0 24 24" fill="currentColor" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-espresso-800/35">
                    <path d="M11 4a7 7 0 104.9 12.02l4.54 4.54a1 1 0 001.42-1.42l-4.54-4.54A7 7 0 0011 4zm-5 7a5 5 0 1110 0 5 5 0 01-10 0z" />
                  </svg>
                  <input
                    v-model="busqueda"
                    placeholder="Escribe nombre, apellido o DNI/Pasaporte"
                    :class="[claseInput, 'pl-9']"
                    autocomplete="off"
                    autofocus
                  />
                </div>

                <p v-if="busqueda.trim().length < 2" class="mt-3 text-sm text-espresso-800/45">
                  Escribe al menos 2 letras para buscar entre los propietarios registrados.
                </p>
                <div v-else class="mt-3 overflow-hidden rounded-lg border border-gold-300/40 bg-white">
                  <p v-if="buscando" class="px-4 py-3 text-sm text-espresso-800/40">Buscando...</p>
                  <p v-else-if="resultados.length === 0" class="px-4 py-3 text-sm text-espresso-800/45">
                    No hay propietarios con ese nombre o DNI. Puedes registrarlo en "Propietario nuevo".
                  </p>
                  <div v-else class="max-h-72 divide-y divide-gold-300/20 overflow-y-auto">
                    <button
                      v-for="r in resultados"
                      :key="r.id"
                      type="button"
                      class="flex w-full items-center justify-between gap-4 px-4 py-3 text-left transition hover:bg-brand-50"
                      @click="seleccionarPropietario(r)"
                    >
                      <span class="min-w-0">
                        <span class="block truncate text-sm font-medium text-espresso-800">{{ r.nombre_completo }}</span>
                        <span class="block text-xs text-espresso-800/50">
                          {{ r.DNI ?? 'Sin DNI/Pasaporte' }} · {{ r.TELF || r.CELULAR || r.OTRO_TEL || 'Sin teléfono' }}
                        </span>
                      </span>
                      <span class="shrink-0 text-right text-xs text-espresso-800/55">
                        {{ r.villas.length === 1 ? 'Villa' : 'Villas' }}
                        <span class="block font-medium text-espresso-800">{{ r.villas.join(', ') || '—' }}</span>
                      </span>
                    </button>
                  </div>
                </div>
              </div>

              <template v-if="modoPropietario === 'nuevo' || propietarioSeleccionado">
                <div
                  v-if="propietarioSeleccionado && !soloLectura"
                  class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-brand-200 bg-brand-50/60 px-3 py-2 text-sm"
                >
                  <span class="text-espresso-800">
                    <span class="font-semibold">{{ propietarioSeleccionado.nombre_completo }}</span>
                    <span v-if="otrasVillas.length" class="text-espresso-800/60"> · también tiene: {{ otrasVillas.join(', ') }}</span>
                  </span>
                  <button type="button" class="text-xs font-medium text-brand-700 underline" @click="cambiarModoPropietario('existente')">
                    Elegir otro
                  </button>
                </div>
                <p v-if="propietarioBloqueado" class="text-xs text-espresso-800/55">
                  Los datos de un propietario ya registrado solo los puede modificar un Director o Administrador.
                </p>
                <p v-else-if="propietarioSeleccionado && otrasVillas.length && !soloLectura" class="text-xs text-espresso-800/55">
                  Los cambios en estos datos se aplican también a sus otras villas.
                </p>

                <fieldset :disabled="propietarioBloqueado" class="contents">
                  <PersonaCampos :persona="propietario" requerido de="del propietario" />
                </fieldset>
              </template>
            </div>

            <!-- ======================= Datos de Encargado ======================= -->
            <div v-else-if="tab === 'encargado'" class="space-y-4">
              <p class="text-xs text-espresso-800/55">Persona que atiende la villa en nombre del propietario. Todos los campos son opcionales.</p>
              <PersonaCampos :persona="encargado" con-parentesco de="del encargado" />
            </div>

            <!-- ======================= Datos de Villa ======================= -->
            <div v-else-if="tab === 'villa'" class="space-y-6">
              <div class="grid grid-cols-1 gap-x-5 gap-y-5 sm:grid-cols-2">
                <div>
                  <label :class="claseLabel">Ubicación de la villa<span class="text-wine-500"> *</span></label>
                  <input :value="villa.DIR" maxlength="255" placeholder="BLOQUE A" :class="claseInput" @input="villa.DIR = mayusculas(valor($event))" />
                </div>
                <div>
                  <label :class="claseLabel">Fecha de entrega<span class="text-wine-500"> *</span></label>
                  <FechaInput v-model="villa.FCONTRUC" :class="claseInput" />
                </div>
                <div>
                  <label :class="claseLabel">Medidor ENEE</label>
                  <input :value="villa.NOMED" maxlength="20" placeholder="ENEE-1001" :class="claseInput" @input="villa.NOMED = mayusculas(valor($event))" />
                </div>
                <div>
                  <label :class="claseLabel">Clave catastral</label>
                  <input :value="villa.CLAVE_CATASTRAL" maxlength="40" placeholder="0801-0001-00001" :class="claseInput" @input="villa.CLAVE_CATASTRAL = mayusculas(valor($event))" />
                </div>
                <div class="sm:col-span-2">
                  <label :class="claseLabel">Descripción IP</label>
                  <textarea
                    :value="villa.DESCRIPCION_IP"
                    rows="2"
                    maxlength="255"
                    :class="[claseInput, 'resize-none']"
                    @input="villa.DESCRIPCION_IP = mayusculas(valor($event))"
                  />
                </div>
                <div class="sm:col-span-2">
                  <label :class="claseLabel">Observación de la villa</label>
                  <textarea
                    :value="villa.OBSERVACION"
                    rows="2"
                    maxlength="500"
                    placeholder="VILLA A NOMBRE DE JUAN PÉREZ / MARÍA LÓPEZ"
                    :class="[claseInput, 'resize-none']"
                    @input="villa.OBSERVACION = mayusculas(valor($event))"
                  />
                </div>
                <div>
                  <label :class="claseLabel"># Habitaciones</label>
                  <input :value="villa.NOHAB" inputmode="numeric" maxlength="3" placeholder="2" title="Solo números" :class="claseInput" @input="villa.NOHAB = soloDigitos(valor($event))" />
                </div>
                <div>
                  <label :class="claseLabel"># Baños</label>
                  <input :value="villa.NOBATH" inputmode="numeric" maxlength="3" placeholder="2" title="Solo números" :class="claseInput" @input="villa.NOBATH = soloDigitos(valor($event))" />
                </div>
              </div>

              <div class="flex flex-wrap gap-x-8 gap-y-3">
                <label class="flex items-center gap-2 text-sm text-espresso-700">
                  <input v-model="villa.APLICOBRO" type="checkbox" :class="claseCheck" />
                  Aplicar cuota mensual
                </label>
                <label class="flex items-center gap-2 text-sm text-espresso-700">
                  <input v-model="villa.CUOTA_ESPECIAL" type="checkbox" :class="claseCheck" />
                  Cuota especial
                </label>
              </div>
            </div>
          </fieldset>
        </form>

        <!-- ======================= Reporte ======================= -->
        <div v-else-if="tab === 'reporte'">
          <div class="mb-3 flex justify-end gap-2">
            <ExportarBotones @exportar="exportarReporte" />
          </div>
          <table class="min-w-full divide-y divide-gold-300/20 text-sm">
            <thead>
              <tr class="text-left text-espresso-800/50">
                <th class="py-1.5 pr-2">Fecha</th>
                <th class="py-1.5 pr-2">Descripción</th>
                <th class="py-1.5 pr-2 text-right">Cargos</th>
                <th class="py-1.5 pr-2 text-right">Créditos</th>
                <th class="py-1.5 text-right">Saldo</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gold-300/15">
              <tr>
                <td colspan="4" class="py-1.5 pr-2 text-espresso-800/50">Saldo inicial</td>
                <td class="py-1.5 text-right font-medium">{{ formatearMonto(estadoCuenta?.saldo_inicial ?? 0) }}</td>
              </tr>
              <tr
                v-for="(m, i) in estadoCuenta?.movimientos ?? []"
                :key="i"
                tabindex="0"
                title="Ver detalle y recibo"
                class="cursor-pointer hover:bg-brand-50/50 focus:bg-brand-50/50 focus:outline-none"
                @click="movimientoDetalle = m"
                @keydown.enter="movimientoDetalle = m"
              >
                <td class="py-1.5 pr-2">{{ formatearFecha(m.fecha) }}</td>
                <td class="py-1.5 pr-2">
                  {{ m.descripcion }}
                  <span v-if="m.observacion" class="ml-1.5 inline-block h-1.5 w-1.5 rounded-full bg-gold-500 align-middle" title="Tiene observación"></span>
                </td>
                <td class="py-1.5 pr-2 text-right">{{ m.cargo ? formatearMonto(m.cargo) : '' }}</td>
                <td class="py-1.5 pr-2 text-right">{{ m.credito ? formatearMonto(m.credito) : '' }}</td>
                <td class="py-1.5 text-right">{{ formatearMonto(m.saldo) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="border-t border-gold-300/40 font-semibold text-espresso-800">
                <td colspan="4" class="py-1.5 pr-2">Saldo a la fecha</td>
                <td class="py-1.5 text-right">{{ formatearMonto(estadoCuenta?.saldo_final ?? 0) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- ======================= Historial de Villa ======================= -->
        <div v-else-if="tab === 'historial' && historial">
          <div class="mb-5 inline-flex rounded-lg border border-espresso-800/15 bg-white p-0.5 text-sm">
            <button
              v-for="s in [
                { id: 'linea', titulo: 'Historial' },
                { id: 'propietarios', titulo: `Propietarios anteriores (${historial.propietarios.length})` },
                { id: 'encargados', titulo: `Encargados anteriores (${historial.encargados.length})` },
              ] as const"
              :key="s.id"
              type="button"
              class="rounded-md px-3 py-1.5 font-medium transition"
              :class="subtabHistorial === s.id ? 'bg-brand-100 text-brand-800' : 'text-espresso-800/60 hover:text-espresso-800'"
              @click="subtabHistorial = s.id"
            >
              {{ s.titulo }}
            </button>
          </div>

          <!-- linea de tiempo -->
          <ol v-if="subtabHistorial === 'linea'" class="relative ml-2 border-l-2 border-gold-300/50">
            <li v-for="e in lineaDeTiempo" :key="e.clave" class="relative mb-5 pl-5 last:mb-0">
              <span
                class="absolute -left-[7px] top-1.5 h-3 w-3 rounded-full ring-2 ring-cream-50"
                :class="e.hasta ? 'bg-espresso-800/25' : 'bg-brand-500'"
              />
              <div class="flex flex-wrap items-center gap-2">
                <span
                  class="rounded-full px-2 py-0.5 text-xs font-medium"
                  :class="e.tipo === 'propietario' ? 'bg-brand-100 text-brand-800' : 'bg-gold-300/30 text-espresso-800'"
                >
                  {{ e.tipo === 'propietario' ? 'Propietario' : 'Encargado' }}
                </span>
                <span v-if="!e.hasta" class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Actual</span>
                <span class="font-medium text-espresso-900" :class="e.hasta ? 'text-espresso-800/70' : ''">{{ e.nombre }}</span>
              </div>
              <p class="mt-1 text-xs text-espresso-800/55">
                {{ textoPeriodo(e.desde, e.hasta) }}
                <template v-if="e.registradoPor"> · cambio registrado por {{ e.registradoPor }}</template>
              </p>
            </li>
          </ol>

          <!-- fichas de propietarios / encargados anteriores -->
          <template v-else>
            <p
              v-if="(subtabHistorial === 'propietarios' ? historial.propietarios : historial.encargados).length === 0"
              class="py-8 text-center text-sm text-espresso-800/40"
            >
              Esta villa no ha tenido {{ subtabHistorial === 'propietarios' ? 'otros propietarios' : 'otros encargados' }} registrados en el sistema.
            </p>
            <div
              v-for="p in subtabHistorial === 'propietarios' ? historial.propietarios : historial.encargados"
              :key="p.id"
              class="mb-4 rounded-xl border border-gold-300/30 bg-white/70 p-4 last:mb-0"
            >
              <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2 border-b border-gold-300/20 pb-2">
                <p class="font-display font-semibold text-espresso-800">{{ p.nombre_completo || '—' }}</p>
                <p class="text-xs text-espresso-800/55">{{ textoPeriodo(p.desde, p.hasta) }}</p>
              </div>
              <dl class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                <div v-for="c in camposFicha.filter((c) => !c.soloEncargado || p.tipo === 'encargado')" :key="c.campo" class="flex justify-between gap-3 sm:block">
                  <dt class="text-xs text-espresso-800/50">{{ c.etiqueta }}</dt>
                  <dd class="text-espresso-800">{{ valorFicha(p, c.campo) }}</dd>
                </div>
              </dl>
              <p v-if="p.registrado_por" class="mt-3 text-xs text-espresso-800/45">Cambio registrado por {{ p.registrado_por }}</p>
            </div>
          </template>
        </div>
      </div>

      <div class="flex justify-end gap-2 rounded-b-2xl border-t border-gold-300/30 bg-cream-100/60 px-6 py-4">
        <template v-if="esPestanaDeDatos">
          <template v-if="soloLectura">
            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-espresso-700 hover:bg-brand-50" @click="emit('close')">
              Cerrar
            </button>
            <button
              v-if="puedeEditar"
              type="button"
              class="rounded-lg bg-gradient-to-r from-wine-500 via-brand-500 to-gold-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
              @click="activarEdicion"
            >
              Modificar
            </button>
          </template>
          <template v-else>
            <button
              type="button"
              class="rounded-lg px-4 py-2 text-sm font-medium text-espresso-700 hover:bg-brand-50"
              @click="esEdicion ? cancelarEdicion() : emit('close')"
            >
              Cancelar
            </button>
            <button
              type="button"
              :disabled="guardando"
              class="rounded-lg bg-gradient-to-r from-wine-500 via-brand-500 to-gold-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90 disabled:opacity-50"
              @click="guardar"
            >
              {{ guardando ? 'Guardando...' : 'Guardar' }}
            </button>
          </template>
        </template>
        <button v-else type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-espresso-700 hover:bg-brand-50" @click="emit('close')">
          Cerrar
        </button>
      </div>
    </div>

    <ObservacionModal v-if="movimientoDetalle" :movimiento="movimientoDetalle" @close="movimientoDetalle = null" />
  </div>
  </Teleport>
</template>
