<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { fetchRegistrationOrganizations, registerExecutive } from '../api/auth'
import { auth } from '../auth/store'

const router = useRouter()
const organizations = ref([])
const name = ref('')
const email = ref('')
const organizationId = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const loadingOrganizations = ref(false)
const submitting = ref(false)
const errorMessage = ref('')
const fieldErrors = ref({})

async function loadOrganizations() {
  loadingOrganizations.value = true
  errorMessage.value = ''
  try {
    organizations.value = await fetchRegistrationOrganizations()
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Unable to load organizations. Please try again.'
  } finally {
    loadingOrganizations.value = false
  }
}

function validationErrors(error) {
  return Object.fromEntries(
    Object.entries(error.response?.data?.errors || {}).map(([field, messages]) => [field, messages[0]]),
  )
}

async function submit() {
  submitting.value = true
  errorMessage.value = ''
  fieldErrors.value = {}

  try {
    const result = await registerExecutive({
      name: name.value.trim(),
      email: email.value.trim(),
      orgnization_id: Number(organizationId.value),
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    const loginData = result.data
    const user = loginData?.data
    const token = loginData?.access_token

    if (!user || !token || user.role_id !== 4) {
      throw new Error('Registration response did not include a valid Executive account and access token.')
    }

    sessionStorage.setItem('srm_access_token', token)
    auth.setSession(user, 'token')
    await router.replace(auth.homePath)
  } catch (error) {
    fieldErrors.value = validationErrors(error)
    errorMessage.value = error.response?.data?.message || error.message || 'Unable to create your account.'
  } finally {
    submitting.value = false
  }
}

onMounted(loadOrganizations)
</script>

<template>
  <main class="login-page">
    <section class="login-card register-card">
      <div class="login-brand">
        <div class="brand-icon brand-icon-large">S</div>
        <p class="eyebrow eyebrow-light">EXECUTIVE WORKSPACE</p>
        <h1>Join your<br>organization.</h1>
        <p>Create an Executive account to receive work and tasks from your organization.</p>
        <div class="login-decoration" aria-hidden="true"></div>
      </div>
      <div class="login-form-panel">
        <p class="eyebrow">GET STARTED</p>
        <h2>Create an Executive account</h2>
        <p class="form-intro">Choose your organization and enter your account details.</p>
        <form class="form-stack" @submit.prevent="submit">
          <label class="field">
            <span>Full name</span>
            <input v-model="name" type="text" autocomplete="name" maxlength="100" placeholder="Your name" required>
            <small v-if="fieldErrors.name" class="field-error">{{ fieldErrors.name }}</small>
          </label>
          <label class="field">
            <span>Email address</span>
            <input v-model="email" type="email" autocomplete="email" maxlength="255" placeholder="name@company.com" required>
            <small v-if="fieldErrors.email" class="field-error">{{ fieldErrors.email }}</small>
          </label>
          <label class="field">
            <span>Organization</span>
            <select v-model="organizationId" required :disabled="loadingOrganizations || organizations.length === 0">
              <option value="" disabled>{{ loadingOrganizations ? 'Loading organizations…' : 'Select your organization' }}</option>
              <option v-for="organization in organizations" :key="organization.id" :value="String(organization.id)">{{ organization.name }}</option>
            </select>
            <small v-if="fieldErrors.orgnization_id" class="field-error">{{ fieldErrors.orgnization_id }}</small>
          </label>
          <label class="field">
            <span>Password</span>
            <input v-model="password" type="password" autocomplete="new-password" minlength="8" placeholder="At least 8 characters" required>
            <small v-if="fieldErrors.password" class="field-error">{{ fieldErrors.password }}</small>
          </label>
          <label class="field">
            <span>Confirm password</span>
            <input v-model="passwordConfirmation" type="password" autocomplete="new-password" minlength="8" placeholder="Re-enter your password" required>
          </label>
          <div v-if="!loadingOrganizations && organizations.length === 0" class="notice notice-info">No active organizations are available for registration yet.</div>
          <div v-if="errorMessage" class="notice notice-error" role="alert">{{ errorMessage }}</div>
          <button class="button button-primary button-wide" type="submit" :disabled="submitting || loadingOrganizations || organizations.length === 0">
            {{ submitting ? 'Creating account…' : 'Create Executive account' }}
            <span v-if="!submitting" aria-hidden="true">→</span>
          </button>
        </form>
        <p class="login-footnote">Already have an account? <RouterLink to="/login">Sign in</RouterLink></p>
      </div>
    </section>
  </main>
</template>
