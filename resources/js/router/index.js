import { createRouter, createWebHistory } from 'vue-router'
import { auth } from '../auth/store'
import DashboardPage from '../views/DashboardPage.vue'
import ClientAdminTasksPage from '../views/ClientAdminTasksPage.vue'
import LoginPage from '../views/LoginPage.vue'
import ManagerExecutivesPage from '../views/ManagerExecutivesPage.vue'
import ManagementPage from '../views/ManagementPage.vue'
import ProfilePage from '../views/ProfilePage.vue'
import RegisterPage from '../views/RegisterPage.vue'

const routes = [
  { path: '/login', component: LoginPage, meta: { guestOnly: true, title: 'Sign in' } },
  { path: '/register', component: RegisterPage, meta: { guestOnly: true, title: 'Executive registration' } },
  { path: '/super-admin/dashboard', component: DashboardPage, meta: { roles: [1], title: 'Dashboard' } },
  { path: '/super-admin/organizations', component: ManagementPage, meta: { roles: [1], section: 'organizations', title: 'Organizations' } },
  { path: '/super-admin/client-admins', component: ManagementPage, meta: { roles: [1], section: 'client-admins', title: 'Client Admins' } },
  { path: '/super-admin/managers', component: ManagementPage, meta: { roles: [1], section: 'managers', title: 'Managers' } },
  { path: '/super-admin/executives', component: ManagementPage, meta: { roles: [1], section: 'executives', title: 'Executives' } },
  { path: '/super-admin/users', component: ManagementPage, meta: { roles: [1], section: 'users', title: 'Users' } },
  { path: '/super-admin/profile', component: ProfilePage, meta: { roles: [1], title: 'Profile' } },
  { path: '/client-admin/dashboard', component: DashboardPage, meta: { roles: [2], title: 'Dashboard' } },
  { path: '/client-admin/managers', component: ManagementPage, meta: { roles: [2], section: 'managers', title: 'Managers' } },
  { path: '/client-admin/executives', component: ManagementPage, meta: { roles: [2], section: 'executives', title: 'Executives' } },
  { path: '/client-admin/tasks', component: ClientAdminTasksPage, meta: { roles: [2], title: 'Assign Tasks' } },
  { path: '/client-admin/profile', component: ProfilePage, meta: { roles: [2], title: 'Profile' } },
  { path: '/manager/dashboard', component: DashboardPage, meta: { roles: [3], title: 'Dashboard' } },
  { path: '/manager/executives', component: ManagerExecutivesPage, meta: { roles: [3], title: 'My Executives' } },
  { path: '/manager/profile', component: ProfilePage, meta: { roles: [3], title: 'Profile' } },
  { path: '/executive/dashboard', component: DashboardPage, meta: { roles: [4], title: 'Dashboard' } },
  { path: '/executive/profile', component: ProfilePage, meta: { roles: [4], title: 'My Profile' } },
  { path: '/executive/account', component: ProfilePage, meta: { roles: [4], title: 'Profile' } },
  { path: '/:pathMatch(.*)*', redirect: '/login' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  await auth.initialize()

  if (to.meta.guestOnly) {
    return auth.user ? auth.homePath : true
  }

  if (!auth.user) return '/login'

  if (to.meta.roles && !to.meta.roles.includes(auth.user.role_id)) {
    return auth.homePath
  }

  return true
})

export default router
