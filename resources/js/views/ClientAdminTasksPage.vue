<script setup>
import { computed, onMounted, ref } from 'vue'
import { getClientAdminStaff } from '../api/staff'
import {
  assignTasksToManager,
  getClientAdminAssignedTasks,
  getFreeTaskTemplates,
} from '../api/tasks'

const managers = ref([])
const templates = ref([])
const assignments = ref([])
const selectedManagerId = ref('')
const selectedTemplateIds = ref([])
const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const templateError = ref('')

const assignedTemplateIds = computed(() => new Set(assignments.value.map((task) => Number(task.template_id))))
const selectedTemplates = computed(() => templates.value.filter((task) => selectedTemplateIds.value.includes(String(task.id))))
const availableTemplates = computed(() => templates.value.filter((task) => !assignedTemplateIds.value.has(task.id)))

async function loadData() {
  loading.value = true
  errorMessage.value = ''
  templateError.value = ''

  const results = await Promise.allSettled([
    getClientAdminStaff(3),
    getClientAdminAssignedTasks(),
    getFreeTaskTemplates(),
  ])

  if (results[0].status === 'fulfilled') {
    managers.value = results[0].value.filter((manager) => Number(manager.status) === 1)
    if (!selectedManagerId.value && managers.value.length) {
      selectedManagerId.value = String(managers.value[0].id)
    }
  } else {
    errorMessage.value = results[0].reason.response?.data?.message || 'Unable to load your organization Managers.'
  }

  if (results[1].status === 'fulfilled') {
    assignments.value = results[1].value
  } else {
    errorMessage.value = results[1].reason.response?.data?.message || 'Unable to load task assignments.'
  }

  if (results[2].status === 'fulfilled') {
    templates.value = results[2].value
  } else {
    templateError.value = results[2].reason.message || 'Unable to load the free task library.'
  }

  loading.value = false
}

async function assignSelectedTasks() {
  if (!selectedManagerId.value || selectedTemplates.value.length === 0) return

  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const result = await assignTasksToManager(Number(selectedManagerId.value), selectedTemplates.value.map((task) => ({
      template_id: task.id,
      title: task.title,
    })))
    successMessage.value = result.message
    selectedTemplateIds.value = []
    await loadData()
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Unable to assign selected tasks.'
    if (error.response?.status === 409) await loadData()
  } finally {
    saving.value = false
  }
}

onMounted(loadData)
</script>

<template>
  <section>
    <div class="page-heading">
      <div>
        <p class="eyebrow">ORGANIZATION TASKS</p>
        <h1>Assign Tasks to Managers</h1>
        <p class="page-subtitle">Select one or more free task ideas and assign them to a Manager in your organization.</p>
      </div>
      <!-- <span class="role-pill">DummyJSON · Free</span> -->
    </div>

    <div v-if="successMessage" class="notice notice-success" role="status">{{ successMessage }}</div>
    <div v-if="errorMessage" class="notice notice-error" role="alert">{{ errorMessage }}</div>

    <section class="panel task-template-panel">
      <div class="panel-heading">
        <div><h2>Task List</h2><p>{{ availableTemplates.length }} unassigned of {{ templates.length }} task ideas</p></div>
        <button class="button button-secondary button-small" :disabled="loading" @click="loadData">Refresh list</button>
      </div>

      <div v-if="loading && !templates.length" class="table-state"><span class="spinner"></span>Loading task library…</div>
      <div v-else-if="templateError" class="table-state"><strong>Task library unavailable</strong><span>{{ templateError }}</span><button class="button button-secondary button-small" @click="loadData">Retry</button></div>
      <div v-else-if="!templates.length" class="table-state"><strong>No task ideas available</strong><span>There are no task templates to display right now.</span></div>
      <template v-else>
        <div class="template-toolbar">
          <label class="field template-select-field">
            <span>Assign selected tasks to</span>
            <select v-model="selectedManagerId" :disabled="!managers.length">
              <option value="" disabled>Select a Manager</option>
              <option v-for="manager in managers" :key="manager.id" :value="String(manager.id)">{{ manager.name }}</option>
            </select>
          </label>
          <button class="button button-primary" :disabled="saving || !selectedManagerId || !selectedTemplates.length" @click="assignSelectedTasks">
            {{ saving ? 'Assigning…' : `Assign ${selectedTemplates.length || ''} task${selectedTemplates.length === 1 ? '' : 's'}` }}
          </button>
        </div>

        <div v-if="!managers.length" class="notice notice-info">Add an active Manager to your organization before assigning tasks.</div>
        <div class="task-template-list">
          <label
            v-for="task in templates"
            :key="task.id"
            class="task-template-option"
            :class="{ 'task-template-selected': selectedTemplateIds.includes(String(task.id)), 'task-template-disabled': assignedTemplateIds.has(task.id) }"
          >
            <input
              v-model="selectedTemplateIds"
              type="checkbox"
              :value="String(task.id)"
              :disabled="assignedTemplateIds.has(task.id) || saving || !managers.length"
            >
            <span class="task-template-copy"><strong>{{ task.title }}</strong><small>Free task idea #{{ task.id }}</small></span>
            <span class="task-template-status">{{ assignedTemplateIds.has(task.id) ? `Assigned to ${assignments.find((item) => Number(item.template_id) === task.id)?.manager?.name || 'a Manager'}` : 'Available' }}</span>
          </label>
        </div>
        <p class="muted-copy">Once a task idea is assigned in this organization, it is disabled here so it cannot be assigned again. The selected Manager will see only their assigned tasks.</p>
      </template>
    </section>

    <section class="panel dashboard-clients-panel">
      <div class="panel-heading"><div><h2>Assigned task list</h2><p>{{ assignments.length }} task{{ assignments.length === 1 ? '' : 's' }} allocated in your organization</p></div></div>
      <div v-if="loading && !assignments.length" class="table-state"><span class="spinner"></span>Loading assignments…</div>
      <div v-else-if="!assignments.length" class="table-state"><strong>No tasks assigned yet</strong><span>Tasks assigned to Managers will appear here.</span></div>
      <div v-else class="table-scroll">
        <table class="data-table">
          <thead><tr><th>Task</th><th>Manager</th><th>Assigned By</th><th>Status</th></tr></thead>
          <tbody>
            <tr v-for="task in assignments" :key="task.id">
              <td><strong>{{ task.title }}</strong><small class="table-subtext">Template #{{ task.template_id }}</small></td>
              <td>{{ task.manager?.name || 'Manager unavailable' }}</td>
              <td>{{ task.client_admin?.name || 'Client Admin' }}</td>
              <td><span class="role-pill">{{ task.executive_id ? 'Assigned to Executive' : task.status.replace('_', ' ') }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </section>
</template>
