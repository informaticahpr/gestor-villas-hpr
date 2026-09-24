<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'
import VillaModal from './VillaModal.vue'
import MovimientoModal from './MovimientoModal.vue'

const auth = useAuthStore()
const ui = useUiStore()
const router = useRouter()

async function onLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}

function onVillaCreada() {
  ui.mostrarCrearVilla = false
  router.push({ name: 'villas' })
}

function iniciales(nombre: string | undefined): string {
  if (!nombre) return ''
  return nombre
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase())
    .join('')
}

const iconBuscar = 'M11 4a7 7 0 104.9 12.02l4.54 4.54a1 1 0 001.42-1.42l-4.54-4.54A7 7 0 0011 4zm-5 7a5 5 0 1110 0 5 5 0 01-10 0z'
const iconCrear = 'M12 4a1 1 0 011 1v6h6a1 1 0 110 2h-6v6a1 1 0 11-2 0v-6H5a1 1 0 110-2h6V5a1 1 0 011-1z'
const iconCargo = 'M7 8a3 3 0 013-3h7a1 1 0 010 2h-7a1 1 0 000 2h4a3 3 0 010 6h-1a1 1 0 110-2h1a1 1 0 000-2h-4a3 3 0 01-3-3zm0 9a1 1 0 011-1h9a1 1 0 110 2H8a1 1 0 01-1-1z'
const iconImpresora =
  'M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z'
const iconReportes ='M4 20V10a1 1 0 112 0v10a1 1 0 11-2 0zm7 0V4a1 1 0 112 0v16a1 1 0 11-2 0zm7 0v-7a1 1 0 112 0v7a1 1 0 11-2 0z'
const iconConfig =
  'M11.078 2.25c-.917 0-1.699.663-1.85 1.567l-.091.549a.798.798 0 01-.517.608 7.45 7.45 0 00-.478.198.798.798 0 01-.796-.064l-.453-.324a1.875 1.875 0 00-2.416.196l-.038.038a1.875 1.875 0 00-.196 2.416l.324.453a.798.798 0 01.064.796 7.448 7.448 0 00-.198.478.798.798 0 01-.608.517l-.55.092a1.875 1.875 0 00-1.566 1.849v.055c0 .917.663 1.699 1.567 1.85l.549.091c.281.047.508.25.608.517.06.163.127.323.198.478a.798.798 0 01-.064.796l-.324.453a1.875 1.875 0 00.196 2.416l.038.038c.638.638 1.659.7 2.416.196l.453-.324a.798.798 0 01.796-.064c.155.071.315.138.478.198.267.1.47.327.517.608l.092.55c.15.903.932 1.566 1.849 1.566h.055c.917 0 1.699-.663 1.85-1.567l.091-.549a.798.798 0 01.517-.608 7.52 7.52 0 00.478-.198.798.798 0 01.796.064l.453.324a1.875 1.875 0 002.416-.196l.038-.038c.638-.638.7-1.659.196-2.416l-.324-.453a.798.798 0 01-.064-.796c.071-.155.138-.315.198-.478.1-.267.327-.47.608-.517l.55-.091a1.875 1.875 0 001.566-1.85v-.055c0-.917-.663-1.699-1.567-1.85l-.549-.091a.798.798 0 01-.608-.517 7.507 7.507 0 00-.198-.478.798.798 0 01.064-.796l.324-.453a1.875 1.875 0 00-.196-2.416l-.038-.038a1.875 1.875 0 00-2.416-.196l-.453.324a.798.798 0 01-.796.064 7.462 7.462 0 00-.478-.198.798.798 0 01-.517-.608l-.091-.55a1.875 1.875 0 00-1.85-1.566h-.054zM12 15.75a3.75 3.75 0 100-7.5 3.75 3.75 0 000 7.5z'
</script>

<template>
  <div class="min-h-screen bg-cream-100">
    <header class="sticky top-0 z-40 border-b border-gold-300/40 bg-cream-50/95 backdrop-blur">
      <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-2">
        <RouterLink :to="{ name: 'home' }" class="flex items-center gap-3">
          <img src="/logo.png" alt="Palma Real Hotel y Villas" class="h-16 w-auto" />
          <span class="hidden whitespace-nowrap border-l border-espresso-800/15 pl-3 font-display text-xl font-semibold tracking-wide text-espresso-800 sm:block">
            Gestor de Villas
          </span>
        </RouterLink>

        <div class="flex items-center gap-2 text-sm">
          <span
            class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-wine-500 via-brand-500 to-gold-500 text-xs font-semibold text-white shadow-sm"
          >
            {{ iniciales(auth.user?.name) }}
          </span>
          <span class="hidden text-espresso-700 md:block">
            {{ auth.user?.name }}
            <span class="text-espresso-800/50">· {{ auth.user?.rol }}</span>
          </span>
          <button
            class="ml-1 rounded-md px-2 py-1.5 text-espresso-800/60 transition hover:bg-brand-50 hover:text-wine-600"
            @click="onLogout"
          >
            Salir
          </button>
        </div>
      </div>

      <nav class="border-t border-gold-300/25 bg-gradient-to-r from-brand-50/50 via-cream-50 to-brand-50/50">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-center gap-1.5 px-4 py-2 text-sm">
          <RouterLink
            :to="{ name: 'villas' }"
            class="flex items-center gap-1.5 rounded-full px-4 py-1.5 font-medium text-espresso-700 transition hover:bg-white hover:text-brand-700 hover:shadow-sm"
            active-class="!bg-gradient-to-r !from-wine-500 !via-brand-500 !to-gold-500 !text-white !shadow-sm"
          >
            <svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4 shrink-0"><path :d="iconBuscar" /></svg>
            Buscar Villa
          </RouterLink>
          <button
            class="flex items-center gap-1.5 rounded-full px-4 py-1.5 font-medium text-espresso-700 transition hover:bg-white hover:text-brand-700 hover:shadow-sm"
            @click="ui.mostrarCrearVilla = true"
          >
            <svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4 shrink-0"><path :d="iconCrear" /></svg>
            Crear Villa
          </button>
          <button
            class="flex items-center gap-1.5 rounded-full px-4 py-1.5 font-medium text-espresso-700 transition hover:bg-white hover:text-brand-700 hover:shadow-sm"
            @click="ui.mostrarMovimiento = true"
          >
            <svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4 shrink-0"><path :d="iconCargo" /></svg>
            Cargo/Crédito
          </button>
          <RouterLink
            :to="{ name: 'reportes' }"
            class="flex items-center gap-1.5 rounded-full px-4 py-1.5 font-medium text-espresso-700 transition hover:bg-white hover:text-brand-700 hover:shadow-sm"
            active-class="!bg-gradient-to-r !from-wine-500 !via-brand-500 !to-gold-500 !text-white !shadow-sm"
          >
            <svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4 shrink-0"><path :d="iconReportes" /></svg>
            Reportes
          </RouterLink>
          <RouterLink
            :to="{ name: 'reimpresion' }"
            class="flex items-center gap-1.5 rounded-full px-4 py-1.5 font-medium text-espresso-700 transition hover:bg-white hover:text-brand-700 hover:shadow-sm"
            active-class="!bg-gradient-to-r !from-wine-500 !via-brand-500 !to-gold-500 !text-white !shadow-sm"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" :d="iconImpresora" /></svg>
            Reimpresión
          </RouterLink>
          <RouterLink
            v-if="auth.esDirectorOAdmin()"
            :to="{ name: 'configuracion' }"
            class="flex items-center gap-1.5 rounded-full px-4 py-1.5 font-medium text-espresso-700 transition hover:bg-white hover:text-brand-700 hover:shadow-sm"
            active-class="!bg-gradient-to-r !from-wine-500 !via-brand-500 !to-gold-500 !text-white !shadow-sm"
          >
            <svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4 shrink-0"><path :d="iconConfig" /></svg>
            Configuración
          </RouterLink>
        </div>
      </nav>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8">
      <RouterView />
    </main>

    <VillaModal v-if="ui.mostrarCrearVilla" @close="ui.mostrarCrearVilla = false" @saved="onVillaCreada" />
    <MovimientoModal v-if="ui.mostrarMovimiento" @close="ui.mostrarMovimiento = false" />
  </div>
</template>
