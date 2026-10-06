<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '../lib/api'
import { formatearMonto } from '../lib/format'
import { useAuthStore } from '../stores/auth'
import { useDataStore } from '../stores/data'
import { useUiStore } from '../stores/ui'

const router = useRouter()
const auth = useAuthStore()
const ui = useUiStore()
const dataStore = useDataStore()

interface Periodo {
  cxc: number
  recuperado: number
}

interface Dashboard {
  fecha: string
  villas: number
  cuota_mantenimiento: number | null
  cuentas_por_cobrar: number
  /** Solo Director/Admin */
  saldo_a_favor?: number
  mes: Periodo
  /** Solo Director/Admin */
  anio?: Periodo & { anio: number; meses: (Periodo & { mes: number })[] }
}

const MESES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic']
const MESES_LARGOS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']

// Todos ven el dashboard; al Supervisor el backend solo le manda villas, cuota, cuentas por cobrar y
// lo recuperado en el mes (saldo a favor y el año son de Director/Admin).
const dashboard = ref<Dashboard | null>(null)
const errorDashboard = ref(false)

async function cargarDashboard() {
  try {
    const { data } = await api.get<Dashboard>('/api/dashboard')
    dashboard.value = data
    errorDashboard.value = false
  } catch {
    errorDashboard.value = true
  }
}

onMounted(cargarDashboard)
watch(() => dataStore.version, cargarDashboard)

function porcentaje(p: Periodo): number {
  return p.cxc > 0 ? Math.round((p.recuperado / p.cxc) * 1000) / 10 : 0
}

const nombreMes = computed(() => {
  if (!dashboard.value) return ''
  const [, mes] = dashboard.value.fecha.split('-').map(Number)
  return MESES_LARGOS[mes - 1]
})

const fechaCorte = computed(() => {
  if (!dashboard.value) return ''
  const [anio, mes, dia] = dashboard.value.fecha.split('-').map(Number)
  return `${dia} de ${MESES_LARGOS[mes - 1]} de ${anio}`
})

/** Escala comun de las barras mensuales: el mayor valor (cxc o recuperado) del año. */
const maxMensual = computed(() => {
  const meses = dashboard.value?.anio?.meses ?? []
  return Math.max(1, ...meses.flatMap((m) => [m.cxc, m.recuperado]))
})

const mesActivo = ref<number | null>(null)

// Debajo del dashboard los accesos rapidos van mas pequeños.
const compacto = computed(() => true)
const tarjeta =
  'group rounded-xl border border-gold-300/30 bg-cream-50 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-lg hover:shadow-brand-900/5'
const icono =
  'flex items-center justify-center rounded-lg bg-gradient-to-br from-wine-500 via-brand-500 to-gold-500 text-white shadow-sm'

const acciones = [
  {
    titulo: 'Buscar Villa',
    descripcion: 'Buscar por # de villa o propietario',
    accion: () => router.push({ name: 'villas' }),
    icon: 'M11 4a7 7 0 104.9 12.02l4.54 4.54a1 1 0 001.42-1.42l-4.54-4.54A7 7 0 0011 4zm-5 7a5 5 0 1110 0 5 5 0 01-10 0z',
  },
  {
    titulo: 'Crear Villa',
    descripcion: 'Registrar una nueva villa',
    accion: () => (ui.mostrarCrearVilla = true),
    icon: 'M12 4a1 1 0 011 1v6h6a1 1 0 110 2h-6v6a1 1 0 11-2 0v-6H5a1 1 0 110-2h6V5a1 1 0 011-1z',
  },
  {
    titulo: 'Cargo/Crédito',
    descripcion: 'Aplicar un cargo o abono',
    accion: () => (ui.mostrarMovimiento = true),
    icon: 'M7 8a3 3 0 013-3h7a1 1 0 010 2h-7a1 1 0 000 2h4a3 3 0 010 6h-1a1 1 0 110-2h1a1 1 0 000-2h-4a3 3 0 01-3-3zm0 9a1 1 0 011-1h9a1 1 0 110 2H8a1 1 0 01-1-1z',
  },
  {
    titulo: 'Reportes',
    descripcion: 'Estados de cuenta y saldos generales',
    accion: () => router.push({ name: 'reportes' }),
    icon: 'M4 20V10a1 1 0 112 0v10a1 1 0 11-2 0zm7 0V4a1 1 0 112 0v16a1 1 0 11-2 0zm7 0v-7a1 1 0 112 0v7a1 1 0 11-2 0z',
  },
  {
    titulo: 'Reimpresión',
    descripcion: 'Ver o volver a imprimir una nota de cargo o un recibo',
    accion: () => router.push({ name: 'reimpresion' }),
    // outline, distinto al resto (que son "fill"): se dibuja con stroke, ver template
    icon: 'M6.72 13.829a42.415 42.415 0 0110.56 0M6.34 18h11.318M6.34 18l.228 2.523a1.125 1.125 0 001.121 1.227h8.618a1.125 1.125 0 001.12-1.227L17.66 18M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0c.653.06 1.303.132 1.95.216 1.017.132 1.75 1.05 1.75 2.075V15.75a2.25 2.25 0 01-2.25 2.25h-1.083m-9.417-8.716V4.5A2.25 2.25 0 019 2.25h6a2.25 2.25 0 012.25 2.25v4.034M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z',
    outline: true,
  },
]
</script>

<template>
  <div>
    <div class="mb-6">
      <p class="font-display text-2xl font-semibold text-espresso-800">
        Bienvenido, {{ auth.user?.name }}
      </p>
      <p class="mt-1 text-sm text-espresso-800/50">
        <template v-if="dashboard">Resumen al {{ fechaCorte }}</template>
        <template v-else>¿Qué deseas hacer hoy?</template>
      </p>
    </div>

    <!-- Dashboard (el Supervisor ve solo villas, cuota, cuentas por cobrar y lo recuperado en el mes) -->
    <section class="mb-8">
      <p v-if="errorDashboard" class="rounded-xl border border-wine-500/20 bg-wine-500/5 p-4 text-sm text-wine-600">
        No se pudo cargar el resumen.
        <button class="ml-1 font-semibold underline" @click="cargarDashboard">Reintentar</button>
      </p>

      <div v-else-if="!dashboard" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div v-for="n in 4" :key="n" class="h-24 animate-pulse rounded-xl bg-cream-200/70" />
      </div>

      <template v-else>
        <!-- Indicadores -->
        <div class="grid grid-cols-2 gap-4" :class="dashboard.saldo_a_favor !== undefined ? 'lg:grid-cols-4' : 'lg:grid-cols-3'">
          <div class="rounded-xl border border-gold-300/30 bg-cream-50 p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-espresso-800/50">Villas registradas</p>
            <p class="mt-2 font-display text-2xl font-semibold text-espresso-800 tabular-nums">{{ dashboard.villas }}</p>
          </div>
          <div class="rounded-xl border border-gold-300/30 bg-cream-50 p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-espresso-800/50">Cuota de mantenimiento</p>
            <p class="mt-2 font-display text-2xl font-semibold text-espresso-800 tabular-nums">
              {{ dashboard.cuota_mantenimiento !== null ? formatearMonto(dashboard.cuota_mantenimiento) : 'Sin definir' }}
            </p>
            <p class="mt-0.5 text-xs text-espresso-800/45">mensual</p>
          </div>
          <div class="rounded-xl border border-gold-300/30 bg-cream-50 p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-espresso-800/50">Cuentas por cobrar</p>
            <p class="mt-2 font-display text-2xl font-semibold text-espresso-800 tabular-nums">{{ formatearMonto(dashboard.cuentas_por_cobrar) }}</p>
            <p class="mt-0.5 text-xs text-espresso-800/45">saldo a la fecha</p>
          </div>
          <div v-if="dashboard.saldo_a_favor !== undefined" class="rounded-xl border border-gold-300/30 bg-cream-50 p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-espresso-800/50">Saldo a favor de propietarios</p>
            <p class="mt-2 font-display text-2xl font-semibold text-espresso-800 tabular-nums">{{ formatearMonto(dashboard.saldo_a_favor) }}</p>
            <p class="mt-0.5 text-xs text-espresso-800/45">a la fecha</p>
          </div>
        </div>

        <!-- Recuperado: mes y año -->
        <div class="mt-4 grid grid-cols-1 gap-4" :class="dashboard.anio ? 'lg:grid-cols-3' : ''">
          <div class="rounded-xl border border-gold-300/30 bg-cream-50 p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-espresso-800/50">Recuperado en {{ nombreMes }}</p>
            <p class="mt-2 font-display text-3xl font-semibold text-espresso-800 tabular-nums">{{ formatearMonto(dashboard.mes.recuperado) }}</p>
            <p class="mt-1 text-sm text-espresso-800/55">
              de {{ formatearMonto(dashboard.mes.cxc) }} cargado en el mes
            </p>
            <div class="mt-4 h-2 overflow-hidden rounded-full bg-cream-200">
              <div class="h-full rounded-full bg-[#a84a14] transition-all" :style="{ width: Math.min(100, porcentaje(dashboard.mes)) + '%' }" />
            </div>
            <p class="mt-2 text-sm font-semibold text-espresso-800 tabular-nums">{{ porcentaje(dashboard.mes) }}% recuperado</p>
          </div>

          <div v-if="dashboard.anio" class="rounded-xl border border-gold-300/30 bg-cream-50 p-5 shadow-sm lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-4">
              <div>
                <p class="text-xs font-medium uppercase tracking-wide text-espresso-800/50">Recuperado en {{ dashboard.anio.anio }}</p>
                <p class="mt-2 font-display text-3xl font-semibold text-espresso-800 tabular-nums">{{ formatearMonto(dashboard.anio.recuperado) }}</p>
                <p class="mt-1 text-sm text-espresso-800/55">
                  de {{ formatearMonto(dashboard.anio.cxc) }} cargado en el año ·
                  <span class="font-semibold text-espresso-800">{{ porcentaje(dashboard.anio) }}%</span>
                </p>
              </div>
              <div class="flex items-center gap-4 text-xs text-espresso-800/60">
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-[#c9a13b]" />Cargado (CxC)</span>
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-[#a84a14]" />Recuperado</span>
              </div>
            </div>

            <!-- Barras por mes: cargado vs recuperado -->
            <div class="relative mt-5">
              <div class="flex h-32 items-end gap-1.5 border-b border-espresso-800/10 sm:gap-3">
                <div
                  v-for="m in dashboard.anio.meses"
                  :key="m.mes"
                  class="relative flex h-full flex-1 cursor-default items-end justify-center gap-0.5 rounded-t-md transition-colors"
                  :class="mesActivo === m.mes ? 'bg-cream-200/60' : ''"
                  @mouseenter="mesActivo = m.mes"
                  @mouseleave="mesActivo = null"
                >
                  <div class="w-full max-w-4 rounded-t-[4px] bg-[#c9a13b]" :style="{ height: (m.cxc / maxMensual) * 100 + '%' }" />
                  <div class="w-full max-w-4 rounded-t-[4px] bg-[#a84a14]" :style="{ height: (m.recuperado / maxMensual) * 100 + '%' }" />

                  <div
                    v-if="mesActivo === m.mes"
                    class="pointer-events-none absolute bottom-full z-10 mb-2 w-max rounded-lg border border-gold-300/40 bg-white px-3 py-2 text-xs shadow-lg"
                  >
                    <p class="mb-1 font-semibold capitalize text-espresso-800">{{ MESES_LARGOS[m.mes - 1] }}</p>
                    <p class="flex items-center gap-1.5 text-espresso-800/70">
                      <span class="h-2 w-2 rounded-sm bg-[#c9a13b]" />Cargado
                      <span class="ml-auto pl-3 font-medium tabular-nums text-espresso-800">{{ formatearMonto(m.cxc) }}</span>
                    </p>
                    <p class="flex items-center gap-1.5 text-espresso-800/70">
                      <span class="h-2 w-2 rounded-sm bg-[#a84a14]" />Recuperado
                      <span class="ml-auto pl-3 font-medium tabular-nums text-espresso-800">{{ formatearMonto(m.recuperado) }}</span>
                    </p>
                    <p class="mt-1 border-t border-espresso-800/10 pt-1 text-espresso-800/60">{{ porcentaje(m) }}% recuperado</p>
                  </div>
                </div>
              </div>
              <div class="mt-1.5 flex gap-1.5 sm:gap-3">
                <span v-for="m in dashboard.anio.meses" :key="m.mes" class="flex-1 text-center text-[11px] text-espresso-800/50">{{ MESES[m.mes - 1] }}</span>
              </div>
            </div>
          </div>
        </div>
      </template>
    </section>

    <p class="mb-3 text-xs font-medium uppercase tracking-wide text-espresso-800/50">Accesos rápidos</p>

    <div :class="compacto ? 'grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6' : 'grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4'">
      <button
        v-for="a in acciones"
        :key="a.titulo"
        :class="[tarjeta, compacto ? 'p-3' : 'p-5']"
        @click="a.accion"
      >
        <span :class="[icono, compacto ? 'mb-2 h-8 w-8' : 'mb-3 h-10 w-10']">
          <svg v-if="a.outline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" :class="compacto ? 'h-4 w-4' : 'h-5 w-5'"><path stroke-linecap="round" stroke-linejoin="round" :d="a.icon" /></svg>
          <svg v-else viewBox="0 0 24 24" fill="currentColor" :class="compacto ? 'h-4 w-4' : 'h-5 w-5'"><path :d="a.icon" /></svg>
        </span>
        <p class="font-display font-semibold text-espresso-800" :class="compacto ? 'text-sm' : ''">{{ a.titulo }}</p>
        <p class="mt-1 text-espresso-800/55" :class="compacto ? 'text-xs' : 'text-sm'">{{ a.descripcion }}</p>
      </button>

      <RouterLink
        v-if="auth.puedeVerConfiguracion()"
        :to="{ name: 'configuracion' }"
        :class="[tarjeta, 'p-3']"
      >
        <span :class="[icono, 'mb-2 h-8 w-8']">
          <svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4">
            <path
              d="M11.078 2.25c-.917 0-1.699.663-1.85 1.567l-.091.549a.798.798 0 01-.517.608 7.45 7.45 0 00-.478.198.798.798 0 01-.796-.064l-.453-.324a1.875 1.875 0 00-2.416.196l-.038.038a1.875 1.875 0 00-.196 2.416l.324.453a.798.798 0 01.064.796 7.448 7.448 0 00-.198.478.798.798 0 01-.608.517l-.55.092a1.875 1.875 0 00-1.566 1.849v.055c0 .917.663 1.699 1.567 1.85l.549.091c.281.047.508.25.608.517.06.163.127.323.198.478a.798.798 0 01-.064.796l-.324.453a1.875 1.875 0 00.196 2.416l.038.038c.638.638 1.659.7 2.416.196l.453-.324a.798.798 0 01.796-.064c.155.071.315.138.478.198.267.1.47.327.517.608l.092.55c.15.903.932 1.566 1.849 1.566h.055c.917 0 1.699-.663 1.85-1.567l.091-.549a.798.798 0 01.517-.608 7.52 7.52 0 00.478-.198.798.798 0 01.796.064l.453.324a1.875 1.875 0 002.416-.196l.038-.038c.638-.638.7-1.659.196-2.416l-.324-.453a.798.798 0 01-.064-.796c.071-.155.138-.315.198-.478.1-.267.327-.47.608-.517l.55-.091a1.875 1.875 0 001.566-1.85v-.055c0-.917-.663-1.699-1.567-1.85l-.549-.091a.798.798 0 01-.608-.517 7.507 7.507 0 00-.198-.478.798.798 0 01.064-.796l.324-.453a1.875 1.875 0 00-.196-2.416l-.038-.038a1.875 1.875 0 00-2.416-.196l-.453.324a.798.798 0 01-.796.064 7.462 7.462 0 00-.478-.198.798.798 0 01-.517-.608l-.091-.55a1.875 1.875 0 00-1.85-1.566h-.054zM12 15.75a3.75 3.75 0 100-7.5 3.75 3.75 0 000 7.5z"
            />
          </svg>
        </span>
        <p class="font-display text-sm font-semibold text-espresso-800">Configuración</p>
        <p class="mt-1 text-xs text-espresso-800/55">{{ auth.esSupervisor() ? 'Villas alquiladas a España' : 'Usuarios y roles del sistema' }}</p>
      </RouterLink>
    </div>
  </div>
</template>
