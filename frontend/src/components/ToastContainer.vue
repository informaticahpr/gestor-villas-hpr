<script setup lang="ts">
import { useToastStore } from '../stores/toast'

const toast = useToastStore()

const estilos: Record<string, string> = {
  success: 'border-emerald-500/30 bg-emerald-50 text-emerald-800',
  error: 'border-wine-500/30 bg-wine-500/10 text-wine-700',
  warning: 'border-gold-500/40 bg-gold-500/10 text-espresso-800',
  info: 'border-brand-500/30 bg-brand-50 text-brand-800',
}

const iconos: Record<string, string> = {
  success: 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
  error: 'M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-8.25 3.75h.008v.008H12.75v-.008z',
  warning: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
  info: 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z',
}
</script>

<template>
  <div class="pointer-events-none fixed right-4 top-4 z-[100] flex w-full max-w-sm flex-col gap-2">
    <TransitionGroup name="toast">
      <div
        v-for="t in toast.toasts"
        :key="t.id"
        class="pointer-events-auto flex items-start gap-2.5 rounded-lg border px-4 py-3 text-sm shadow-lg backdrop-blur"
        :class="estilos[t.tipo]"
      >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="mt-0.5 h-5 w-5 shrink-0">
          <path stroke-linecap="round" stroke-linejoin="round" :d="iconos[t.tipo]" />
        </svg>
        <span class="flex-1 leading-snug">{{ t.mensaje }}</span>
        <button type="button" class="shrink-0 text-current/50 hover:text-current" @click="toast.cerrar(t.id)">
          ✕
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.25s ease;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateX(24px);
}
.toast-leave-active {
  position: absolute;
  width: 100%;
}
</style>
