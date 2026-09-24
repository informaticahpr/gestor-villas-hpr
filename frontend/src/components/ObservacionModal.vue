<script setup lang="ts">
import { computed } from 'vue'
import { useEscapeKey } from '../lib/useEscapeKey'
import { abrirRecibo } from '../lib/exportar'
import { formatearFecha } from '../lib/fechaFormato'
import { formatearMonto } from '../lib/format'
import type { MovimientoFila } from '../types'

const props = defineProps<{ movimiento: MovimientoFila }>()
const emit = defineEmits<{ close: [] }>()

const tipo = computed(() => (props.movimiento.tipo === 'cargo' ? 'Cargo' : 'Crédito'))
const importe = computed(() => (props.movimiento.tipo === 'cargo' ? props.movimiento.cargo : props.movimiento.credito))

// Prioritario: al estar abierto sobre otro modal (ej. el de la villa), Escape solo cierra este.
useEscapeKey(
  () => {
    emit('close')
    return true
  },
  { prioritario: true },
)
</script>

<template>
  <Teleport to="body">
    <div
      class="fixed inset-0 z-[60] flex items-center justify-center bg-espresso-900/50 p-4 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
      @click.self="emit('close')"
    >
      <div class="w-full max-w-md rounded-2xl bg-cream-50 shadow-2xl shadow-espresso-900/20">
        <div class="rounded-t-2xl border-b border-gold-300/30 bg-gradient-to-r from-brand-50/70 to-cream-50 px-6 py-4">
          <h2 class="font-display text-lg font-semibold text-espresso-800">{{ tipo }} — {{ movimiento.descripcion }}</h2>
          <p class="mt-1 text-xs text-espresso-800/55">
            <span v-if="movimiento.folio">Folio {{ movimiento.folio }} · </span>{{ formatearFecha(movimiento.fecha) }} ·
            {{ formatearMonto(importe) }}
          </p>
        </div>

        <div class="px-6 py-5">
          <p class="mb-1 text-xs font-medium uppercase tracking-wide text-espresso-800/40">Observación</p>
          <p v-if="movimiento.observacion" class="whitespace-pre-line text-sm text-espresso-800/90">{{ movimiento.observacion }}</p>
          <p v-else class="text-sm italic text-espresso-800/40">Sin observación.</p>
        </div>

        <div class="flex justify-end gap-2 rounded-b-2xl border-t border-gold-300/30 px-6 py-4">
          <button
            type="button"
            class="rounded-lg px-4 py-2 text-sm font-medium text-espresso-700 hover:bg-espresso-800/5"
            @click="emit('close')"
          >
            Cerrar
          </button>
          <button
            type="button"
            class="rounded-lg bg-gradient-to-r from-wine-500 via-brand-500 to-gold-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
            @click="abrirRecibo(movimiento.id)"
          >
            Ver recibo
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
