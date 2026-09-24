<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    pagina: number
    ultimaPagina: number
    total: number
    porPagina: number
    opciones?: number[]
  }>(),
  { opciones: () => [10, 20, 50, 100] },
)

const emit = defineEmits<{
  'update:pagina': [pagina: number]
  'update:porPagina': [porPagina: number]
}>()

const desde = computed(() => (props.total === 0 ? 0 : (props.pagina - 1) * props.porPagina + 1))
const hasta = computed(() => Math.min(props.pagina * props.porPagina, props.total))

// Numeros de pagina visibles: siempre la primera, la ultima y las vecinas de la actual, con "…" en los saltos.
const paginasVisibles = computed<Array<number | '…'>>(() => {
  const ultima = props.ultimaPagina
  const actual = props.pagina
  if (ultima <= 7) return Array.from({ length: ultima }, (_, i) => i + 1)

  const set = new Set<number>([1, ultima, actual - 1, actual, actual + 1])
  if (actual <= 3) [2, 3, 4].forEach((p) => set.add(p))
  if (actual >= ultima - 2) [ultima - 1, ultima - 2, ultima - 3].forEach((p) => set.add(p))

  const ordenadas = [...set].filter((p) => p >= 1 && p <= ultima).sort((a, b) => a - b)
  const resultado: Array<number | '…'> = []
  ordenadas.forEach((p, i) => {
    if (i > 0 && p - ordenadas[i - 1] > 1) resultado.push('…')
    resultado.push(p)
  })
  return resultado
})

function ir(pagina: number) {
  if (pagina < 1 || pagina > props.ultimaPagina || pagina === props.pagina) return
  emit('update:pagina', pagina)
}

function cambiarTamano(evento: Event) {
  emit('update:porPagina', Number((evento.target as HTMLSelectElement).value))
}

const claseBoton =
  'rounded-lg border px-3 py-1.5 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-40'
</script>

<template>
  <div v-if="total > 0" class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-espresso-700">
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
      <label class="flex items-center gap-2">
        Mostrar
        <select
          :value="porPagina"
          class="rounded-lg border border-espresso-800/15 bg-white px-2.5 py-1.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
          @change="cambiarTamano"
        >
          <option v-for="n in opciones" :key="n" :value="n">{{ n }}</option>
        </select>
        registros
      </label>
      <span class="text-espresso-800/60">Mostrando {{ desde }}–{{ hasta }} de {{ total }}</span>
    </div>

    <nav v-if="ultimaPagina > 1" class="flex flex-wrap items-center gap-1" aria-label="Paginación">
      <button
        type="button"
        :class="[claseBoton, 'border-espresso-800/20 text-espresso-700 hover:bg-brand-50']"
        :disabled="pagina <= 1"
        @click="ir(pagina - 1)"
      >
        Anterior
      </button>

      <template v-for="(p, i) in paginasVisibles" :key="i">
        <span v-if="p === '…'" class="px-1.5 text-espresso-800/40">…</span>
        <button
          v-else
          type="button"
          :aria-current="p === pagina ? 'page' : undefined"
          :class="[
            claseBoton,
            'min-w-[2.25rem]',
            p === pagina
              ? 'border-brand-600 bg-brand-50 text-brand-700'
              : 'border-espresso-800/20 text-espresso-700 hover:bg-brand-50',
          ]"
          @click="ir(p)"
        >
          {{ p }}
        </button>
      </template>

      <button
        type="button"
        :class="[claseBoton, 'border-espresso-800/20 text-espresso-700 hover:bg-brand-50']"
        :disabled="pagina >= ultimaPagina"
        @click="ir(pagina + 1)"
      >
        Siguiente
      </button>
    </nav>
  </div>
</template>
