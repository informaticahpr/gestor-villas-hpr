<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import api from '../lib/api'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import { useDataStore } from '../stores/data'
import { formatearMonto } from '../lib/format'
import { mensajeDeError } from '../lib/errors'
import { useEscapeKey } from '../lib/useEscapeKey'
import { abrirReporte, type FormatoExportacion } from '../lib/exportar'
import ExportarBotones from './ExportarBotones.vue'
import ObservacionModal from './ObservacionModal.vue'
import FechaInput from './FechaInput.vue'
import { formatearFecha } from '../lib/fechaFormato'
import type { VillaDetalle, EstadoCuenta, MovimientoFila } from '../types'

const props = defineProps<{ villaId?: string }>()
const emit = defineEmits<{ close: []; saved: [] }>()

const auth = useAuthStore()
const toast = useToastStore()
const dataStore = useDataStore()

const esEdicion = computed(() => Boolean(props.villaId))
const editando = ref(false)
const soloLectura = computed(() => esEdicion.value && !editando.value)
const puedeEditar = computed(() => auth.esDirectorOAdmin())

const tab = ref<'datos' | 'reporte'>('datos')
const cargando = ref(false)
const guardando = ref(false)
const estadoCuenta = ref<EstadoCuenta | null>(null)
const periodo = ref<{ desde: string; hasta: string } | null>(null)
const movimientoDetalle = ref<MovimientoFila | null>(null)

const form = reactive({
  CLV_CLIE: '',
  NOMBRES: '',
  APELLIDOS: '',
  DIR: '',
  TELF: '',
  CELULAR: '',
  OTRO_TEL: '',
  MAIL: '',
  MAIL2: '',
  FCONTRUC: '',
  NOMED: '',
  FECHA_NAC: '',
  NOHAB: null as number | null,
  NOBATH: null as number | null,
  APLICOBRO: true,
  CUOTA_ESPECIAL: false,
})

const telefonoValido = computed(() => Boolean(form.TELF || form.CELULAR || form.OTRO_TEL))
const correoValido = computed(() => Boolean(form.MAIL || form.MAIL2))

const saldo = ref(0)

// filtros de entrada: nombre/apellido solo letras (en mayusculas), telefonos solo numeros
function soloLetras(valor: string): string {
  return valor.replace(/[^\p{L}\s]/gu, '').toUpperCase()
}

function soloNumeros(valor: string): string {
  return valor.replace(/[^0-9\-\s]/g, '')
}

const clvClieModel = computed({
  get: () => form.CLV_CLIE,
  set: (v: string) => { form.CLV_CLIE = v.toUpperCase() },
})
const nombresModel = computed({
  get: () => form.NOMBRES,
  set: (v: string) => { form.NOMBRES = soloLetras(v) },
})
const apellidosModel = computed({
  get: () => form.APELLIDOS,
  set: (v: string) => { form.APELLIDOS = soloLetras(v) },
})
const telfModel = computed({
  get: () => form.TELF,
  set: (v: string) => { form.TELF = soloNumeros(v) },
})
const celularModel = computed({
  get: () => form.CELULAR,
  set: (v: string) => { form.CELULAR = soloNumeros(v) },
})
const otroTelModel = computed({
  get: () => form.OTRO_TEL,
  set: (v: string) => { form.OTRO_TEL = soloNumeros(v) },
})

async function cargar() {
  if (!props.villaId) return
  cargando.value = true
  try {
    const { data } = await api.get(`/api/villas/${props.villaId}`)
    const v: VillaDetalle = data.villa
    Object.assign(form, {
      CLV_CLIE: v.CLV_CLIE,
      NOMBRES: v.NOMBRES,
      APELLIDOS: v.APELLIDOS,
      DIR: v.DIR ?? '',
      TELF: v.TELF ?? '',
      CELULAR: v.CELULAR ?? '',
      OTRO_TEL: v.OTRO_TEL ?? '',
      MAIL: v.MAIL ?? '',
      MAIL2: v.MAIL2 ?? '',
      FCONTRUC: v.FCONTRUC ?? '',
      NOMED: v.NOMED ?? '',
      FECHA_NAC: v.FECHA_NAC ?? '',
      NOHAB: v.NOHAB,
      NOBATH: v.NOBATH,
      APLICOBRO: v.APLICOBRO,
      CUOTA_ESPECIAL: v.CUOTA_ESPECIAL,
    })
    saldo.value = v.SALDO
    estadoCuenta.value = data.estado_cuenta
    periodo.value = data.periodo
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

async function guardar() {
  if (!telefonoValido.value) {
    toast.warning('Indica al menos un teléfono: Celular 1, Celular 2 u Otro.')
    return
  }
  if (!correoValido.value) {
    toast.warning('Indica al menos un correo: Correo Electrónico 1 o 2.')
    return
  }

  guardando.value = true
  try {
    if (esEdicion.value) {
      await api.put(`/api/villas/${props.villaId}`, form)
      toast.success(`Villa ${form.CLV_CLIE} actualizada correctamente.`)
    } else {
      await api.post('/api/villas', form)
      toast.success(`Villa ${form.CLV_CLIE} creada correctamente.`)
    }
    editando.value = false
    dataStore.tocar()
    emit('saved')
  } catch (e: any) {
    toast.error(mensajeDeError(e, 'No se pudo guardar la villa.'))
  } finally {
    guardando.value = false
  }
}

// Exporta el mismo periodo que muestra la pestaña, tal como lo calculo el servidor (VillaController::show).
function exportarReporte(formato: FormatoExportacion) {
  if (!periodo.value) return
  abrirReporte('estado-cuenta', formato, { villa: form.CLV_CLIE, ...periodo.value })
}

function claseSaldo(valor: number): string {
  if (valor > 0) return 'text-wine-600'
  if (valor < 0) return 'text-emerald-600'
  return 'text-espresso-700'
}

onMounted(cargar)

// Escape hace lo mismo que el boton de cierre visible en el pie del modal.
useEscapeKey(() => {
  if (tab.value === 'datos' && !soloLectura.value && esEdicion.value) cancelarEdicion()
  else emit('close')
})
</script>

<template>
  <Teleport to="body">
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-espresso-900/50 p-4 backdrop-blur-sm">
    <div class="flex max-h-[90vh] w-full max-w-3xl flex-col rounded-2xl bg-cream-50 shadow-2xl shadow-espresso-900/20">
      <div class="flex items-start justify-between rounded-t-2xl border-b border-gold-300/30 bg-gradient-to-r from-brand-50/70 to-cream-50 px-6 py-4">
        <div>
          <h2 class="font-display text-lg font-semibold text-espresso-800">
            {{ esEdicion ? `Villa ${form.CLV_CLIE}` : 'Nueva Villa' }}
          </h2>
          <div class="mt-2 flex items-center gap-4 text-sm">
            <button
              type="button"
              class="border-b-2 pb-1 font-medium"
              :class="tab === 'datos' ? 'border-brand-600 text-brand-700' : 'border-transparent text-espresso-800/40'"
              @click="tab = 'datos'"
            >
              Datos
            </button>
            <button
              v-if="esEdicion"
              type="button"
              class="border-b-2 pb-1 font-medium"
              :class="tab === 'reporte' ? 'border-brand-600 text-brand-700' : 'border-transparent text-espresso-800/40'"
              @click="tab = 'reporte'"
            >
              Reporte (último año)
            </button>
            <span v-if="soloLectura" class="ml-1 rounded-full bg-espresso-800/8 px-2 py-0.5 text-xs font-medium text-espresso-800/50">
              Solo lectura
            </span>
          </div>
        </div>

        <div class="text-right">
          <p class="text-xs font-medium uppercase tracking-wide text-espresso-800/40">Saldo a la fecha</p>
          <p class="font-display text-2xl font-bold" :class="claseSaldo(saldo)">{{ formatearMonto(saldo) }}</p>
        </div>
      </div>

      <div class="overflow-y-auto px-6 py-4">
        <div v-if="cargando" class="py-10 text-center text-espresso-800/40">Cargando...</div>

        <form v-else-if="tab === 'datos'" class="space-y-6" @submit.prevent="guardar">
          <fieldset :disabled="soloLectura" class="contents space-y-6">
            <div class="grid grid-cols-2 gap-x-5 gap-y-5">
              <div>
                <label class="mb-1.5 block text-sm font-medium text-espresso-700">#Villa</label>
                <input
                  v-model="clvClieModel"
                  :disabled="esEdicion"
                  required
                  placeholder="A-1"
                  class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2.5 text-sm placeholder:text-espresso-800/30 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200 disabled:bg-cream-200 disabled:text-espresso-800/50"
                />
              </div>
              <div>
                <label class="mb-1.5 block text-sm font-medium text-espresso-700">Bloque</label>
                <input v-model="form.DIR" required placeholder="Calle Los Pinos #12" class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2.5 text-sm placeholder:text-espresso-800/30 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200 disabled:bg-cream-200 disabled:text-espresso-800/50" />
              </div>
              <div>
                <label class="mb-1.5 block text-sm font-medium text-espresso-700">Nombres</label>
                <input v-model="nombresModel" required placeholder="ANA" title="Solo letras" class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2.5 text-sm placeholder:text-espresso-800/30 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200 disabled:bg-cream-200 disabled:text-espresso-800/50" />
              </div>
              <div>
                <label class="mb-1.5 block text-sm font-medium text-espresso-700">Apellidos</label>
                <input v-model="apellidosModel" required placeholder="SÁNCHEZ" title="Solo letras" class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2.5 text-sm placeholder:text-espresso-800/30 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200 disabled:bg-cream-200 disabled:text-espresso-800/50" />
              </div>
            </div>

            <div>
              <p class="mb-1.5 text-xs font-medium text-espresso-800/50">Al menos uno de los tres es requerido</p>
              <div class="grid grid-cols-3 gap-x-5 gap-y-5">
                <div>
                  <label class="mb-1.5 block text-sm font-medium text-espresso-700">Celular 1</label>
                  <input v-model="telfModel" inputmode="numeric" title="Solo números" placeholder="2234-5601" class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2.5 text-sm placeholder:text-espresso-800/30 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200 disabled:bg-cream-200 disabled:text-espresso-800/50" />
                </div>
                <div>
                  <label class="mb-1.5 block text-sm font-medium text-espresso-700">Celular 2</label>
                  <input v-model="celularModel" inputmode="numeric" title="Solo números" placeholder="9988-1201" class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2.5 text-sm placeholder:text-espresso-800/30 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200 disabled:bg-cream-200 disabled:text-espresso-800/50" />
                </div>
                <div>
                  <label class="mb-1.5 block text-sm font-medium text-espresso-700">Otro</label>
                  <input v-model="otroTelModel" inputmode="numeric" title="Solo números" placeholder="9988-1299" class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2.5 text-sm placeholder:text-espresso-800/30 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200 disabled:bg-cream-200 disabled:text-espresso-800/50" />
                </div>
              </div>
            </div>

            <div>
              <p class="mb-1.5 text-xs font-medium text-espresso-800/50">Al menos uno de los dos es requerido</p>
              <div class="grid grid-cols-2 gap-x-5 gap-y-5">
                <div>
                  <label class="mb-1.5 block text-sm font-medium text-espresso-700">Correo Electrónico 1</label>
                  <input v-model="form.MAIL" type="email" pattern="[^\s@]+@[^\s@]+\.[a-zA-Z]{2,}" title="Debe incluir un dominio, ej. nombre@dominio.com" placeholder="ana.sanchez@example.com" class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2.5 text-sm placeholder:text-espresso-800/30 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200 disabled:bg-cream-200 disabled:text-espresso-800/50" />
                </div>
                <div>
                  <label class="mb-1.5 block text-sm font-medium text-espresso-700">Correo Electrónico 2</label>
                  <input v-model="form.MAIL2" type="email" pattern="[^\s@]+@[^\s@]+\.[a-zA-Z]{2,}" title="Debe incluir un dominio, ej. nombre@dominio.com" placeholder="ana.sanchez2@example.com" class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2.5 text-sm placeholder:text-espresso-800/30 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200 disabled:bg-cream-200 disabled:text-espresso-800/50" />
                </div>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-x-5 gap-y-5">
              <div>
                <label class="mb-1.5 block text-sm font-medium text-espresso-700">Fecha de Entrega</label>
                <FechaInput v-model="form.FCONTRUC" class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200 disabled:bg-cream-200 disabled:text-espresso-800/50" />
              </div>
              <div>
                <label class="mb-1.5 block text-sm font-medium text-espresso-700">Clave ENEE</label>
                <input v-model="form.NOMED" placeholder="ENEE-1001" class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2.5 text-sm placeholder:text-espresso-800/30 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200 disabled:bg-cream-200 disabled:text-espresso-800/50" />
              </div>
              <div>
                <label class="mb-1.5 block text-sm font-medium text-espresso-700">Fecha de nacimiento del propietario</label>
                <FechaInput v-model="form.FECHA_NAC" class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200 disabled:bg-cream-200 disabled:text-espresso-800/50" />
              </div>
              <div class="flex items-center">
                <label class="flex items-center gap-2 text-sm text-espresso-700">
                  <input
                    v-model="form.APLICOBRO"
                    type="checkbox"
                    class="h-4 w-4 rounded border-espresso-800/25 text-brand-600 focus:ring-2 focus:ring-brand-300"
                  />
                  Aplicar cuota mensual
                </label>
              </div>
              <div class="flex items-center">
                <label class="flex items-center gap-2 text-sm text-espresso-700">
                  <input
                    v-model="form.CUOTA_ESPECIAL"
                    type="checkbox"
                    class="h-4 w-4 rounded border-espresso-800/25 text-brand-600 focus:ring-2 focus:ring-brand-300"
                  />
                  Cuota especial
                </label>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-x-5 gap-y-5">
              <div>
                <label class="mb-1.5 block text-sm font-medium text-espresso-700"># Habitaciones</label>
                <input v-model.number="form.NOHAB" type="number" min="0" placeholder="2" class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2.5 text-sm placeholder:text-espresso-800/30 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200 disabled:bg-cream-200 disabled:text-espresso-800/50" />
              </div>
              <div>
                <label class="mb-1.5 block text-sm font-medium text-espresso-700"># Baños</label>
                <input v-model.number="form.NOBATH" type="number" min="0" placeholder="2" class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2.5 text-sm placeholder:text-espresso-800/30 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200 disabled:bg-cream-200 disabled:text-espresso-800/50" />
              </div>
            </div>
          </fieldset>
        </form>

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
      </div>

      <div class="flex justify-end gap-2 rounded-b-2xl border-t border-gold-300/30 bg-cream-100/60 px-6 py-4">
        <template v-if="tab === 'datos'">
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
