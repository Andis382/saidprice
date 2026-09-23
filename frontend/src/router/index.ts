import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuth } from '@/stores/auth'
import AppShell from '@/components/layout/AppShell.vue'

declare module 'vue-router' {
  interface RouteMeta {
    /** needs a signed-in user */
    auth?: boolean
    /** only for signed-out visitors (login, register) */
    guest?: boolean
    /** roles allowed; empty = everyone signed in */
    roles?: string[]
    /** which nav item to highlight for nested screens */
    nav?: string
    /** document title key */
    title?: string
  }
}

const routes: RouteRecordRaw[] = [
  { path: '/login', name: 'login', component: () => import('@/views/auth/LoginView.vue'), meta: { guest: true } },
  { path: '/register', name: 'register', component: () => import('@/views/auth/RegisterView.vue'), meta: { guest: true } },
  { path: '/join/:token', name: 'join', component: () => import('@/views/auth/JoinView.vue') },
  { path: '/q/:token', name: 'public-quote', component: () => import('@/views/PublicQuoteView.vue') },
  {
    path: '/',
    component: AppShell,
    meta: { auth: true },
    children: [
      { path: '', name: 'home', redirect: { name: 'quotes' } },
      { path: 'quotes', name: 'quotes', component: () => import('@/views/QuotesView.vue') },
      { path: 'quotes/new', name: 'quote-new', component: () => import('@/views/NewQuoteView.vue') },
      { path: 'quotes/:id(\\d+)', name: 'quote', component: () => import('@/views/QuoteDetailView.vue'), meta: { nav: 'quotes' } },
      { path: 'quotes/:id(\\d+)/edit', name: 'quote-edit', component: () => import('@/views/QuoteEditView.vue'), meta: { nav: 'quotes' } },
      { path: 'follow-ups', name: 'follow-ups', component: () => import('@/views/FollowUpsView.vue') },
      { path: 'stats', name: 'stats', component: () => import('@/views/StatsView.vue') },
      { path: 'customers', name: 'customers', component: () => import('@/views/CustomersView.vue') },
      { path: 'customers/:id(\\d+)', name: 'customer', component: () => import('@/views/CustomerDetailView.vue'), meta: { nav: 'customers' } },
      { path: 'messages', name: 'messages', component: () => import('@/views/MessagesView.vue') },
      { path: 'settings', name: 'settings', component: () => import('@/views/SettingsView.vue') },
    ],
  },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/views/NotFoundView.vue') },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior(to, from, saved) {
    if (saved) return saved
    if (to.hash) return { el: to.hash }
    if (to.path !== from.path) return { top: 0 }
  },
})

router.beforeEach(async (to) => {
  const auth = useAuth()
  if (!auth.ready) await auth.load()
  if (to.meta.auth && !auth.signedIn) {
    return { name: 'login', query: to.fullPath !== '/' ? { next: to.fullPath } : {} }
  }
  if (to.meta.guest && auth.signedIn) return { name: 'quotes' }
  const roles = to.matched.flatMap((r) => r.meta.roles ?? [])
  if (roles.length && !auth.hasRole(...roles)) return { name: 'quotes' }
})

export default router
