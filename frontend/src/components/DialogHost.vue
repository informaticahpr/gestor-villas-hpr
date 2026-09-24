<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import { useDialogStore } from '../stores/dialog'
import { useEscapeKey } from '../lib/useEscapeKey'

const dialog = useDialogStore()

const password = ref('')
const repetir = ref('')
const inputPassword = ref<HTMLInputElement | null>(null)
const botonConfirmar = ref<HTMLButtonElement | null>(null)

const MIN_PASSWORD = 8

const errorPassword = computed(() => {
  if (dialog.activo?.tipo !== 'password') return ''
  if (password.value.length > 0 && password.value.length < MIN_PASSWORD) {
    return `La contraseña debe tener al menos ${MIN_PASSWORD} caracteres.`
  }
  if (repetir.value.length > 0 && password.value !== repetir.value) {
    return 'Las contraseñas no coinciden.'
  }
  return ''
})

const passwordValido = computed(
  () => password.value.length >= MIN_PASSWORD && password.value === repetir.value,
)

// Al abrir un diálogo: limpia los campos y enfoca el primer control útil.
watch(
  () => dialog.activo,
  async (d) => {
    password.value = ''
    repetir.value = ''
    if (!d) return
    await nextTick()
    if (d.tipo === 'password') inputPassword.value?.focus()
    else botonConfirmar.value?.focus()
  },
)

function cancelar() {
  dialog.cerrar(dialog.activo?.tipo === 'confirmar' ? false : null)
}

// Prioritario: si hay un dialogo abierto, Escape solo lo cierra a el y no al modal de abajo.
useEscapeKey(
  () => {
    if (!dialog.activo) return false
    cancelar()
    return true
  },
  { prioritario: true },
)

function aceptar() {
  const d = dialog.activo
  if (!d) return
  if (d.tipo === 'confirmar') {
    dialog.cerrar(true)
  } else if (passwordValido.value) {
    dialog.cerrar(password.value)
  }
}
</script>

<template>
  <div
    v-if="dialog.activo"
    class="fixed inset-0 z-[60] flex items-center justify-center bg-espresso-900/50 p-4 backdrop-blur-sm"
    role="dialog"
    aria-modal="true"
    @click.self="cancelar"
  >
    <form class="w-full max-w-md rounded-2xl bg-cream-50 shadow-2xl shadow-espresso-900/20" @submit.prevent="aceptar">
      <div class="rounded-t-2xl border-b border-gold-300/30 bg-gradient-to-r from-brand-50/70 to-cream-50 px-6 py-4">
        <h2 class="font-display text-lg font-semibold text-espresso-800">{{ dialog.activo.titulo }}</h2>
      </div>

      <div class="space-y-4 px-6 py-5">
        <p class="text-sm text-espresso-800/80">{{ dialog.activo.mensaje }}</p>

        <template v-if="dialog.activo.tipo === 'password'">
          <div>
            <label class="mb-1 block text-sm font-medium text-espresso-700">Nueva contraseña</label>
            <input
              ref="inputPassword"
              v-model="password"
              type="password"
              autocomplete="new-password"
              class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2 text-sm text-espresso-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
            />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-espresso-700">Repetir contraseña</label>
            <input
              v-model="repetir"
              type="password"
              autocomplete="new-password"
              class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2 text-sm text-espresso-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
            />
          </div>
          <p v-if="errorPassword" class="text-sm text-wine-600">{{ errorPassword }}</p>
        </template>
      </div>

      <div class="flex justify-end gap-2 rounded-b-2xl border-t border-gold-300/30 px-6 py-4">
        <button
          type="button"
          class="rounded-lg px-4 py-2 text-sm font-medium text-espresso-700 hover:bg-espresso-800/5"
          @click="cancelar"
        >
          Cancelar
        </button>
        <button
          ref="botonConfirmar"
          type="submit"
          :disabled="dialog.activo.tipo === 'password' && !passwordValido"
          class="rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90 disabled:opacity-50"
          :class="
            dialog.activo.tipo === 'confirmar' && dialog.activo.peligro
              ? 'bg-wine-600'
              : 'bg-gradient-to-r from-wine-500 via-brand-500 to-gold-500'
          "
        >
          {{ dialog.activo.tipo === 'confirmar' ? (dialog.activo.textoConfirmar ?? 'Aceptar') : 'Cambiar contraseña' }}
        </button>
      </div>
    </form>
  </div>
</template>
