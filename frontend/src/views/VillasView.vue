<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import api from '../lib/api'
import type { VillaResumen } from '../types'
import VillaModal from '../components/VillaModal.vue'
import { useDataStore } from '../stores/data'
import { formatearMonto } from '../lib/format'

const dataStore = useDataStore()

const q = ref('')
const villas = ref<VillaResumen[]>([])
const cargando = ref(false)
const villaSeleccionada = ref<string | null>(null)

async function buscar() {
  cargando.value = true
  try {
    const { data } = await api.get('/api/villas', { params: { q: q.value } })
    villas.value = data.data
  } finally {
    cargando.value = false
  }
}

// se refresca sola si se crea/edita una villa o se aplica un cargo desde otra
// pantalla (ej. el modal "Crear Villa" o "Cargo/Crédito" del menú superior)
watch(() => dataStore.version, buscar)

function claseSaldo(saldo: number): string {
  if (saldo > 0) return 'text-wine-600 font-semibold'
  if (saldo < 0) return 'text-emerald-600 font-semibold'
  return 'text-espresso-800/50'
}

onMounted(buscar)
</script>

<template>
  <div>
    <div class="mb-5">
      <p class="font-display text-2xl font-semibold text-espresso-800">Buscar Villa</p>
      <p class="mt-1 text-sm text-espresso-800/50">Por # de villa o nombre del propietario</p>
    </div>

    <form class="mb-5 flex gap-2" @submit.prevent="buscar">
      <input
        v-model="q"
        type="text"
        placeholder="Ej. A-1 o Sánchez..."
        class="w-full max-w-sm rounded-lg border border-espresso-800/15 bg-cream-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
      />
      <button type="submit" class="rounded-lg bg-gradient-to-r from-wine-500 via-brand-500 to-gold-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">
        Buscar
      </button>
    </form>

    <div class="overflow-hidden rounded-xl border border-gold-300/30 bg-cream-50 shadow-sm">
      <table class="min-w-full divide-y divide-gold-300/20 text-sm">
        <thead class="bg-brand-50/60">
          <tr>
            <th class="px-4 py-2.5 text-left font-medium text-espresso-800/70">Villa</th>
            <th class="px-4 py-2.5 text-left font-medium text-espresso-800/70">Propietario</th>
            <th class="px-4 py-2.5 text-right font-medium text-espresso-800/70">Saldo</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gold-300/15">
          <tr
            v-for="v in villas"
            :key="v.villa"
            class="cursor-pointer transition hover:bg-brand-50/50"
            @click="villaSeleccionada = v.villa"
          >
            <td class="px-4 py-2.5 font-medium text-espresso-900">{{ v.villa }}</td>
            <td class="px-4 py-2.5 text-espresso-800/80">{{ v.nombre_completo }}</td>
            <td class="px-4 py-2.5 text-right" :class="claseSaldo(v.saldo)">
              {{ formatearMonto(v.saldo) }}
            </td>
          </tr>
          <tr v-if="!cargando && villas.length === 0">
            <td colspan="3" class="px-4 py-8 text-center text-espresso-800/40">Sin resultados</td>
          </tr>
        </tbody>
      </table>
    </div>

    <VillaModal
      v-if="villaSeleccionada"
      :villa-id="villaSeleccionada"
      @close="villaSeleccionada = null"
      @saved="villaSeleccionada = null"
    />
  </div>
</template>
