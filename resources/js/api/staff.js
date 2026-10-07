import http from './http'

export async function getStaff(roleId) {
  const { data } = await http.get(`/admin/staff/${roleId}`)
  return data.users
}

export async function getTrashedStaff(roleId) {
  const { data } = await http.get(`/admin/staff/${roleId}/trash`)
  return data.users
}

export async function createStaff(roleId, payload) {
  const { data } = await http.post(`/admin/staff/${roleId}`, payload)
  return data
}

export async function updateStaff(roleId, userId, payload) {
  const { data } = await http.put(`/admin/staff/${roleId}/${userId}`, payload)
  return data
}

export async function deleteStaff(roleId, userId) {
  const { data } = await http.delete(`/admin/staff/${roleId}/${userId}`)
  return data
}

export async function restoreStaff(roleId, userId) {
  const { data } = await http.post(`/admin/staff/${roleId}/${userId}/restore`)
  return data
}

export async function promoteExecutive(userId) {
  const { data } = await http.post(`/admin/executives/${userId}/promote`)
  return data
}

export async function getClientAdminStaff(roleId) {
  const { data } = await http.get(`/client-admin/staff/${roleId}`)
  return data.users
}

export async function getTrashedClientAdminStaff(roleId) {
  const { data } = await http.get(`/client-admin/staff/${roleId}/trash`)
  return data.users
}

export async function createClientAdminStaff(roleId, payload) {
  const { data } = await http.post(`/client-admin/staff/${roleId}`, payload)
  return data
}

export async function updateClientAdminStaff(roleId, userId, payload) {
  const { data } = await http.put(`/client-admin/staff/${roleId}/${userId}`, payload)
  return data
}

export async function deleteClientAdminStaff(roleId, userId) {
  const { data } = await http.delete(`/client-admin/staff/${roleId}/${userId}`)
  return data
}

export async function restoreClientAdminStaff(roleId, userId) {
  const { data } = await http.post(`/client-admin/staff/${roleId}/${userId}/restore`)
  return data
}

export async function promoteClientAdminExecutive(userId) {
  const { data } = await http.post(`/client-admin/staff/executives/${userId}/promote`)
  return data
}
