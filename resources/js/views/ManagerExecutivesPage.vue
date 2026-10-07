<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import {
  assignManagerTasks,
  createTask,
  deleteTask,
  getAvailableManagerTasks,
  getExecutiveTasks,
  getManagerExecutives,
  updateTask,
} from '../api/tasks'

const executives = ref([])
const tasks = ref([])
const availableTasks = ref([])
const selectedExecutiveId = ref('')
const selectedTaskIds = ref([])
const loading = ref(false)
const saving = ref(false)
const modalOpen = ref(false)
const editingTaskId = ref(null)
const errorMessage = ref('')
const successMessage = ref('')
const fieldErrors = ref({})
const form = reactive(emptyForm())

function emptyForm() {
  return {
    title: '',
    description: '',
    status: 'pending',
    due_date: '',
  }
}

const selectedExecutive = computed(() => executives.value.find((person) => String(person.id) === selectedExecutiveId.value))

watch(selectedExecutiveId, loadTasks)

async function loadTasks() {
  if (!selectedExecutiveId.value) {
    tasks.value = []
    return
  }

  loading.value = true
  errorMessage.value = ''
  try {
    tasks.value = await getExecutiveTasks(selectedExecutiveId.value)
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Unable to load this Executive’s tasks.'
  } finally {
    loading.value = false
  }
}

async function loadExecutives() {
  loading.value = true
  errorMessage.value = ''
  try {
    executives.value = await getManagerExecutives()
    if (executives.value.length && !selectedExecutiveId.value) {
      selectedExecutiveId.value = String(executives.value[0].id)
    }
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Unable to load your assigned Executives.'
  } finally {
    loading.value = false
  }
}

async function loadAvailableTasks() {
  loading.value = true
  errorMessage.value = ''
  try {
    availableTasks.value = await getAvailableManagerTasks()
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Unable to load tasks assigned to you.'
  } finally {
    loading.value = false
  }
}

async function assignSelectedTasks() {
  if (!selectedTaskIds.value.length || !selectedExecutive.value) return

  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''
  try {
    const result = await assignManagerTasks(Number(selectedExecutiveId.value), selectedTaskIds.value.map(Number))
    successMessage.value = result.message
    selectedTaskIds.value = []
    await loadAvailableTasks()
    await loadTasks()
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Unable to assign this task.'
  } finally {
    saving.value = false
  }
}

function openCreate() {
  editingTaskId.value = null
  Object.assign(form, emptyForm())
  fieldErrors.value = {}
  errorMessage.value = ''
  modalOpen.value = true
}

function openEdit(task) {
  editingTaskId.value = task.id
  Object.assign(form, {
    title: task.title,
    description: task.description || '',
    status: task.status,
    due_date: task.due_date ? task.due_date.slice(0, 10) : '',
  })
  fieldErrors.value = {}
  errorMessage.value = ''
  modalOpen.value = true
}

async function saveTask() {
  saving.value = true
  errorMessage.value = ''
  fieldErrors.value = {}

  const payload = {
    title: form.title.trim(),
    description: form.description.trim() || null,
    status: form.status,
    due_date: form.due_date || null,
  }

  try {
    if (editingTaskId.value) {
      await updateTask(editingTaskId.value, payload)
      successMessage.value = 'Task updated successfully.'
    } else {
      await createTask(selectedExecutiveId.value, payload)
      successMessage.value = 'Task assigned successfully.'
    }
    modalOpen.value = false
    await loadTasks()
  } catch (error) {
    fieldErrors.value = Object.fromEntries(
      Object.entries(error.response?.data?.errors || {}).map(([key, messages]) => [key, messages[0]]),
    )
    errorMessage.value = error.response?.data?.message || 'Unable to save this task.'
  } finally {
    saving.value = false
  }
}

async function removeTask(task) {
  if (!window.confirm(`Delete task "${task.title}"?`)) return

  errorMessage.value = ''
  successMessage.value = ''
  try {
    await deleteTask(task.id)
    successMessage.value = 'Task deleted successfully.'
    await loadTasks()
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Unable to delete this task.'
  }
}

onMounted(() => {
  loadExecutives()
  loadAvailableTasks()
})
</script>

<template>
  <section>
    <div class="page-heading">
      <div>
        <p class="eyebrow">TEAM WORKSPACE</p>
        <h1>My Executives</h1>
        <p class="page-subtitle">View only Executives assigned to you and manage their tasks.</p>
      </div>
    </div>

    <div v-if="successMessage" class="notice notice-success" role="status">{{ successMessage }}</div>
    <div v-if="errorMessage" class="notice notice-error" role="alert">{{ errorMessage }}</div>

    <section class="panel">
      <div class="table-toolbar">
        <div><h2>Assigned Executives</h2><p>{{ executives.length }} Executive{{ executives.length === 1 ? '' : 's' }} assigned to you</p></div>
        <label v-if="executives.length" class="field executive-selector">
          <span>Choose Executive</span>
          <select v-model="selectedExecutiveId">
            <option v-for="executive in executives" :key="executive.id" :value="String(executive.id)">{{ executive.name }}</option>
          </select>
        </label>
      </div>

      <div v-if="loading && !executives.length" class="table-state"><span class="spinner"></span>Loading assigned Executives…</div>
      <div v-else-if="!executives.length" class="table-state">
        <span class="empty-state-icon">⌕</span><strong>No Executives assigned yet</strong>
        <span>Your Client Admin can allocate Executives to you from the Executives management page.</span>
      </div>
      <template v-else>
        <div class="assigned-executive-summary">
          <div class="person-cell"><span class="person-avatar">{{ selectedExecutive?.name.slice(0, 1).toUpperCase() }}</span><strong>{{ selectedExecutive?.name }}</strong></div>
          <span>{{ selectedExecutive?.email }}</span>
          <span class="status-pill" :class="Number(selectedExecutive?.status) === 1 ? 'status-active' : 'status-inactive'"><span></span>{{ Number(selectedExecutive?.status) === 1 ? 'Active' : 'Inactive' }}</span>
        </div>

        <section class="task-template-panel">
          <div class="panel-heading">
            <div><h2>Tasks from Client Admin</h2><p>Select multiple tasks to assign them to {{ selectedExecutive?.name }}.</p></div>
            <span class="role-pill">{{ availableTasks.length }} available</span>
          </div>
          <div v-if="loading && !availableTasks.length" class="table-state"><span class="spinner"></span>Loading your tasks…</div>
          <div v-else-if="!availableTasks.length" class="table-state"><strong>No tasks waiting for you</strong><span>Your Client Admin’s task assignments will appear here.</span></div>
          <template v-else>
            <div class="template-toolbar">
              <span class="muted-copy">{{ selectedTaskIds.length }} task{{ selectedTaskIds.length === 1 ? '' : 's' }} selected</span>
              <button class="button button-primary" :disabled="saving || !selectedTaskIds.length" @click="assignSelectedTasks">
                {{ saving ? 'Assigning…' : `Assign selected to ${selectedExecutive?.name}` }}
              </button>
            </div>
            <div class="task-template-list">
              <label v-for="task in availableTasks" :key="task.id" class="task-template-option" :class="{ 'task-template-selected': selectedTaskIds.includes(String(task.id)) }">
                <input v-model="selectedTaskIds" type="checkbox" :value="String(task.id)">
                <span class="task-template-copy"><strong>{{ task.title }}</strong><small>Assigned by {{ task.client_admin?.name || 'Client Admin' }}</small></span>
                <span class="task-template-status">{{ task.status.replace('_', ' ') }}</span>
              </label>
            </div>
            <p class="muted-copy">Only tasks assigned to your Manager account are listed. Assigning them makes them visible only to the selected Executive.</p>
          </template>
        </section>

        <div class="panel-heading task-panel-heading">
          <div><h2>Managed Tasks</h2><p>Tasks assigned to {{ selectedExecutive?.name }}</p></div>
          <button class="button button-secondary button-small" @click="openCreate">＋ Create Custom Task</button>
        </div>

        <div v-if="loading" class="table-state"><span class="spinner"></span>Loading tasks…</div>
        <div v-else-if="tasks.length === 0" class="table-state"><strong>No tasks yet</strong><span>Assign this Executive their first task.</span></div>
        <div v-else class="table-scroll">
          <table class="data-table">
            <thead><tr><th>Task</th><th>Status</th><th>Due date</th><th>Actions</th></tr></thead>
            <tbody>
              <tr v-for="task in tasks" :key="task.id">
                <td><strong>{{ task.title }}</strong><small v-if="task.description" class="table-subtext">{{ task.description }}</small></td>
                <td><span class="role-pill">{{ task.status.replace('_', ' ') }}</span></td>
                <td>{{ task.due_date ? new Date(`${task.due_date.slice(0, 10)}T00:00:00`).toLocaleDateString() : '—' }}</td>
                <td><div class="row-actions"><button class="button button-secondary button-small" @click="openEdit(task)">Edit</button><button class="button button-danger button-small" @click="removeTask(task)">Delete</button></div></td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </section>

    <div v-if="modalOpen" class="modal-backdrop" @click.self="modalOpen = false">
      <section class="crud-modal" role="dialog" aria-modal="true" :aria-label="editingTaskId ? 'Edit task' : 'Assign task'">
        <header class="crud-modal-header">
          <div><p class="eyebrow">TASK FOR {{ selectedExecutive?.name?.toUpperCase() }}</p><h2>{{ editingTaskId ? 'Edit Task' : 'Assign Task' }}</h2></div>
          <button class="close-button" aria-label="Close dialog" @click="modalOpen = false">×</button>
        </header>
        <form class="crud-form" @submit.prevent="saveTask">
          <label class="field"><span>Title</span><input v-model="form.title" required maxlength="150"><small v-if="fieldErrors.title" class="field-error">{{ fieldErrors.title }}</small></label>
          <label class="field"><span>Description</span><textarea v-model="form.description" rows="4" maxlength="10000"></textarea><small v-if="fieldErrors.description" class="field-error">{{ fieldErrors.description }}</small></label>
          <div class="crud-form-grid">
            <label class="field"><span>Status</span><select v-model="form.status" required><option value="pending">Pending</option><option value="in_progress">In progress</option><option value="completed">Completed</option></select><small v-if="fieldErrors.status" class="field-error">{{ fieldErrors.status }}</small></label>
            <label class="field"><span>Due date</span><input v-model="form.due_date" type="date"><small v-if="fieldErrors.due_date" class="field-error">{{ fieldErrors.due_date }}</small></label>
          </div>
          <div v-if="errorMessage" class="notice notice-error" role="alert">{{ errorMessage }}</div>
          <footer class="crud-actions"><button class="button button-secondary" type="button" :disabled="saving" @click="modalOpen = false">Cancel</button><button class="button button-primary" type="submit" :disabled="saving">{{ saving ? 'Saving…' : editingTaskId ? 'Save changes' : 'Assign task' }}</button></footer>
        </form>
      </section>
    </div>
  </section>
</template>
