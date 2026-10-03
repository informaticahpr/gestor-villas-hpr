import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import App from './App.vue'
import router from './router'
import api from './lib/api'
import { useAuthStore } from './stores/auth'
import { useToastStore } from './stores/toast'

const pinia = createPinia()
createApp(App).use(pinia).use(router).mount('#app')

// Sesion vencida (inactividad, cambio de contraseña hecho por el Administrador, reinicio del servidor):
// el backend responde 401 (Unauthenticated) o 419 (token CSRF vencido). En vez de mostrar ese error
// en la pantalla donde estaba, se avisa una sola vez y se lleva al login, que al entrar regresa a la
// misma pagina. La peticion que fallo se queda pendiente a proposito: la pantalla se desmonta al
// navegar al login, asi no aparece ademas su propio mensaje de error.
let redirigiendo = false
api.interceptors.response.use(undefined, (error) => {
  const status = error?.response?.status
  const auth = useAuthStore(pinia)

  if ((status === 401 || status === 419) && auth.user) {
    if (!redirigiendo) {
      redirigiendo = true
      auth.user = null
      useToastStore(pinia).warning('Tu sesión expiró. Vuelve a iniciar sesión para continuar.')
      const actual = router.currentRoute.value
      router.push({ name: 'login', query: actual.name === 'login' ? {} : { next: actual.fullPath } }).finally(() => {
        redirigiendo = false
      })
    }
    return new Promise(() => {})
  }

  return Promise.reject(error)
})
