<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { auth, roleFor } from './auth/store'

const route = useRoute()
const router = useRouter()
const navigation = {
  1: [
    { label: 'Dashboard', path: '/super-admin/dashboard' },
    { label: 'Organizations', path: '/super-admin/organizations' },
    { label: 'Client Admins', path: '/super-admin/client-admins' },
    { label: 'Managers', path: '/super-admin/managers' },
    { label: 'Executives', path: '/super-admin/executives' },
    // { label: 'Users', path: '/super-admin/users' },
    { label: 'Profile', path: '/super-admin/profile' },
  ],
  2: [
    { label: 'Dashboard', path: '/client-admin/dashboard' },
    { label: 'Managers', path: '/client-admin/managers' },
    { label: 'Executives', path: '/client-admin/executives' },
    { label: 'Assign Tasks', path: '/client-admin/tasks' },
    { label: 'Profile', path: '/client-admin/profile' },
  ],
  3: [
    { label: 'Dashboard', path: '/manager/dashboard' },
    { label: 'My Executives', path: '/manager/executives' },
    { label: 'Profile', path: '/manager/profile' },
  ],
  4: [
    { label: 'Dashboard', path: '/executive/dashboard' },
    // { label: 'My Profile', path: '/executive/profile' },
    { label: 'Profile', path: '/executive/account' },
  ],
}
const links = computed(() => navigation[auth.user?.role_id] || [])
const currentRole = computed(() => roleFor(auth.user?.role_id))
const mobileMenuOpen = ref(false)
const loggingOut = ref(false)

onMounted(async () => {
  await auth.initialize()
  if (auth.user && route.path === '/login') {
    await router.replace(auth.homePath)
  }
})

watch(() => auth.user, async (user) => {
  if (!user && !route.meta.guestOnly) {
    await router.replace('/login')
  }
})

async function logout() {
  loggingOut.value = true
  try {
    await auth.logout()
    await router.replace('/login')
  } finally {
    loggingOut.value = false
  }
}
</script>

<template>
  <RouterView v-if="route.meta.guestOnly" />

  <main v-else-if="!auth.user" class="auth-transition">
    <span class="spinner"></span>
    <p>Returning to sign in…</p>
  </main>

  <div v-else class="shell">
    <aside class="sidebar" :class="{ 'sidebar-open': mobileMenuOpen }">
      <RouterLink class="brand" :to="auth.homePath" @click="mobileMenuOpen = false">
        <span class="brand-icon">S</span>
        <span><strong>SRM</strong><small>Management portal</small></span>
      </RouterLink>

      <div class="nav-caption">WORKSPACE</div>
      <nav class="side-nav" aria-label="Main navigation">
        <RouterLink
          v-for="item in links"
          :key="item.path"
          :to="item.path"
          class="nav-link"
          :class="{ 'nav-link-active': route.path === item.path }"
          @click="mobileMenuOpen = false"
        >
          <span class="nav-indicator"></span>{{ item.label }}
        </RouterLink>
      </nav>

      <div class="sidebar-bottom">
        <div class="security-note"><span class="security-dot"></span>Protected workspace</div>
        <button class="nav-link logout-link" :disabled="loggingOut" @click="logout">
          <span class="nav-indicator"></span>{{ loggingOut ? 'Signing out...' : 'Log out' }}
        </button>
      </div>
    </aside>

    <div v-if="mobileMenuOpen" class="mobile-scrim" @click="mobileMenuOpen = false"></div>

    <main class="main-area">
      <header class="topbar">
        <button class="mobile-menu-button" aria-label="Open navigation" @click="mobileMenuOpen = !mobileMenuOpen">☰</button>
        <div class="breadcrumb"><span>Workspace</span><span class="breadcrumb-slash">/</span><strong>{{ route.meta.title || 'Dashboard' }}</strong></div>
        <div class="account">
          <div class="account-copy"><strong>{{ auth.user.name }}</strong><span>{{ currentRole }}</span></div>
          <div class="avatar">{{ (auth.user.name || 'U').slice(0, 1).toUpperCase() }}</div>
        </div>
      </header>
      <div class="page-content">
        <RouterView />
      </div>
    </main>
  </div>
</template>
