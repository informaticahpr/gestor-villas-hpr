<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'

const router = useRouter()
const auth = useAuthStore()
const ui = useUiStore()

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
    descripcion: 'Volver a imprimir un recibo ya emitido',
    accion: () => router.push({ name: 'reimpresion' }),
    // outline, distinto al resto (que son "fill"): se dibuja con stroke, ver template
    icon: 'M6.72 13.829a42.415 42.415 0 0110.56 0M6.34 18h11.318M6.34 18l.228 2.523a1.125 1.125 0 001.121 1.227h8.618a1.125 1.125 0 001.12-1.227L17.66 18M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0c.653.06 1.303.132 1.95.216 1.017.132 1.75 1.05 1.75 2.075V15.75a2.25 2.25 0 01-2.25 2.25h-1.083m-9.417-8.716V4.5A2.25 2.25 0 019 2.25h6a2.25 2.25 0 012.25 2.25v4.034M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z',
    outline: true,
  },
]
</script>

<template>
  <div>
    <div class="mb-8">
      <p class="font-display text-2xl font-semibold text-espresso-800">
        Bienvenido, {{ auth.user?.name }}
      </p>
      <p class="mt-1 text-sm text-espresso-800/50">¿Qué deseas hacer hoy?</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <button
        v-for="a in acciones"
        :key="a.titulo"
        class="group rounded-xl border border-gold-300/30 bg-cream-50 p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-lg hover:shadow-brand-900/5"
        @click="a.accion"
      >
        <span
          class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-wine-500 via-brand-500 to-gold-500 text-white shadow-sm"
        >
          <svg v-if="a.outline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" :d="a.icon" /></svg>
          <svg v-else viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path :d="a.icon" /></svg>
        </span>
        <p class="font-display font-semibold text-espresso-800">{{ a.titulo }}</p>
        <p class="mt-1 text-sm text-espresso-800/55">{{ a.descripcion }}</p>
      </button>

      <RouterLink
        v-if="auth.esDirectorOAdmin()"
        :to="{ name: 'configuracion' }"
        class="group rounded-xl border border-gold-300/30 bg-cream-50 p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-lg hover:shadow-brand-900/5"
      >
        <span
          class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-wine-500 via-brand-500 to-gold-500 text-white shadow-sm"
        >
          <svg viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
            <path
              d="M11.078 2.25c-.917 0-1.699.663-1.85 1.567l-.091.549a.798.798 0 01-.517.608 7.45 7.45 0 00-.478.198.798.798 0 01-.796-.064l-.453-.324a1.875 1.875 0 00-2.416.196l-.038.038a1.875 1.875 0 00-.196 2.416l.324.453a.798.798 0 01.064.796 7.448 7.448 0 00-.198.478.798.798 0 01-.608.517l-.55.092a1.875 1.875 0 00-1.566 1.849v.055c0 .917.663 1.699 1.567 1.85l.549.091c.281.047.508.25.608.517.06.163.127.323.198.478a.798.798 0 01-.064.796l-.324.453a1.875 1.875 0 00.196 2.416l.038.038c.638.638 1.659.7 2.416.196l.453-.324a.798.798 0 01.796-.064c.155.071.315.138.478.198.267.1.47.327.517.608l.092.55c.15.903.932 1.566 1.849 1.566h.055c.917 0 1.699-.663 1.85-1.567l.091-.549a.798.798 0 01.517-.608 7.52 7.52 0 00.478-.198.798.798 0 01.796.064l.453.324a1.875 1.875 0 002.416-.196l.038-.038c.638-.638.7-1.659.196-2.416l-.324-.453a.798.798 0 01-.064-.796c.071-.155.138-.315.198-.478.1-.267.327-.47.608-.517l.55-.091a1.875 1.875 0 001.566-1.85v-.055c0-.917-.663-1.699-1.567-1.85l-.549-.091a.798.798 0 01-.608-.517 7.507 7.507 0 00-.198-.478.798.798 0 01.064-.796l.324-.453a1.875 1.875 0 00-.196-2.416l-.038-.038a1.875 1.875 0 00-2.416-.196l-.453.324a.798.798 0 01-.796.064 7.462 7.462 0 00-.478-.198.798.798 0 01-.517-.608l-.091-.55a1.875 1.875 0 00-1.85-1.566h-.054zM12 15.75a3.75 3.75 0 100-7.5 3.75 3.75 0 000 7.5z"
            />
          </svg>
        </span>
        <p class="font-display font-semibold text-espresso-800">Configuración</p>
        <p class="mt-1 text-sm text-espresso-800/55">Usuarios y roles del sistema</p>
      </RouterLink>
    </div>
  </div>
</template>
