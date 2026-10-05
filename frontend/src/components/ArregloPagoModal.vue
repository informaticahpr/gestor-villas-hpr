<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '../lib/api'
import { formatearMonto } from '../lib/format'
import { formatearFecha } from '../lib/fechaFormato'
import { mensajeDeError } from '../lib/errors'
import { useEscapeKey } from '../lib/useEscapeKey'
import { useToastStore } from '../stores/toast'

/**
 * Arreglo de pago (lo autoriza el Director; el Admin tambien tiene acceso, para pruebas): anula moras vigentes de una villa, por ejemplo cuando el
 * propietario paga todo lo atrasado de una vez. Queda registrado en la bitacora con el motivo.
 */
const props = defineProps<{ villaId: string }>()
const emit = defineEmits<{ close: []; aplicado: [] }>()

const toast = useToastStore()

interface Mora {
  id: number
  folio: string | null
  fecha: string
  importe: number
  descripcion: string | null
}

const cargando = ref(true)
const guardando = ref(false)
const moras = ref<Mora[]>([])
const saldo = ref(0)
const seleccion = ref<number[]>([])
const motivo = ref('')

const totalSeleccionado = computed(() =>
  Math.round(moras.value.filter((m) => seleccion.value.includes(m.id)).reduce((t, m) => t + m.importe, 0) * 100) / 100,
)
const todas = computed({
  get: () => moras.value.length > 0 && seleccion.value.length === moras.value.length,
  set: (v: boolean) => (seleccion.value = v ? moras.value.map((m) => m.id) : []),
})

async function cargar() {
  cargando.value = true
  try {
    const { data } = await api.get(`/api/villas/${props.villaId}/moras`)
    moras.value = data.moras
    saldo.value = data.saldo
    seleccion.value = data.moras.map((m: Mora) => m.id)
  } catch (e: any) {
    toast.error(mensajeDeError(e, 'No se pudieron cargar las moras de la villa.'))
  } finally {
    cargando.value = false
  }
}

async function aplicar() {
  if (seleccion.value.length === 0 || motivo.value.trim().length < 5) return
  guardando.value = true
  try {
    const { data } = await api.post(`/api/villas/${props.villaId}/arreglo-pago`, { moras: seleccion.value, motivo: motivo.value.trim() })
    toast.success(`Arreglo de pago aplicado: se anularon ${data.anuladas} mora(s) por ${formatearMonto(data.total)}. Saldo de la villa: ${formatearMonto(data.saldo_villa)}.`)
    emit('aplicado')
  } catch (e: any) {
    toast.error(mensajeDeError(e, 'No se pudo aplicar el arreglo de pago.'))
  } finally {
    guardando.value = false
  }
}

onMounted(cargar)

useEscapeKey(
  () => {
    if (!guardando.value) emit('close')
    return true
  },
  { prioritario: true },
)
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-[60] flex items-center justify-center bg-espresso-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" @click.self="!guardando && emit('close')">
      <form class="flex max-h-[90vh] w-full max-w-xl flex-col rounded-2xl bg-cream-50 shadow-2xl shadow-espresso-900/20" @submit.prevent="aplicar">
        <div class="rounded-t-2xl border-b border-gold-300/30 bg-gradient-to-r from-brand-50/70 to-cream-50 px-6 py-4">
          <h2 class="font-display text-lg font-semibold text-espresso-800">Arreglo de pago · Villa {{ villaId }}</h2>
          <p class="mt-1 text-xs text-espresso-800/55">Anula las moras seleccionadas. Queda registrado en la bitácora y no se puede deshacer.</p>
        </div>

        <div class="space-y-4 overflow-y-auto px-6 py-5 text-sm">
          <p v-if="cargando" class="text-espresso-800/40">Cargando...</p>
          <p v-else-if="moras.length === 0" class="py-4 text-center text-espresso-800/50">Esta villa no tiene moras vigentes.</p>
          <template v-else>
            <div class="overflow-hidden rounded-lg border border-gold-300/40 bg-white">
              <label class="flex cursor-pointer items-center gap-3 border-b border-gold-300/30 bg-brand-50/50 px-3 py-2 font-medium text-espresso-800">
                <input v-model="todas" type="checkbox" class="h-4 w-4 rounded border-espresso-800/25 text-brand-600" />
                Todas las moras ({{ moras.length }})
              </label>
              <div class="max-h-60 divide-y divide-gold-300/20 overflow-y-auto">
                <label v-for="m in moras" :key="m.id" class="flex cursor-pointer items-start gap-3 px-3 py-2 hover:bg-brand-50/40">
                  <input v-model="seleccion" type="checkbox" :value="m.id" class="mt-0.5 h-4 w-4 rounded border-espresso-800/25 text-brand-600" />
                  <span class="min-w-0 flex-1">
                    <span class="block text-espresso-800">{{ m.descripcion ?? 'Mora' }}</span>
                    <span class="block text-xs text-espresso-800/50">{{ formatearFecha(m.fecha) }}<template v-if="m.folio"> · {{ m.folio }}</template></span>
                  </span>
                  <span class="whitespace-nowrap font-medium text-espresso-900">{{ formatearMonto(m.importe) }}</span>
                </label>
              </div>
            </div>

            <dl class="grid grid-cols-3 gap-3 rounded-lg bg-white/70 p-3 text-center">
              <div>
                <dt class="text-xs text-espresso-800/50">Saldo actual</dt>
                <dd class="font-semibold text-espresso-900">{{ formatearMonto(saldo) }}</dd>
              </div>
              <div>
                <dt class="text-xs text-espresso-800/50">Moras a anular</dt>
                <dd class="font-semibold text-wine-600">− {{ formatearMonto(totalSeleccionado) }}</dd>
              </div>
              <div>
                <dt class="text-xs text-espresso-800/50">Saldo quedaría en</dt>
                <dd class="font-semibold text-espresso-900">{{ formatearMonto(saldo - totalSeleccionado) }}</dd>
              </div>
            </dl>

            <div>
              <label class="mb-1 block font-medium text-espresso-700">Motivo del arreglo</label>
              <textarea
                v-model="motivo"
                rows="2"
                maxlength="200"
                required
                placeholder="Ej. Pagó todo lo adeudado de 2026 en diciembre, autorizado por el Director"
                class="w-full resize-none rounded-lg border border-espresso-800/15 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
              />
            </div>
          </template>
        </div>

        <div class="flex justify-end gap-2 rounded-b-2xl border-t border-gold-300/30 px-6 py-4">
          <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-espresso-700 hover:bg-espresso-800/5" :disabled="guardando" @click="emit('close')">
            Cancelar
          </button>
          <button
            v-if="moras.length > 0"
            type="submit"
            class="rounded-lg bg-wine-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-wine-600 disabled:opacity-50"
            :disabled="guardando || seleccion.length === 0 || motivo.trim().length < 5"
          >
            {{ guardando ? 'Aplicando...' : `Anular ${seleccion.length} mora(s)` }}
          </button>
        </div>
      </form>
    </div>
  </Teleport>
</template>
