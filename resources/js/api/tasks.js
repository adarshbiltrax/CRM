import http from './http'

export async function getFreeTaskTemplates() {
  const response = await fetch('https://dummyjson.com/todos?limit=30')

  if (!response.ok) {
    throw new Error(`Task template service returned ${response.status}.`)
  }

  const result = await response.json()
  if (!Array.isArray(result.todos)) {
    throw new Error('Task template service returned an invalid response.')
  }

  return result.todos.map((todo) => {
    if (!Number.isInteger(todo.id) || typeof todo.todo !== 'string' || typeof todo.completed !== 'boolean') {
      throw new Error('Task template service returned an invalid task.')
    }

    return {
      id: todo.id,
      title: todo.todo,
      completed: todo.completed,
    }
  })
}

export async function getManagerExecutives() {
  const { data } = await http.get('/manager/executives')
  return data.executives
}

export async function getClientAdminAssignedTasks() {
  const { data } = await http.get('/client-admin/tasks')
  return data.tasks
}

export async function assignTasksToManager(managerId, tasks) {
  const { data } = await http.post(`/client-admin/tasks/managers/${managerId}/assign`, { tasks })
  return data
}

export async function getAvailableManagerTasks() {
  const { data } = await http.get('/manager/tasks/available')
  return data.tasks
}

export async function assignManagerTasks(executiveId, taskIds) {
  const { data } = await http.post('/manager/tasks/assign', {
    executive_id: executiveId,
    task_ids: taskIds,
  })
  return data
}

export async function getExecutiveTasks(executiveId) {
  const { data } = await http.get(`/manager/executives/${executiveId}/tasks`)
  return data.tasks
}

export async function createTask(executiveId, payload) {
  const { data } = await http.post(`/manager/executives/${executiveId}/tasks`, payload)
  return data
}

export async function updateTask(taskId, payload) {
  const { data } = await http.put(`/manager/tasks/${taskId}`, payload)
  return data
}

export async function deleteTask(taskId) {
  const { data } = await http.delete(`/manager/tasks/${taskId}`)
  return data
}

export async function getMyTasks() {
  const { data } = await http.get('/executive/tasks')
  return data.tasks
}
