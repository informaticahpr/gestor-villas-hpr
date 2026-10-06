<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import api from '../lib/api'
import { formatearMonto } from '../lib/format'
import { formatearFecha } from '../lib/fechaFormato'
import { abrirRecibo } from '../lib/exportar'
import { mensajeDeError } from '../lib/errors'
import { useToastStore } from '../stores/toast'
import { useDataStore } from '../stores/data'
import { useAuthStore } from '../stores/auth'
import { useEscapeKey } from '../lib/useEscapeKey'
import FechaInput from '../components/FechaInput.vue'
import VillaBuscador from '../components/VillaBuscador.vue'
import PaginacionControles from '../components/PaginacionControles.vue'
import type { MovimientoListado, MetaPaginacion } from '../types'

const toast = useToastStore()
const dataStore = useDataStore()
const auth = useAuthStore()

const movimientos = ref<MovimientoListado[]>([])
const cargando = ref(false)

const pagina = ref(1)
const porPagina = ref(10)
const meta = ref<MetaPaginacion>({ pagina: 1, por_pagina: 10, total: 0, ultima_pagina: 1 })

// formulario de filtros
const filtroVilla = ref('') // '' = todas
const filtroTipo = ref<'' | 'cargo' | 'credito'>('')
const filtroCorrelativo = ref('')
const filtroDesde = ref('')
const filtroHasta = ref('')

// Filtros "aplicados": lo que se ve en la tabla. Cambiar de pagina o de tamaño usa este resumen y no lo
// que haya escrito la persona en el formulario sin pulsar "Buscar".
type Filtros = Record<'villa' | 'tipo' | 'q' | 'desde' | 'hasta', string | undefined>
const filtrosAplicados = ref<Filtros>({ villa: undefined, tipo: undefined, q: undefined, desde: undefined, hasta: undefined })

async function cargar() {
  cargando.value = true
  try {
    const { data } = await api.get('/api/movimientos', {
      params: { ...filtrosAplicados.value, pagina: pagina.value, por_pagina: porPagina.value },
    })
    movimientos.value = data.data
    meta.value = data.meta

    // si ya no existe la pagina pedida, vuelve a la ultima
    if (data.data.length === 0 && data.meta.total > 0 && pagina.value > data.meta.ultima_pagina) {
      pagina.value = data.meta.ultima_pagina
      await cargar()
    }
  } catch (e: any) {
    toast.error(mensajeDeError(e, 'No se pudieron cargar los movimientos.'))
  } finally {
    cargando.value = false
  }
}

function buscar() {
  filtrosAplicados.value = {
    villa: filtroVilla.value || undefined,
    tipo: filtroTipo.value || undefined,
    q: filtroCorrelativo.value.trim() || undefined,
    desde: filtroDesde.value || undefined,
    hasta: filtroHasta.value || undefined,
  }
  pagina.value = 1
  cargar()
}

function limpiar() {
  filtroVilla.value = ''
  filtroTipo.value = ''
  filtroCorrelativo.value = ''
  filtroDesde.value = ''
  filtroHasta.value = ''
  buscar()
}

function irAPagina(nueva: number) {
  pagina.value = nueva
  cargar()
}

function cambiarPorPagina(nuevo: number) {
  porPagina.value = nuevo
  pagina.value = 1
  cargar()
}

onMounted(cargar)

// se refresca si se aplica un cargo/abono desde el modal del menu superior
watch(() => dataStore.version, cargar)

// --- Anulacion (solo Director/Admin; el backend tambien lo restringe) ---
// El movimiento no se borra: queda en esta lista marcado ANULADO, pero deja de contar en saldos,
// estado de cuenta, reportes y dashboard. El correcto se vuelve a capturar desde Cargo/Crédito.
const porAnular = ref<MovimientoListado | null>(null)
const motivo = ref('')
const anulando = ref(false)

function pedirAnulacion(m: MovimientoListado) {
  porAnular.value = m
  motivo.value = ''
}

function cerrarAnulacion() {
  if (anulando.value) return
  porAnular.value = null
}

useEscapeKey(() => {
  if (!porAnular.value) return false
  cerrarAnulacion()
  return true
})

async function confirmarAnulacion() {
  if (!porAnular.value || motivo.value.trim().length < 5) return
  anulando.value = true
  try {
    await api.patch(`/api/movimientos/${porAnular.value.id}/anular`, { motivo: motivo.value.trim() })
    toast.success(`Correlativo No. ${porAnular.value.correlativo ?? porAnular.value.id} anulado. Ya no cuenta en el saldo de la villa ${porAnular.value.villa}.`)
    porAnular.value = null
    dataStore.tocar() // refresca esta lista, el dashboard y los saldos abiertos
  } catch (e: any) {
    toast.error(mensajeDeError(e, 'No se pudo anular el movimiento.'))
  } finally {
    anulando.value = false
  }
}

const claseCampo =
  'rounded-lg border border-espresso-800/15 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200'
</script>

<template>
  <div>
    <p class="mb-1 font-display text-2xl font-semibold text-espresso-800">Reimpresión</p>
    <p class="mb-5 text-sm text-espresso-800/60">
      Notas de cargo y recibos de los movimientos aplicados (original y copia). Busca el movimiento y ábrelo para verlo o volver a imprimirlo.
      <template v-if="auth.esDirectorOAdmin()">Si se capturó por error, anúlalo y vuelve a capturar el correcto.</template>
    </p>

    <form
      class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-gold-300/30 bg-cream-50 p-4 shadow-sm"
      @submit.prevent="buscar"
    >
      <div class="w-full max-w-sm">
        <label class="mb-1 block text-sm font-medium text-espresso-700">Villa</label>
        <VillaBuscador v-model="filtroVilla" con-opcion-todas />
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-espresso-700">Tipo</label>
        <select v-model="filtroTipo" :class="claseCampo">
          <option value="">Todos</option>
          <option value="cargo">Cargos</option>
          <option value="credito">Créditos / abonos</option>
        </select>
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-espresso-700">Desde</label>
        <FechaInput v-model="filtroDesde" :class="[claseCampo, 'w-40']" />
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-espresso-700">Hasta</label>
        <FechaInput v-model="filtroHasta" :min="filtroDesde" :class="[claseCampo, 'w-40']" />
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-espresso-700">Correlativo No.</label>
        <input v-model="filtroCorrelativo" type="text" maxlength="40" placeholder="Ej. CR0000141" :class="[claseCampo, 'w-40']" />
      </div>
      <button
        type="submit"
        class="rounded-lg bg-gradient-to-r from-wine-500 via-brand-500 to-gold-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
      >
        Buscar
      </button>
      <button
        type="button"
        class="rounded-lg border border-espresso-800/20 px-4 py-2 text-sm font-medium text-espresso-700 hover:bg-brand-50"
        @click="limpiar"
      >
        Limpiar
      </button>
    </form>

    <p v-if="cargando" class="mb-2 text-espresso-800/40">Cargando...</p>

    <div class="overflow-x-auto rounded-xl border border-gold-300/30 bg-cream-50 shadow-sm">
      <table class="min-w-full divide-y divide-gold-300/20 text-sm">
        <thead class="bg-brand-50/60">
          <tr>
            <th class="whitespace-nowrap px-4 py-2.5 text-left font-medium text-espresso-800/70">Correlativo No.</th>
            <th class="px-4 py-2.5 text-left font-medium text-espresso-800/70">Fecha</th>
            <th class="px-4 py-2.5 text-left font-medium text-espresso-800/70">Villa</th>
            <th class="px-4 py-2.5 text-left font-medium text-espresso-800/70">Concepto</th>
            <th class="px-4 py-2.5 text-left font-medium text-espresso-800/70">Tipo</th>
            <th class="px-4 py-2.5 text-right font-medium text-espresso-800/70">Importe</th>
            <th class="px-4 py-2.5 text-left font-medium text-espresso-800/70">Registró</th>
            <th class="px-4 py-2.5"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gold-300/15">
          <tr v-for="m in movimientos" :key="m.id" class="hover:bg-brand-50/40" :class="m.anulado ? 'bg-espresso-800/[0.03] text-espresso-800/50' : ''">
            <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs text-espresso-900">{{ m.correlativo ?? '—' }}</td>
            <td class="whitespace-nowrap px-4 py-2.5 text-espresso-800/80">{{ formatearFecha(m.fecha) }}</td>
            <td class="px-4 py-2.5">
              <span class="font-medium text-espresso-900">{{ m.villa }}</span>
              <span v-if="m.propietario" class="block max-w-[14rem] truncate text-xs text-espresso-800/55" :title="m.propietario">{{ m.propietario }}</span>
            </td>
            <td class="px-4 py-2.5 text-espresso-800/85">
              {{ m.concepto }}
              <span v-if="m.observacion" class="ml-1.5 inline-block h-1.5 w-1.5 rounded-full bg-gold-500 align-middle" :title="m.observacion"></span>
              <span v-if="m.forma_pago" class="block text-xs text-espresso-800/55">{{ m.forma_pago }}</span>
            </td>
            <td class="px-4 py-2.5">
              <span
                class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="m.tipo === 'cargo' ? 'bg-brand-100 text-brand-800' : 'bg-emerald-100 text-emerald-800'"
              >
                {{ m.tipo === 'cargo' ? 'Cargo' : 'Crédito' }}
              </span>
              <span
                v-if="m.anulado"
                class="ml-1 mt-1 inline-block rounded-full bg-wine-500/10 px-2.5 py-0.5 text-xs font-semibold text-wine-600"
                :title="`Anulado por ${m.anulado_por} — ${m.motivo_anulacion}`"
              >
                Anulado
              </span>
            </td>
            <td class="whitespace-nowrap px-4 py-2.5 text-right font-medium" :class="m.anulado ? 'text-espresso-800/45 line-through' : 'text-espresso-900'">{{ formatearMonto(m.importe) }}</td>
            <td class="px-4 py-2.5 text-espresso-800/70">{{ m.usuario }}</td>
            <td class="whitespace-nowrap px-4 py-2.5 text-right">
              <button
                v-if="auth.esDirectorOAdmin() && !m.anulado"
                type="button"
                class="mr-2 whitespace-nowrap rounded-lg border border-wine-500/30 px-3 py-1.5 text-sm font-medium text-wine-600 hover:bg-wine-500/5"
                @click="pedirAnulacion(m)"
              >
                Anular
              </button>
              <button
                type="button"
                class="whitespace-nowrap rounded-lg border border-espresso-800/20 px-3 py-1.5 text-sm font-medium text-espresso-700 hover:bg-brand-50"
                @click="abrirRecibo(m.id)"
              >
                Ver documento
              </button>
            </td>
          </tr>
          <tr v-if="!cargando && movimientos.length === 0">
            <td colspan="8" class="px-4 py-8 text-center text-espresso-800/40">Sin movimientos para estos filtros</td>
          </tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <div
        v-if="porAnular"
        class="fixed inset-0 z-[60] flex items-center justify-center bg-espresso-900/50 p-4 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        @click.self="cerrarAnulacion"
      >
        <form class="w-full max-w-md rounded-2xl bg-cream-50 shadow-2xl shadow-espresso-900/20" @submit.prevent="confirmarAnulacion">
          <div class="rounded-t-2xl border-b border-gold-300/30 bg-gradient-to-r from-wine-500/10 to-cream-50 px-6 py-4">
            <h2 class="font-display text-lg font-semibold text-espresso-800">
              Anular {{ porAnular.tipo === 'cargo' ? 'cargo' : 'abono' }} {{ porAnular.correlativo ?? '' }}
            </h2>
            <p class="mt-1 text-xs text-espresso-800/55">
              Villa {{ porAnular.villa }} · {{ porAnular.concepto }} · {{ formatearFecha(porAnular.fecha) }} ·
              {{ formatearMonto(porAnular.importe) }}
            </p>
          </div>

          <div class="space-y-3 px-6 py-5 text-sm">
            <p class="text-espresso-800/75">
              El movimiento dejará de contar en el saldo y no saldrá en el estado de cuenta. Seguirá visible aquí
              marcado como <strong class="text-wine-600">ANULADO</strong> y quedará registrado en la bitácora.
              <strong>No se puede deshacer.</strong>
            </p>
            <div>
              <label class="mb-1 block font-medium text-espresso-700">Motivo de la anulación</label>
              <textarea
                v-model="motivo"
                rows="3"
                maxlength="255"
                required
                placeholder="Ej. Se capturó en la villa equivocada"
                :class="[claseCampo, 'w-full resize-none']"
                autofocus
              />
            </div>
          </div>

          <div class="flex justify-end gap-2 rounded-b-2xl border-t border-gold-300/30 px-6 py-4">
            <button
              type="button"
              class="rounded-lg px-4 py-2 text-sm font-medium text-espresso-700 hover:bg-espresso-800/5"
              :disabled="anulando"
              @click="cerrarAnulacion"
            >
              Cancelar
            </button>
            <button
              type="submit"
              class="rounded-lg bg-wine-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-wine-600 disabled:opacity-50"
              :disabled="anulando || motivo.trim().length < 5"
            >
              {{ anulando ? 'Anulando...' : 'Anular' }}
            </button>
          </div>
        </form>
      </div>
    </Teleport>

    <PaginacionControles
      :pagina="meta.pagina"
      :ultima-pagina="meta.ultima_pagina"
      :total="meta.total"
      :por-pagina="porPagina"
      @update:pagina="irAPagina"
      @update:por-pagina="cambiarPorPagina"
    />
  </div>
</template>
