import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AppShell from '../components/AppShell.vue'
import LoginView from '../views/LoginView.vue'
import HomeView from '../views/HomeView.vue'
import VillasView from '../views/VillasView.vue'
import ReportesView from '../views/ReportesView.vue'
import ReimpresionView from '../views/ReimpresionView.vue'
import ConfiguracionView from '../views/ConfiguracionView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { public: true },
    },
    {
      path: '/',
      component: AppShell,
      children: [
        { path: '', name: 'home', component: HomeView },
        { path: 'villas', name: 'villas', component: VillasView },
        { path: 'reportes', name: 'reportes', component: ReportesView },
        { path: 'reimpresion', name: 'reimpresion', component: ReimpresionView },
        {
          path: 'configuracion',
          name: 'configuracion',
          component: ConfiguracionView,
          meta: { requiereAdmin: true },
        },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.listo) {
    await auth.fetchUser()
  }

  if (!to.meta.public && !auth.user) {
    return { name: 'login', query: { next: to.fullPath } }
  }

  if (to.name === 'login' && auth.user) {
    return { name: 'home' }
  }

  if (to.meta.requiereAdmin && !auth.esDirectorOAdmin()) {
    return { name: 'home' }
  }

  return true
})

export default router
