<script setup>
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { loginSuperAdmin, loginWithToken } from '../api/auth'
import { auth } from '../auth/store'

const router = useRouter()
const email = ref('')
const password = ref('')
const errorMessage = ref('')
const submitting = ref(false)
const fieldErrors = ref({})

function validationErrors(error) {
  const errors = error.response?.data?.errors || {}
  return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0]]))
}

async function submit() {
  errorMessage.value = ''
  fieldErrors.value = {}
  submitting.value = true

  try {
    let user
    try {
      const result = await loginSuperAdmin({ email: email.value.trim(), password: password.value })
      user = result.user
      auth.setSession(user, 'session')
    } catch (error) {
      if (error.response?.status !== 403) throw error

      const result = await loginWithToken({ email: email.value.trim(), password: password.value })
      const loginData = result.data
      user = loginData?.data
      const token = loginData?.access_token

      if (!user || !token) throw new Error('Login response did not include a user and access token.')
      if (![2, 3, 4].includes(user.role_id)) throw new Error('This role is not enabled for this login.')

      sessionStorage.setItem('srm_access_token', token)
      auth.setSession(user, 'token')
    }

    if (!user?.role_id) {
      auth.clear()
      throw new Error('Your account does not have a recognized role.')
    }

    await router.replace(auth.homePath)
  } catch (error) {
    fieldErrors.value = validationErrors(error)
    errorMessage.value = error.response?.data?.message || error.message || 'Unable to sign in. Please try again.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <main class="login-page">
    <section class="login-card">
      <div class="login-brand">
        <div class="brand-icon brand-icon-large">S</div>
        <p class="eyebrow eyebrow-light">SRM WORKSPACE</p>
        <h1>One workspace.<br>Clear ownership.</h1>
        <p>Sign in to continue to your organization's management workspace.</p>
        <div class="login-decoration" aria-hidden="true"></div>
      </div>
      <div class="login-form-panel">
        <p class="eyebrow">WELCOME BACK</p>
        <h2>Sign in to SRM</h2>
        <p class="form-intro">Use your account credentials to access the portal.</p>
        <form class="form-stack" @submit.prevent="submit">
          <label class="field">
            <span>Email address</span>
            <input v-model="email" type="email" autocomplete="username" placeholder="name@company.com" required>
            <small v-if="fieldErrors.email" class="field-error">{{ fieldErrors.email }}</small>
          </label>
          <label class="field">
            <span>Password</span>
            <input v-model="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>
            <small v-if="fieldErrors.password" class="field-error">{{ fieldErrors.password }}</small>
          </label>
          <div v-if="errorMessage" class="notice notice-error" role="alert">{{ errorMessage }}</div>
          <button class="button button-primary button-wide" type="submit" :disabled="submitting">
            {{ submitting ? 'Signing in...' : 'Sign in' }}
            <span v-if="!submitting" aria-hidden="true">→</span>
          </button>
        </form>
        <p class="login-footnote">Need an Executive account? <RouterLink to="/register">Register here</RouterLink></p>
        <p class="login-footnote">Your access is determined by your assigned account role.</p>
      </div>
    </section>
  </main>
</template>
