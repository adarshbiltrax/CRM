import http from './http'

export async function getClients() {
  const { data } = await http.get('/admin/clients')
  return data.clients
}

export async function getTrashedClients() {
  const { data } = await http.get('/admin/clients/trash')
  return data.clients
}

export async function createClient(payload) {
  const { data } = await http.post('/admin/clients', payload)
  return data
}

export async function updateClient(id, payload) {
  const { data } = await http.put(`/admin/clients/${id}`, payload)
  return data
}

export async function deleteClient(id) {
  const { data } = await http.delete(`/admin/clients/${id}`)
  return data
}

export async function restoreClient(id) {
  const { data } = await http.post(`/admin/clients/${id}/restore`)
  return data
}
