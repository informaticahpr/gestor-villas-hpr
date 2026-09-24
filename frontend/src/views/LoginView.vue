<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'

const auth = useAuthStore()
const toast = useToastStore()
const router = useRouter()
const route = useRoute()

const usuario = ref('')
const password = ref('')

async function onSubmit() {
  try {
    await auth.login(usuario.value, password.value)
    const next = (route.query.next as string) || '/'
    router.push(next)
  } catch (e) {
    const err = e as { response?: { status?: number; data?: { errors?: { usuario?: string[] }; message?: string } } }
    // La contraseña nunca se conserva en el campo tras un intento fallido: evita dejarla
    // expuesta en pantalla en un equipo compartido (ej. recepción del hotel).
    password.value = ''
    if (!err.response) {
      toast.error('No se pudo conectar con el servidor. Verifica que el backend esté en ejecución.')
    } else if (err.response.status === 429) {
      toast.error(err.response.data?.message ?? 'Demasiados intentos. Espera un minuto antes de volver a intentarlo.')
    } else if (err.response.status === 422) {
      toast.error(err.response.data?.errors?.usuario?.[0] ?? 'Usuario o contraseña incorrectos.')
    } else {
      toast.error('Ocurrió un error al iniciar sesión. Intenta de nuevo.')
    }
  }
}
</script>

<template>
  <div
    class="flex min-h-screen items-center justify-center px-4"
    style="background: radial-gradient(circle at 20% 15%, #fbe7d3 0%, #fbf7f0 45%, #f5eee1 100%)"
  >
    <div class="w-full max-w-sm">
      <div class="mb-6 flex justify-center">
        <img src="/logo.png" alt="Palma Real Hotel y Villas" class="h-32 w-auto drop-shadow-sm" />
      </div>

      <div class="rounded-2xl border border-gold-300/30 bg-cream-50 p-8 shadow-xl shadow-brand-900/5">
        <h1 class="mb-1 text-center font-display text-xl font-semibold text-espresso-800">
          Gestor de Villas
        </h1>
        <p class="mb-6 text-center text-sm text-espresso-800/60">Inicia sesión para continuar</p>

        <form class="space-y-4" @submit.prevent="onSubmit">
          <div>
            <label class="mb-1 block text-sm font-medium text-espresso-700">Usuario</label>
            <input
              v-model="usuario"
              type="text"
              autocomplete="username"
              required
              class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2 text-sm text-espresso-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
            />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-espresso-700">Contraseña</label>
            <input
              v-model="password"
              type="password"
              autocomplete="current-password"
              required
              class="w-full rounded-lg border border-espresso-800/15 bg-white px-3 py-2 text-sm text-espresso-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
            />
          </div>

          <button
            type="submit"
            :disabled="auth.cargando"
            class="w-full rounded-lg bg-gradient-to-r from-wine-500 via-brand-500 to-gold-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-90 disabled:opacity-50"
          >
            {{ auth.cargando ? 'Ingresando...' : 'Ingresar' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>
