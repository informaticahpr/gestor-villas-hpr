<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import api from '../lib/api'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import { useDataStore } from '../stores/data'
import { mensajeDeError } from '../lib/errors'
import { useEscapeKey } from '../lib/useEscapeKey'
import { hoyLocal, hoyServidor } from '../lib/fecha'
import { formatearFecha } from '../lib/fechaFormato'
import { abrirRecibo } from '../lib/exportar'
import VillaBuscador from './VillaBuscador.vue'
import FechaInput from './FechaInput.vue'
import type { VillaResumen, Concepto, FormaPago } from '../types'

const emit = defineEmits<{ close: []; saved: [] }>()

const auth = useAuthStore()
const toast = useToastStore()
const dataStore = useDataStore()

const villas = ref<VillaResumen[]>([])
const conceptos = ref<Concepto[]>([])
const formasPago = ref<FormaPago[]>([])
const guardando = ref(false)

const tipo = ref<'cargo' | 'credito'>('cargo')
const modo = ref<'individual' | 'todas'>('individual')

// La fecha del movimiento NO se elige: la fija el servidor (hoy, zona de Tegucigalpa) y aqui solo se
// muestra. Arranca con el reloj del navegador (ya en esa zona) y al abrir el modal se corrige con la
// fecha del servidor. Lo unico que elige el usuario es el vencimiento, que no puede ser anterior a hoy.
const hoyISO = ref(hoyLocal())

const form = reactive({
  FECHA_VENC: '',
  CLV_CLIE: '',
  IMPORTE: 0,
  NUM_CPTO: null as number | null,
  FORMA_PAGO_ID: null as number | null,
  OBS: '',
})

// --- modo "todas" (cuota mensual) ---
const villasConAplicobro = computed(() => villas.value.filter((v) => v.aplicobro))
// la cuota especial de una villa solo reemplaza a la cuota de mantenimiento, no a otros conceptos
const villasConCuotaEspecialEnTodas = computed(() =>
  conceptoSeleccionado.value?.ES_MANTENIMIENTO
    ? villasConAplicobro.value.filter((v) => v.cuota_especial && v.monto_cuota_especial !== null)
    : [],
)

watch(tipo, (nuevo) => {
  if (nuevo === 'credito' && modo.value === 'todas') {
    modo.value = 'individual'
  }
  form.NUM_CPTO = null
  form.FORMA_PAGO_ID = null
})

// --- conceptos ---
const conceptosFiltrados = computed(() =>
  conceptos.value.filter((c) => c.ES_CARGO === (tipo.value === 'cargo')),
)

// --- monto fijo por concepto (lo controla Admin/Director en Configuración) ---
const conceptoSeleccionado = computed(() => conceptos.value.find((c) => c.NUM_CPTO === form.NUM_CPTO) ?? null)
const villaSeleccionada = computed(() => villas.value.find((v) => v.villa === form.CLV_CLIE) ?? null)

// La cuota de mantenimiento la fija Director/Admin en Configuración → Cuotas y nadie la escribe aquí.
// Si la villa elegida tiene cuota especial, paga ese monto en vez de la cuota mensual.
const usaCuotaEspecialDeLaVilla = computed(
  () =>
    Boolean(conceptoSeleccionado.value?.ES_MANTENIMIENTO) &&
    modo.value === 'individual' &&
    Boolean(villaSeleccionada.value?.cuota_especial) &&
    villaSeleccionada.value?.monto_cuota_especial !== null,
)

const montoBloqueado = computed(() => {
  const concepto = conceptoSeleccionado.value
  if (!concepto || concepto.MONTO_DEFAULT === null) return null
  if (usaCuotaEspecialDeLaVilla.value) return villaSeleccionada.value!.monto_cuota_especial
  return concepto.MONTO_DEFAULT
})

// mientras no se defina la cuota mensual, el cargo de mantenimiento no se puede aplicar
const mantenimientoSinConfigurar = computed(
  () => Boolean(conceptoSeleccionado.value?.ES_MANTENIMIENTO) && conceptoSeleccionado.value?.MONTO_DEFAULT === null,
)

watch(
  montoBloqueado,
  (nuevo) => {
    if (nuevo !== null) {
      form.IMPORTE = nuevo
    }
  },
  { immediate: true },
)

const mostrarNuevoConcepto = ref(false)
const nuevoConceptoDescr = ref('')
const creandoConcepto = ref(false)

async function crearConcepto() {
  if (!nuevoConceptoDescr.value.trim()) return
  creandoConcepto.value = true
  try {
    const { data } = await api.post('/api/conceptos', {
      DESCR: nuevoConceptoDescr.value.trim(),
      ES_CARGO: tipo.value === 'cargo',
    })
    conceptos.value.push(data.concepto)
    form.NUM_CPTO = data.concepto.NUM_CPTO
    nuevoConceptoDescr.value = ''
    mostrarNuevoConcepto.value = false
    toast.success(`Concepto "${data.concepto.DESCR}" creado.`)
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'No se pudo crear el concepto.')
  } finally {
    creandoConcepto.value = false
  }
}

async function cargarListas() {
  const [resVillas, resConceptos, resFormasPago] = await Promise.all([
    api.get('/api/villas'),
    api.get('/api/conceptos'),
    api.get('/api/formas-pago'),
  ])
  villas.value = resVillas.data.data
  conceptos.value = resConceptos.data.data
  formasPago.value = resFormasPago.data.data
}

async function guardar() {
  if (modo.value === 'individual' && !form.CLV_CLIE) {
    toast.warning('Busca y selecciona una villa.')
    return
  }
  if (!form.NUM_CPTO) {
    toast.warning('Selecciona un concepto.')
    return
  }
  if (mantenimientoSinConfigurar.value) {
    toast.warning('La cuota de mantenimiento aún no está configurada. Un Director o Administrador debe definirla en Configuración → Cuotas.')
    return
  }
  if (tipo.value === 'cargo' && !form.FECHA_VENC) {
    toast.warning('Indica la fecha de vencimiento del cargo.')
    return
  }
  if (form.FECHA_VENC && form.FECHA_VENC < hoyISO.value) {
    toast.warning('La fecha de vencimiento no puede ser anterior a hoy.')
    return
  }
  if (tipo.value === 'credito' && !form.FORMA_PAGO_ID) {
    toast.warning('Selecciona la forma de pago.')
    return
  }

  guardando.value = true
  try {
    if (modo.value === 'todas') {
      const { data } = await api.post('/api/movimientos/aplicar-a-todas', {
        NUM_CPTO: form.NUM_CPTO,
        IMPORTE: form.IMPORTE,
        FECHA_VENC: form.FECHA_VENC || null,
        OBS: form.OBS || null,
      })
      const detalleCuotaEspecial = data.villas_con_cuota_especial > 0
        ? ` (${data.villas_con_cuota_especial} con cuota especial, a su monto asignado)`
        : ''
      toast.success(`Cargo aplicado a ${data.villas_afectadas} villa(s)${detalleCuotaEspecial}.`)
    } else {
      const { data } = await api.post('/api/movimientos', {
        CLV_CLIE: form.CLV_CLIE,
        NUM_CPTO: form.NUM_CPTO,
        FORMA_PAGO_ID: tipo.value === 'credito' ? form.FORMA_PAGO_ID : null,
        IMPORTE: form.IMPORTE,
        FECHA_VENC: form.FECHA_VENC || null,
        OBS: form.OBS || null,
      })
      toast.success('Movimiento aplicado correctamente.')
      abrirRecibo(data.movimiento.ID_MOV)
    }
    dataStore.tocar()
    emit('saved')
    emit('close')
  } catch (e: any) {
    toast.error(mensajeDeError(e, 'No se pudo aplicar el movimiento.'))
  } finally {
    guardando.value = false
  }
}

onMounted(async () => {
  hoyISO.value = await hoyServidor()
})
onMounted(cargarListas)

// Escape hace lo mismo que el boton Cancelar.
useEscapeKey(() => emit('close'))
</script>

<template>
  <Teleport to="body">
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-espresso-900/50 p-4 backdrop-blur-sm">
    <div class="flex max-h-[90vh] w-full max-w-lg flex-col rounded-2xl bg-cream-50 shadow-2xl shadow-espresso-900/20">
      <div class="rounded-t-2xl border-b border-gold-300/30 bg-gradient-to-r from-brand-50/70 to-cream-50 px-6 py-4">
        <h2 class="font-display text-lg font-semibold text-espresso-800">Cargo / Crédito</h2>
      </div>

      <form class="space-y-5 overflow-y-auto px-6 py-4" @submit.prevent="guardar">
        <div class="flex gap-4 text-sm">
          <label class="flex items-center gap-2 text-espresso-700">
            <input v-model="tipo" type="radio" value="cargo" class="text-brand-600 focus:ring-brand-400" />
            Cargo
          </label>
          <label class="flex items-center gap-2 text-espresso-700">
            <input v-model="tipo" type="radio" value="credito" class="text-brand-600 focus:ring-brand-400" />
            Crédito
          </label>
        </div>

        <!-- El concepto se elige justo después de Cargo/Crédito: define el monto (si es fijo) y el tipo de aplicación -->
        <div>
          <div class="mb-1.5 flex items-center justify-between">
            <label class="block text-sm font-medium text-espresso-700">Concepto</label>
            <button
              v-if="auth.esDirectorOAdmin()"
              type="button"
              class="text-xs font-medium text-brand-700 hover:text-brand-800"
              @click="mostrarNuevoConcepto = !mostrarNuevoConcepto"
            >
              {{ mostrarNuevoConcepto ? 'Cancelar' : '+ Nuevo concepto' }}
            </button>
          </div>

          <div v-if="mostrarNuevoConcepto && auth.esDirectorOAdmin()" class="mb-2 flex gap-2 rounded-lg border border-gold-300/40 bg-brand-50/40 p-2">
            <input
              v-model="nuevoConceptoDescr"
              type="text"
              :placeholder="tipo === 'cargo' ? 'Ej. Mora por atraso' : 'Ej. Devolución de depósito'"
              class="w-full rounded-md border border-espresso-800/15 bg-white px-2.5 py-1.5 text-sm placeholder:text-espresso-800/30 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
            />
            <button
              type="button"
              :disabled="creandoConcepto || !nuevoConceptoDescr.trim()"
              class="shrink-0 rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
              @click="crearConcepto"
            >
              Crear
            </button>
          </div>

          <select v-model.number="form.NUM_CPTO" required class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
            <option :value="null" disabled>Selecciona un concepto</option>
            <option v-for="c in conceptosFiltrados" :key="c.NUM_CPTO" :value="c.NUM_CPTO">
              {{ c.DESCR }}
            </option>
          </select>
          <p v-if="mantenimientoSinConfigurar" class="mt-1.5 text-xs text-wine-600">
            La cuota de mantenimiento aún no está configurada. Un Director o Administrador debe definirla en Configuración → Cuotas.
          </p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-espresso-700">Fecha</label>
            <input
              :value="formatearFecha(hoyISO)"
              type="text"
              readonly
              tabindex="-1"
              title="La fecha del movimiento la fija el sistema (hoy) y no se puede cambiar"
              class="w-full cursor-not-allowed rounded-lg border border-espresso-800/15 bg-cream-200 px-3 py-2.5 text-sm text-espresso-800/70 focus:outline-none"
            />
            <p class="mt-1 text-xs text-espresso-800/50">La fija el sistema (hoy).</p>
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-espresso-700">
              Fecha de vencimiento
              <span v-if="tipo === 'cargo'" class="text-wine-600" title="Obligatoria en los cargos">*</span>
            </label>
            <FechaInput v-model="form.FECHA_VENC" :min="hoyISO" :required="tipo === 'cargo'" class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200" />
          </div>
        </div>

        <div>
          <div class="mb-1.5 flex gap-4 border-b border-espresso-800/10 text-sm">
            <button
              type="button"
              class="border-b-2 pb-1.5 font-medium"
              :class="modo === 'individual' ? 'border-brand-600 text-brand-700' : 'border-transparent text-espresso-800/40'"
              @click="modo = 'individual'"
            >
              Villa individual
            </button>
            <button
              v-if="tipo === 'cargo'"
              type="button"
              class="border-b-2 pb-1.5 font-medium"
              :class="modo === 'todas' ? 'border-brand-600 text-brand-700' : 'border-transparent text-espresso-800/40'"
              @click="modo = 'todas'"
            >
              Todas (cuota mensual)
            </button>
          </div>

          <div v-if="modo === 'individual'" class="pt-2">
            <VillaBuscador v-model="form.CLV_CLIE" />
          </div>

          <div v-else class="space-y-2">
            <div class="rounded-lg border border-gold-300/40 bg-brand-50/50 px-3 py-2.5 text-sm text-espresso-700">
              Se aplicará este cargo a
              <strong>{{ villasConAplicobro.length }} villa{{ villasConAplicobro.length === 1 ? '' : 's' }}</strong>
              con "Aplicar cuota mensual" activado.
            </div>
            <div v-if="villasConCuotaEspecialEnTodas.length > 0" class="rounded-lg border border-gold-300/40 bg-cream-100 px-3 py-2.5 text-sm text-espresso-700/80">
              <strong>{{ villasConCuotaEspecialEnTodas.length }}</strong> de ellas
              {{ villasConCuotaEspecialEnTodas.length === 1 ? 'tiene cuota especial' : 'tienen cuota especial' }}
              y no pagarán el valor de abajo — se les cobrará el monto asignado en Configuración → Cuotas → Cuotas especiales.
            </div>
          </div>
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-espresso-700">Valor</label>
          <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-espresso-800/40">$</span>
            <input
              v-model.number="form.IMPORTE"
              type="number"
              step="0.01"
              min="0.01"
              required
              :disabled="montoBloqueado !== null"
              class="w-full rounded-lg border border-espresso-800/15 bg-white py-2.5 pl-7 pr-3 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200 disabled:bg-cream-200 disabled:text-espresso-800/60"
            />
          </div>
          <p v-if="montoBloqueado !== null" class="mt-1.5 text-xs text-espresso-800/50">
            {{ conceptoSeleccionado?.ES_MANTENIMIENTO ? 'Cuota de mantenimiento' : 'Monto fijo' }} definido en Configuración{{ usaCuotaEspecialDeLaVilla ? ' (cuota especial de esta villa)' : '' }} — no se puede modificar aquí.
          </p>
        </div>

        <div v-if="tipo === 'credito'">
          <label class="mb-1.5 block text-sm font-medium text-espresso-700">Forma de pago</label>
          <select v-model.number="form.FORMA_PAGO_ID" required class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
            <option :value="null" disabled>Selecciona una forma de pago</option>
            <option v-for="f in formasPago" :key="f.id" :value="f.id">
              {{ f.nombre }}
            </option>
          </select>
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-espresso-700">Descripción</label>
          <textarea v-model="form.OBS" rows="2" class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200" />
        </div>

        <div class="flex justify-end gap-2 border-t border-gold-300/30 pt-4">
          <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-espresso-700 hover:bg-brand-50" @click="emit('close')">
            Cerrar
          </button>
          <button
            type="submit"
            :disabled="guardando || mantenimientoSinConfigurar"
            class="rounded-lg bg-gradient-to-r from-wine-500 via-brand-500 to-gold-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90 disabled:opacity-50"
          >
            {{ guardando ? 'Aplicando...' : modo === 'todas' ? `Aplicar a ${villasConAplicobro.length} villas` : 'Aplicar' }}
          </button>
        </div>
      </form>
    </div>
  </div>
  </Teleport>
</template>
