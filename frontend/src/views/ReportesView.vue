<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import api from '../lib/api'
import type { VillaResumen, EstadoCuenta, MovimientoFila, Concepto } from '../types'
import { useDataStore } from '../stores/data'
import { useToastStore } from '../stores/toast'
import { formatearMonto } from '../lib/format'
import { hoyLocal, inicioDeMes } from '../lib/fecha'
import { formatearFecha } from '../lib/fechaFormato'
import FechaInput from '../components/FechaInput.vue'
import { abrirReporte, abrirRecibo, type FormatoExportacion } from '../lib/exportar'
import ExportarBotones from '../components/ExportarBotones.vue'
import VillaBuscador from '../components/VillaBuscador.vue'
import ObservacionModal from '../components/ObservacionModal.vue'

const dataStore = useDataStore()
const toast = useToastStore()

const tab = ref<'estado' | 'general' | 'antiguedad' | 'concepto'>('estado')

// fechas por defecto en la zona de la app (Tegucigalpa), no en UTC
function hoy(): string {
  return hoyLocal()
}

function primerDiaMesActual(): string {
  return inicioDeMes(hoyLocal())
}

// --- Estado de cuenta ---
const villaFiltro = ref('') // '' = todas
const desde = ref(primerDiaMesActual())
const hasta = ref(hoy())

watch(desde, (nuevo) => {
  if (hasta.value < nuevo) {
    hasta.value = nuevo
  }
})
const reportesEstado = ref<Array<{ villa: string; propietario: string; saldo_actual: number; estado_cuenta: EstadoCuenta }>>([])
const cargandoEstado = ref(false)
const movimientoDetalle = ref<MovimientoFila | null>(null)

function exportarEstadoCuenta(formato: FormatoExportacion) {
  abrirReporte('estado-cuenta', formato, { villa: villaFiltro.value, desde: desde.value, hasta: hasta.value })
}

async function generarEstadoCuenta() {
  cargandoEstado.value = true
  try {
    const { data } = await api.get('/api/reportes/estado-cuenta', {
      params: { villa: villaFiltro.value || undefined, desde: desde.value, hasta: hasta.value },
    })
    reportesEstado.value = data.data
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'No se pudo generar el estado de cuenta.')
  } finally {
    cargandoEstado.value = false
  }
}

// --- Saldos Generales / CXC / CXP ---
const hastaGeneral = ref(hoy())
const omitirAlDia = ref(false)
const omitirAFavor = ref(false)
const soloNegativos = ref(false)
const modoSaldos = ref<'general' | 'cxc' | 'cxp'>('general')
const cargandoGeneral = ref(false)
const saldos = ref<VillaResumen[]>([])
const totalGeneral = computed(() => saldos.value.reduce((acumulado, s) => acumulado + s.saldo, 0))

const tituloSaldos = computed(() => {
  if (modoSaldos.value === 'cxc') return 'Cuentas por Cobrar (CXC)'
  if (modoSaldos.value === 'cxp') return 'Cuentas por Pagar (CXP)'
  return 'Saldos Generales'
})
const etiquetaTotalSaldos = computed(() => {
  if (modoSaldos.value === 'cxc') return 'Total CXC'
  if (modoSaldos.value === 'cxp') return 'Total CXP'
  return 'Total general'
})

function verSaldosGenerales() {
  modoSaldos.value = 'general'
  omitirAlDia.value = false
  omitirAFavor.value = false
  soloNegativos.value = false
  generarSaldoGeneral()
}

function verReporteCxc() {
  modoSaldos.value = 'cxc'
  omitirAlDia.value = true
  omitirAFavor.value = true
  soloNegativos.value = false
  generarSaldoGeneral()
}

function verReporteCxp() {
  modoSaldos.value = 'cxp'
  soloNegativos.value = true
  generarSaldoGeneral()
}

async function generarSaldoGeneral() {
  cargandoGeneral.value = true
  try {
    const { data } = await api.get('/api/reportes/saldos-generales', {
      params: {
        hasta: hastaGeneral.value,
        omitir_al_dia: omitirAlDia.value,
        omitir_a_favor: omitirAFavor.value,
        solo_negativos: soloNegativos.value,
      },
    })
    saldos.value = data.data
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'No se pudo generar el reporte de saldos generales.')
  } finally {
    cargandoGeneral.value = false
  }
}

function exportarSaldos(formato: FormatoExportacion) {
  abrirReporte('saldos-generales', formato, {
    hasta: hastaGeneral.value,
    omitir_al_dia: omitirAlDia.value,
    omitir_a_favor: omitirAFavor.value,
    solo_negativos: soloNegativos.value,
  })
}

// --- Antigüedad de Saldos ---
const hastaAntiguedad = ref(hoy())
const cargandoAntiguedad = ref(false)
const antiguedad = ref<{
  filas: Array<{ villa: string; propietario: string; dias: number; bucket: string; saldo: number }>
  totales: Record<string, number>
  porcentajes: Record<string, number>
  total_saldo: number
} | null>(null)

async function generarAntiguedad() {
  cargandoAntiguedad.value = true
  try {
    const { data } = await api.get('/api/reportes/antiguedad-saldos', {
      params: { hasta: hastaAntiguedad.value },
    })
    antiguedad.value = data
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'No se pudo generar el reporte de antigüedad de saldos.')
  } finally {
    cargandoAntiguedad.value = false
  }
}

function exportarAntiguedad(formato: FormatoExportacion) {
  abrirReporte('antiguedad-saldos', formato, { hasta: hastaAntiguedad.value })
}

// --- Por concepto: movimientos de un concepto (cargo o crédito) en un rango de fechas ---
const conceptos = ref<Concepto[]>([])
const conceptoFiltro = ref<number | ''>('')
const villaConcepto = ref('') // '' = todas
const desdeConcepto = ref(primerDiaMesActual())
const hastaConcepto = ref(hoy())
const cargandoConcepto = ref(false)
const movimientosConcepto = ref<
  Array<{ id: number; fecha: string; folio: string | null; villa: string; propietario: string | null; descripcion: string | null; forma_pago: string | null; importe: number }>
>([])
const totalConcepto = ref(0)
/** Concepto del ultimo reporte generado (la tabla muestra la forma de pago solo si es credito). */
const conceptoGenerado = ref<{ DESCR: string; ES_CARGO: boolean } | null>(null)

const conceptosCargo = computed(() => conceptos.value.filter((c) => c.ES_CARGO))
const conceptosCredito = computed(() => conceptos.value.filter((c) => !c.ES_CARGO))

watch(desdeConcepto, (nuevo) => {
  if (hastaConcepto.value < nuevo) hastaConcepto.value = nuevo
})

async function cargarConceptos() {
  try {
    // incluye los inactivos: puede haber movimientos viejos de conceptos que ya no se usan
    const { data } = await api.get('/api/conceptos', { params: { incluir_inactivos: true } })
    conceptos.value = data.data
  } catch {
    toast.error('No se pudieron cargar los conceptos.')
  }
}

function paramsConcepto() {
  return { concepto: String(conceptoFiltro.value), villa: villaConcepto.value, desde: desdeConcepto.value, hasta: hastaConcepto.value }
}

async function generarPorConcepto() {
  if (conceptoFiltro.value === '') {
    toast.warning('Selecciona un concepto.')
    return
  }
  cargandoConcepto.value = true
  try {
    const p = paramsConcepto()
    const { data } = await api.get('/api/reportes/por-concepto', { params: { ...p, villa: p.villa || undefined } })
    movimientosConcepto.value = data.data
    totalConcepto.value = data.total
    conceptoGenerado.value = data.concepto
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'No se pudo generar el reporte por concepto.')
  } finally {
    cargandoConcepto.value = false
  }
}

function exportarPorConcepto(formato: FormatoExportacion) {
  if (conceptoFiltro.value === '') {
    toast.warning('Selecciona un concepto.')
    return
  }
  abrirReporte('por-concepto', formato, paramsConcepto())
}

async function cargarTodo() {
  await generarEstadoCuenta()
  await generarSaldoGeneral()
  await generarAntiguedad()
}

onMounted(() => {
  cargarTodo()
  cargarConceptos()
})

// se refresca sola si se aplica un cargo/abono o se crea/edita una villa
// desde otra pantalla (ej. el modal "Cargo/Crédito" del menú superior)
watch(() => dataStore.version, () => {
  cargarTodo()
  if (conceptoGenerado.value) generarPorConcepto()
})
</script>

<template>
  <div>
    <p class="mb-4 font-display text-2xl font-semibold text-espresso-800">Reportes</p>

    <div class="mb-4 flex gap-4 border-b border-gold-300/30 text-sm">
      <button
        class="border-b-2 px-1 pb-2 font-medium"
        :class="tab === 'estado' ? 'border-brand-600 text-brand-700' : 'border-transparent text-espresso-800/40'"
        @click="tab = 'estado'"
      >
        Estado de Cuenta
      </button>
      <button
        class="border-b-2 px-1 pb-2 font-medium"
        :class="tab === 'general' ? 'border-brand-600 text-brand-700' : 'border-transparent text-espresso-800/40'"
        @click="tab = 'general'"
      >
        Saldos Generales
      </button>
      <button
        class="border-b-2 px-1 pb-2 font-medium"
        :class="tab === 'antiguedad' ? 'border-brand-600 text-brand-700' : 'border-transparent text-espresso-800/40'"
        @click="tab = 'antiguedad'"
      >
        Antigüedad de Saldos
      </button>
      <button
        class="border-b-2 px-1 pb-2 font-medium"
        :class="tab === 'concepto' ? 'border-brand-600 text-brand-700' : 'border-transparent text-espresso-800/40'"
        @click="tab = 'concepto'"
      >
        Por Concepto
      </button>
    </div>

    <!-- Por concepto -->
    <section v-if="tab === 'concepto'">
      <form class="mb-6 space-y-3" @submit.prevent="generarPorConcepto">
        <div class="flex flex-wrap items-end gap-3">
          <div class="w-full max-w-xs">
            <label class="mb-1 block text-sm font-medium text-espresso-700">Concepto</label>
            <select v-model="conceptoFiltro" class="w-full rounded-lg border border-espresso-800/15 bg-cream-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
              <option value="" disabled>Selecciona un concepto</option>
              <optgroup label="Cargos">
                <option v-for="c in conceptosCargo" :key="c.NUM_CPTO" :value="c.NUM_CPTO">{{ c.DESCR }}{{ c.ACTIVO ? '' : ' (inactivo)' }}</option>
              </optgroup>
              <optgroup label="Créditos">
                <option v-for="c in conceptosCredito" :key="c.NUM_CPTO" :value="c.NUM_CPTO">{{ c.DESCR }}{{ c.ACTIVO ? '' : ' (inactivo)' }}</option>
              </optgroup>
            </select>
          </div>
          <div class="w-full max-w-sm">
            <label class="mb-1 block text-sm font-medium text-espresso-700">Villa</label>
            <VillaBuscador v-model="villaConcepto" con-opcion-todas />
          </div>
        </div>
        <div class="flex flex-wrap items-end gap-3">
          <div>
            <label class="mb-1 block text-sm font-medium text-espresso-700">Desde</label>
            <FechaInput v-model="desdeConcepto" class="w-40 rounded-lg border border-espresso-800/15 bg-cream-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-espresso-700">Hasta</label>
            <FechaInput v-model="hastaConcepto" :min="desdeConcepto" class="w-40 rounded-lg border border-espresso-800/15 bg-cream-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200" />
          </div>
          <button type="submit" class="rounded-lg bg-gradient-to-r from-wine-500 via-brand-500 to-gold-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">
            Generar
          </button>
          <ExportarBotones @exportar="exportarPorConcepto" />
        </div>
      </form>

      <p v-if="cargandoConcepto" class="text-espresso-800/40">Cargando...</p>

      <div v-if="conceptoGenerado" class="rounded-xl border border-gold-300/30 bg-cream-50 p-5 shadow-sm">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-gold-300/20 pb-3">
          <h2 class="font-display font-semibold text-espresso-800">
            {{ conceptoGenerado.DESCR }}
            <span class="ml-1 rounded-full px-2 py-0.5 text-xs font-medium" :class="conceptoGenerado.ES_CARGO ? 'bg-brand-100 text-brand-800' : 'bg-emerald-100 text-emerald-800'">
              {{ conceptoGenerado.ES_CARGO ? 'Cargo' : 'Crédito' }}
            </span>
          </h2>
          <p class="font-display font-semibold text-espresso-800">
            Total: {{ formatearMonto(totalConcepto) }}
            <span class="text-sm font-normal text-espresso-800/55">· {{ movimientosConcepto.length }} {{ movimientosConcepto.length === 1 ? 'movimiento' : 'movimientos' }}</span>
          </p>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gold-300/20 text-sm">
            <thead>
              <tr class="text-left text-espresso-800/50">
                <th class="py-1.5 pr-2">Fecha</th>
                <th class="py-1.5 pr-2">Folio</th>
                <th class="py-1.5 pr-2">Villa</th>
                <th class="py-1.5 pr-2">Propietario</th>
                <th class="py-1.5 pr-2">Descripción</th>
                <th v-if="!conceptoGenerado.ES_CARGO" class="py-1.5 pr-2">Forma de pago</th>
                <th class="py-1.5 pr-2 text-right">Importe</th>
                <th class="py-1.5"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gold-300/15">
              <tr v-for="m in movimientosConcepto" :key="m.id" class="hover:bg-brand-50/40">
                <td class="whitespace-nowrap py-1.5 pr-2">{{ formatearFecha(m.fecha) }}</td>
                <td class="whitespace-nowrap py-1.5 pr-2 font-mono text-xs">{{ m.folio ?? '—' }}</td>
                <td class="py-1.5 pr-2 font-medium">{{ m.villa }}</td>
                <td class="py-1.5 pr-2">{{ m.propietario }}</td>
                <td class="py-1.5 pr-2 text-espresso-800/80">{{ m.descripcion }}</td>
                <td v-if="!conceptoGenerado.ES_CARGO" class="py-1.5 pr-2">{{ m.forma_pago ?? '—' }}</td>
                <td class="whitespace-nowrap py-1.5 pr-2 text-right font-medium">{{ formatearMonto(m.importe) }}</td>
                <td class="py-1.5 text-right">
                  <button type="button" class="text-xs font-medium text-brand-700 hover:underline" @click="abrirRecibo(m.id)">Recibo</button>
                </td>
              </tr>
              <tr v-if="movimientosConcepto.length === 0">
                <td :colspan="conceptoGenerado.ES_CARGO ? 7 : 8" class="py-8 text-center text-espresso-800/40">Sin movimientos de este concepto en el rango</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- Estado de cuenta -->
    <section v-if="tab === 'estado'">
      <form class="mb-6 space-y-3" @submit.prevent="generarEstadoCuenta">
        <div class="max-w-sm">
          <label class="mb-1 block text-sm font-medium text-espresso-700">Villa</label>
          <VillaBuscador v-model="villaFiltro" con-opcion-todas />
        </div>
        <div class="flex flex-wrap items-end gap-3">
          <div>
            <label class="mb-1 block text-sm font-medium text-espresso-700">Desde</label>
            <FechaInput v-model="desde" class="w-40 rounded-lg border border-espresso-800/15 bg-cream-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-espresso-700">Hasta</label>
            <FechaInput v-model="hasta" :min="desde" class="w-40 rounded-lg border border-espresso-800/15 bg-cream-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200" />
          </div>
          <button type="submit" class="rounded-lg bg-gradient-to-r from-wine-500 via-brand-500 to-gold-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">
            Generar
          </button>
          <ExportarBotones @exportar="exportarEstadoCuenta" />
        </div>
      </form>

      <p v-if="cargandoEstado" class="text-espresso-800/40">Cargando...</p>

      <div v-for="r in reportesEstado" :key="r.villa" class="mb-8 rounded-xl border border-gold-300/30 bg-cream-50 p-5 shadow-sm">
        <div class="mb-3 flex items-center justify-between border-b border-gold-300/20 pb-3">
          <h2 class="font-display font-semibold text-espresso-800">Villa #{{ r.villa }} — {{ r.propietario }}</h2>
          <p class="font-display font-semibold text-espresso-800">Saldo a la fecha: {{ formatearMonto(r.saldo_actual) }}</p>
        </div>
        <table class="min-w-full divide-y divide-gold-300/20 text-sm">
          <thead>
            <tr class="text-left text-espresso-800/50">
              <th class="py-1.5 pr-2">Fecha</th>
              <th class="py-1.5 pr-2">Descripción</th>
              <th class="py-1.5 pr-2 text-right">Cargo</th>
              <th class="py-1.5 pr-2 text-right">Crédito</th>
              <th class="py-1.5 text-right">Saldo</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gold-300/15">
            <tr>
              <td colspan="4" class="py-1.5 pr-2 text-espresso-800/50">Saldo inicial</td>
              <td class="py-1.5 text-right font-medium">{{ formatearMonto(r.estado_cuenta.saldo_inicial) }}</td>
            </tr>
            <tr
              v-for="(m, i) in r.estado_cuenta.movimientos"
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
        </table>
      </div>
    </section>

    <ObservacionModal v-if="movimientoDetalle" :movimiento="movimientoDetalle" @close="movimientoDetalle = null" />

    <!-- Saldos Generales / CXC / CXP -->
    <section v-if="tab === 'general'">
      <p class="mb-3 font-display text-sm font-semibold uppercase tracking-wide text-espresso-800/70">
        {{ tituloSaldos }}
      </p>

      <div class="mb-3 flex flex-wrap gap-2">
        <button
          type="button"
          class="rounded-lg border px-3 py-1.5 text-sm font-medium"
          :class="modoSaldos === 'general' ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-espresso-800/20 text-espresso-700 hover:bg-brand-50'"
          @click="verSaldosGenerales"
        >
          Saldos Generales
        </button>
        <button
          type="button"
          class="rounded-lg border px-3 py-1.5 text-sm font-medium"
          :class="modoSaldos === 'cxc' ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-espresso-800/20 text-espresso-700 hover:bg-brand-50'"
          @click="verReporteCxc"
        >
          Reporte CXC
        </button>
        <button
          type="button"
          class="rounded-lg border px-3 py-1.5 text-sm font-medium"
          :class="modoSaldos === 'cxp' ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-espresso-800/20 text-espresso-700 hover:bg-brand-50'"
          @click="verReporteCxp"
        >
          Reporte CXP
        </button>
      </div>

      <form class="mb-6 flex flex-wrap items-end gap-4" @submit.prevent="generarSaldoGeneral">
        <div>
          <label class="mb-1 block text-sm font-medium text-espresso-700">Hasta</label>
          <FechaInput v-model="hastaGeneral" class="w-40 rounded-lg border border-espresso-800/15 bg-cream-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200" />
        </div>
        <label v-if="modoSaldos === 'general'" class="flex items-center gap-2 text-sm text-espresso-700">
          <input v-model="omitirAlDia" type="checkbox" class="rounded border-espresso-800/25 text-brand-600 focus:ring-brand-400" />
          Omitir saldos al día
        </label>
        <label v-if="modoSaldos === 'general'" class="flex items-center gap-2 text-sm text-espresso-700">
          <input v-model="omitirAFavor" type="checkbox" class="rounded border-espresso-800/25 text-brand-600 focus:ring-brand-400" />
          Omitir saldos a favor
        </label>
        <button type="submit" class="rounded-lg bg-gradient-to-r from-wine-500 via-brand-500 to-gold-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">
          Generar
        </button>
        <ExportarBotones @exportar="exportarSaldos" />
      </form>

      <p v-if="cargandoGeneral" class="text-espresso-800/40">Cargando...</p>

      <div class="overflow-hidden rounded-xl border border-gold-300/30 bg-cream-50 shadow-sm">
        <table class="min-w-full divide-y divide-gold-300/20 text-sm">
          <thead class="bg-brand-50/60">
            <tr>
              <th class="px-4 py-2 text-left font-medium text-espresso-800/70">Villa</th>
              <th class="px-4 py-2 text-right font-medium text-espresso-800/70">Saldo</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gold-300/15">
            <tr v-for="s in saldos" :key="s.villa">
              <td class="px-4 py-2">{{ s.villa }}</td>
              <td class="px-4 py-2 text-right font-medium" :class="s.saldo > 0 ? 'text-wine-600' : s.saldo < 0 ? 'text-emerald-600' : 'text-espresso-800/40'">
                {{ formatearMonto(s.saldo) }}
              </td>
            </tr>
            <tr v-if="!cargandoGeneral && saldos.length === 0">
              <td colspan="2" class="px-4 py-8 text-center text-espresso-800/40">Sin resultados</td>
            </tr>
          </tbody>
          <tfoot v-if="saldos.length > 0">
            <tr class="border-t border-gold-300/40 text-espresso-800">
              <td class="px-4 py-3 font-display text-lg font-semibold">{{ etiquetaTotalSaldos }}</td>
              <td class="px-4 py-3 text-right font-display text-2xl font-bold">{{ formatearMonto(totalGeneral) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>

    <!-- Antigüedad de Saldos -->
    <section v-if="tab === 'antiguedad'">
      <p class="mb-3 font-display text-sm font-semibold uppercase tracking-wide text-espresso-800/70">
        Antigüedad de Saldos
      </p>

      <form class="mb-6 flex flex-wrap items-end gap-4" @submit.prevent="generarAntiguedad">
        <div>
          <label class="mb-1 block text-sm font-medium text-espresso-700">Hasta</label>
          <FechaInput v-model="hastaAntiguedad" class="w-40 rounded-lg border border-espresso-800/15 bg-cream-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200" />
        </div>
        <button type="submit" class="rounded-lg bg-gradient-to-r from-wine-500 via-brand-500 to-gold-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">
          Generar
        </button>
        <ExportarBotones @exportar="exportarAntiguedad" />
      </form>

      <p v-if="cargandoAntiguedad" class="text-espresso-800/40">Cargando...</p>

      <div v-if="antiguedad" class="overflow-x-auto rounded-xl border border-gold-300/30 bg-cream-50 shadow-sm">
        <table class="min-w-full divide-y divide-gold-300/20 text-sm">
          <thead class="bg-brand-50/60">
            <tr>
              <th class="px-4 py-2 text-left font-medium text-espresso-800/70">Villa</th>
              <th class="px-4 py-2 text-left font-medium text-espresso-800/70">Propietario</th>
              <th class="px-4 py-2 text-right font-medium text-espresso-800/70">+90 días</th>
              <th class="px-4 py-2 text-right font-medium text-espresso-800/70">90 días</th>
              <th class="px-4 py-2 text-right font-medium text-espresso-800/70">60 días</th>
              <th class="px-4 py-2 text-right font-medium text-espresso-800/70">30 días</th>
              <th class="px-4 py-2 text-right font-medium text-espresso-800/70">Saldo</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gold-300/15">
            <tr v-for="f in antiguedad.filas" :key="f.villa">
              <td class="px-4 py-2">{{ f.villa }}</td>
              <td class="px-4 py-2">{{ f.propietario }}</td>
              <td class="px-4 py-2 text-right">{{ f.bucket === 'd90mas' ? formatearMonto(f.saldo) : '' }}</td>
              <td class="px-4 py-2 text-right">{{ f.bucket === 'd90' ? formatearMonto(f.saldo) : '' }}</td>
              <td class="px-4 py-2 text-right">{{ f.bucket === 'd60' ? formatearMonto(f.saldo) : '' }}</td>
              <td class="px-4 py-2 text-right">{{ f.bucket === 'd30' ? formatearMonto(f.saldo) : '' }}</td>
              <td class="px-4 py-2 text-right font-medium">{{ formatearMonto(f.saldo) }}</td>
            </tr>
            <tr v-if="!cargandoAntiguedad && antiguedad.filas.length === 0">
              <td colspan="7" class="px-4 py-8 text-center text-espresso-800/40">Ninguna villa con saldo pendiente</td>
            </tr>
          </tbody>
          <tfoot v-if="antiguedad.filas.length > 0">
            <tr class="border-t border-gold-300/40 font-semibold text-espresso-800">
              <td colspan="2" class="px-4 py-3 font-display text-base">Totales</td>
              <td class="px-4 py-3 text-right">{{ formatearMonto(antiguedad.totales.d90mas) }}</td>
              <td class="px-4 py-3 text-right">{{ formatearMonto(antiguedad.totales.d90) }}</td>
              <td class="px-4 py-3 text-right">{{ formatearMonto(antiguedad.totales.d60) }}</td>
              <td class="px-4 py-3 text-right">{{ formatearMonto(antiguedad.totales.d30) }}</td>
              <td class="px-4 py-3 text-right font-display text-2xl font-bold">{{ formatearMonto(antiguedad.total_saldo) }}</td>
            </tr>
            <tr class="text-espresso-800/50">
              <td colspan="2" class="px-4 py-2">Porcentajes</td>
              <td class="px-4 py-2 text-right">{{ antiguedad.porcentajes.d90mas }}%</td>
              <td class="px-4 py-2 text-right">{{ antiguedad.porcentajes.d90 }}%</td>
              <td class="px-4 py-2 text-right">{{ antiguedad.porcentajes.d60 }}%</td>
              <td class="px-4 py-2 text-right">{{ antiguedad.porcentajes.d30 }}%</td>
              <td class="px-4 py-2 text-right">100%</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>
  </div>
</template>
