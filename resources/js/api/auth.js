import http from './http'

export async function fetchCsrfToken() {
  const { data } = await http.get('/auth/csrf')
  return data.csrf_token
}

export async function loginSuperAdmin(credentials) {
  const { data } = await http.post('/auth/login', credentials)
  return data
}

export async function loginWithToken(credentials) {
  const { data } = await http.post('/execative/login', credentials)
  return data
}

export async function registerExecutive(payload) {
  const { data } = await http.post('/execative/register', payload)
  return data
}

export async function fetchRegistrationOrganizations() {
  const { data } = await http.get('/public/organizations')
  return data.organizations
}

export async function fetchSession() {
  const { data } = await http.get('/auth/me')
  return data.user
}

export async function updateProfile(payload) {
  const { data } = await http.put('/auth/profile', payload)
  return data
}

export async function logoutSession() {
  const { data } = await http.post('/auth/logout')
  return data
}
