<script setup lang="ts">
import type { FormatoExportacion } from '../lib/exportar'

const emit = defineEmits<{ exportar: [formato: FormatoExportacion] }>()
</script>

<template>
  <button
    type="button"
    class="rounded-lg border border-espresso-800/20 px-4 py-2 text-sm font-medium text-espresso-700 hover:bg-brand-50"
    @click="emit('exportar', 'excel')"
  >
    Exportar Excel
  </button>
  <button
    type="button"
    class="rounded-lg border border-espresso-800/20 px-4 py-2 text-sm font-medium text-espresso-700 hover:bg-brand-50"
    @click="emit('exportar', 'pdf')"
  >
    Exportar PDF
  </button>
</template>
