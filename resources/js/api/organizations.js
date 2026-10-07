import http from './http'

export async function getOrganizations() {
  const { data } = await http.get('/admin/organizations')
  return data.organizations
}

export async function getTrashedOrganizations() {
  const { data } = await http.get('/admin/organizations/trash')
  return data.organizations
}

export async function createOrganization(payload) {
  const { data } = await http.post('/admin/organizations', payload)
  return data
}

export async function updateOrganization(id, payload) {
  const { data } = await http.put(`/admin/organizations/${id}`, payload)
  return data
}

export async function deleteOrganization(id) {
  const { data } = await http.delete(`/admin/organizations/${id}`)
  return data
}

export async function restoreOrganization(id) {
  const { data } = await http.post(`/admin/organizations/${id}/restore`)
  return data
}
