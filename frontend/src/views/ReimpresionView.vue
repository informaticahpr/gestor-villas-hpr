<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import api from '../lib/api'
import { formatearMonto } from '../lib/format'
import { formatearFecha } from '../lib/fechaFormato'
import { abrirRecibo } from '../lib/exportar'
import { mensajeDeError } from '../lib/errors'
import { useToastStore } from '../stores/toast'
import { useDataStore } from '../stores/data'
import FechaInput from '../components/FechaInput.vue'
import VillaBuscador from '../components/VillaBuscador.vue'
import PaginacionControles from '../components/PaginacionControles.vue'
import type { MovimientoListado, MetaPaginacion } from '../types'

const toast = useToastStore()
const dataStore = useDataStore()

const movimientos = ref<MovimientoListado[]>([])
const cargando = ref(false)

const pagina = ref(1)
const porPagina = ref(10)
const meta = ref<MetaPaginacion>({ pagina: 1, por_pagina: 10, total: 0, ultima_pagina: 1 })

// formulario de filtros
const filtroVilla = ref('') // '' = todas
const filtroTipo = ref<'' | 'cargo' | 'credito'>('')
const filtroFolio = ref('')
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
    toast.error(mensajeDeError(e, 'No se pudieron cargar los recibos.'))
  } finally {
    cargando.value = false
  }
}

function buscar() {
  filtrosAplicados.value = {
    villa: filtroVilla.value || undefined,
    tipo: filtroTipo.value || undefined,
    q: filtroFolio.value.trim() || undefined,
    desde: filtroDesde.value || undefined,
    hasta: filtroHasta.value || undefined,
  }
  pagina.value = 1
  cargar()
}

function limpiar() {
  filtroVilla.value = ''
  filtroTipo.value = ''
  filtroFolio.value = ''
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

const claseCampo =
  'rounded-lg border border-espresso-800/15 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200'
</script>

<template>
  <div>
    <p class="mb-1 font-display text-2xl font-semibold text-espresso-800">Reimpresión</p>
    <p class="mb-5 text-sm text-espresso-800/60">
      Recibos de los cargos y abonos aplicados. Busca el movimiento y ábrelo para volver a imprimir su recibo.
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
        <label class="mb-1 block text-sm font-medium text-espresso-700">Folio</label>
        <input v-model="filtroFolio" type="text" maxlength="40" placeholder="Ej. CR0000141" :class="[claseCampo, 'w-40']" />
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
            <th class="px-4 py-2.5 text-left font-medium text-espresso-800/70">Folio</th>
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
          <tr v-for="m in movimientos" :key="m.id" class="hover:bg-brand-50/40">
            <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs text-espresso-900">{{ m.folio ?? '—' }}</td>
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
            </td>
            <td class="whitespace-nowrap px-4 py-2.5 text-right font-medium text-espresso-900">{{ formatearMonto(m.importe) }}</td>
            <td class="px-4 py-2.5 text-espresso-800/70">{{ m.usuario }}</td>
            <td class="px-4 py-2.5 text-right">
              <button
                type="button"
                class="whitespace-nowrap rounded-lg border border-espresso-800/20 px-3 py-1.5 text-sm font-medium text-espresso-700 hover:bg-brand-50"
                @click="abrirRecibo(m.id)"
              >
                Ver recibo
              </button>
            </td>
          </tr>
          <tr v-if="!cargando && movimientos.length === 0">
            <td colspan="8" class="px-4 py-8 text-center text-espresso-800/40">Sin movimientos para estos filtros</td>
          </tr>
        </tbody>
      </table>
    </div>

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
