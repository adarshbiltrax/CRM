<script setup>
import { reactive, ref } from 'vue'
import { updateProfile } from '../api/auth'
import { auth, roleFor } from '../auth/store'

const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const fieldErrors = ref({})
const form = reactive({
  name: auth.user.name,
  email: auth.user.email,
  current_password: '',
  password: '',
  password_confirmation: '',
})

async function save() {
  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''
  fieldErrors.value = {}

  try {
    const payload = {
      name: form.name.trim(),
      email: form.email.trim(),
      current_password: form.password ? form.current_password : undefined,
      password: form.password || undefined,
      password_confirmation: form.password ? form.password_confirmation : undefined,
    }
    const result = await updateProfile(payload)
    auth.user = result.user
    sessionStorage.setItem('srm_user', JSON.stringify(result.user))
    form.name = result.user.name
    form.email = result.user.email
    form.current_password = ''
    form.password = ''
    form.password_confirmation = ''
    successMessage.value = result.message
  } catch (error) {
    fieldErrors.value = Object.fromEntries(
      Object.entries(error.response?.data?.errors || {}).map(([key, messages]) => [key, messages[0]]),
    )
    errorMessage.value = error.response?.data?.message || 'Unable to update your profile.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <section>
    <div class="page-heading"><div><p class="eyebrow">ACCOUNT</p><h1>My Profile</h1><p class="page-subtitle">View and update your account details</p></div></div>
    <section class="panel profile-panel">
      <div class="profile-heading"><span class="profile-avatar">{{ auth.user.name.slice(0, 1).toUpperCase() }}</span><div><h2>{{ auth.user.name }}</h2><span class="role-pill">{{ roleFor(auth.user.role_id) }}</span></div></div>
      <dl class="profile-details"><div><dt>Email address</dt><dd>{{ auth.user.email }}</dd></div><div><dt>Account role</dt><dd>{{ roleFor(auth.user.role_id) }}</dd></div><div><dt>Account status</dt><dd><span class="status-pill" :class="auth.user.status === 1 ? 'status-active' : 'status-inactive'"><span></span>{{ auth.user.status === 1 ? 'Active' : 'Inactive' }}</span></dd></div></dl>
      <form class="crud-form" @submit.prevent="save">
        <div v-if="successMessage" class="notice notice-success" role="status">{{ successMessage }}</div>
        <div v-if="errorMessage" class="notice notice-error" role="alert">{{ errorMessage }}</div>
        <label class="field"><span>Name</span><input v-model="form.name" required maxlength="100" autocomplete="name"><small v-if="fieldErrors.name" class="field-error">{{ fieldErrors.name }}</small></label>
        <label class="field"><span>Email address</span><input v-model="form.email" type="email" required maxlength="255" autocomplete="email"><small v-if="fieldErrors.email" class="field-error">{{ fieldErrors.email }}</small></label>
        <hr>
        <p class="muted-copy">Leave password fields blank to keep your current password. To change it, enter your current password and a new password.</p>
        <label class="field"><span>Current password</span><input v-model="form.current_password" type="password" autocomplete="current-password" :required="Boolean(form.password)"><small v-if="fieldErrors.current_password" class="field-error">{{ fieldErrors.current_password }}</small></label>
        <label class="field"><span>New password</span><input v-model="form.password" type="password" minlength="8" autocomplete="new-password"><small v-if="fieldErrors.password" class="field-error">{{ fieldErrors.password }}</small></label>
        <label class="field"><span>Confirm new password</span><input v-model="form.password_confirmation" type="password" minlength="8" autocomplete="new-password"><small v-if="fieldErrors.password_confirmation" class="field-error">{{ fieldErrors.password_confirmation }}</small></label>
        <footer class="crud-actions"><button class="button button-primary" type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save profile' }}</button></footer>
      </form>
    </section>
  </section>
</template>
