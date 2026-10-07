import { reactive } from 'vue'
import { fetchCsrfToken, fetchSession, logoutSession } from '../api/auth'

const roleNames = {
  1: 'Super Admin',
  2: 'Client Admin',
  3: 'Sales Manager',
  4: 'Sales Executive',
}

export function roleFor(id) {
  return roleNames[id] || 'User'
}

export const auth = reactive({
  user: null,
  mode: null,
  initialized: false,
  get homePath() {
    const prefixes = {
      1: 'super-admin',
      2: 'client-admin',
      3: 'manager',
      4: 'executive',
    }
    return `/${prefixes[this.user?.role_id] || 'login'}/dashboard`
  },
  setSession(user, mode) {
    this.user = user
    this.mode = mode
    sessionStorage.setItem('srm_user', JSON.stringify(user))
    sessionStorage.setItem('srm_auth_mode', mode)
  },
  async initialize() {
    if (this.initialized) return

    try {
      const csrfToken = await fetchCsrfToken()
      sessionStorage.setItem('srm_csrf_token', csrfToken)

      const token = sessionStorage.getItem('srm_access_token')
      const savedUser = sessionStorage.getItem('srm_user')
      if (token && savedUser && sessionStorage.getItem('srm_auth_mode') === 'token') {
        this.user = JSON.parse(savedUser)
        this.mode = 'token'
      } else {
        const user = await fetchSession()
        if (user) this.setSession(user, 'session')
      }
    } catch (error) {
      if (error.response?.status !== 401) {
        this.user = null
        this.mode = null
      }
    } finally {
      this.initialized = true
    }
  },
  async logout() {
    try {
      if (this.mode === 'session') await logoutSession()
    } finally {
      this.clear()
    }
  },
  clear() {
    this.user = null
    this.mode = null
    sessionStorage.removeItem('srm_access_token')
    sessionStorage.removeItem('srm_user')
    sessionStorage.removeItem('srm_auth_mode')
  },
})

window.addEventListener('srm:unauthorized', () => auth.clear())
