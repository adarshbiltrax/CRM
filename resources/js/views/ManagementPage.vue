<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  createClient,
  deleteClient,
  getClients,
  getTrashedClients,
  restoreClient,
  updateClient,
} from '../api/clients'
import {
  createOrganization,
  deleteOrganization,
  getOrganizations,
  getTrashedOrganizations,
  restoreOrganization,
  updateOrganization,
} from '../api/organizations'
import {
  createClientAdminStaff,
  createStaff,
  deleteClientAdminStaff,
  deleteStaff,
  getClientAdminStaff,
  getStaff,
  getTrashedClientAdminStaff,
  getTrashedStaff,
  promoteClientAdminExecutive,
  promoteExecutive,
  restoreClientAdminStaff,
  restoreStaff,
  updateClientAdminStaff,
  updateStaff,
} from '../api/staff'
import { auth } from '../auth/store'

const route = useRoute()
const router = useRouter()
const organizations = ref([])
const clients = ref([])
const staff = ref([])
const managers = ref([])
const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const fieldErrors = ref({})
const search = ref('')
const status = ref('all')
const page = ref(1)
const pageSize = 8
const modalOpen = ref(false)
const trashView = ref(false)
const editingId = ref(null)
const form = reactive(emptyForm())

function emptyForm() {
  return {
    name: '',
    email: '',
    phone: '',
    city: '',
    state: '',
    country: '',
    password: '',
    role_id: '',
    orgnization_id: '',
    manager_id: '',
    status: '1',
  }
}

const section = computed(() => route.meta.section)
const isStaffSection = computed(() => ['managers', 'executives'].includes(section.value))
const isClientAdminStaff = computed(() => auth.user?.role_id === 2 && isStaffSection.value)
const staffRoleId = computed(() => section.value === 'managers' ? 3 : 4)
const staffRoleName = computed(() => section.value === 'managers' ? 'Manager' : 'Executive')
const hasApi = computed(() => (
  (auth.user?.role_id === 1 && ['organizations', 'client-admins', 'managers', 'executives'].includes(section.value))
  || isClientAdminStaff.value
))
const title = computed(() => route.meta.title)
const description = computed(() => ({
  organizations: 'Create and manage organizations and the Client Admin accounts assigned to them.',
  'client-admins': 'Create and manage Client Admin accounts and their organization assignments.',
  managers: auth.user?.role_id === 2
    ? 'Manage Managers in your organization, including trashed accounts.'
    : 'Manage Managers across all organizations, including trashed accounts.',
  executives: auth.user?.role_id === 2
    ? 'Manage Executives in your organization, including trashed accounts.'
    : 'Manage Executives across all organizations, including trashed accounts.',
}[section.value] || 'Manage accounts.'))
const rows = computed(() => {
  const source = isStaffSection.value
    ? staff.value
    : section.value === 'organizations' ? organizations.value : clients.value
  const query = search.value.trim().toLowerCase()

  return source.filter((item) => {
    const searchable = section.value === 'organizations'
      ? [item.name, item.email, item.city, item.state, item.country]
      : [item.name, item.email, item.orgnization?.name]
    const matchesSearch = !query || searchable.some((value) => value?.toLowerCase().includes(query))
    const matchesStatus = status.value === 'all' || String(item.status) === status.value
    return matchesSearch && matchesStatus
  })
})
const visibleRows = computed(() => rows.value.slice((page.value - 1) * pageSize, page.value * pageSize))
const pageCount = computed(() => Math.max(1, Math.ceil(rows.value.length / pageSize)))
const modalTitle = computed(() => {
  const subject = section.value === 'organizations'
    ? 'Organization'
    : isStaffSection.value ? staffRoleName.value : 'Client Admin'
  return `${editingId.value ? 'Edit' : 'Add'} ${subject}`
})

watch([search, status], () => { page.value = 1 })
watch(trashView, () => {
  page.value = 1
  loadData()
})
watch(() => route.query.create, (requested) => {
  if (requested === '1' && hasApi.value) {
    openCreate()
    router.replace({ path: route.path })
  }
}, { immediate: true })
watch(section, () => {
  organizations.value = []
  clients.value = []
  staff.value = []
  trashView.value = false
  search.value = ''
  status.value = 'all'
  page.value = 1
  successMessage.value = ''
  loadData()
})

async function loadData() {
  if (!hasApi.value) return

  loading.value = true
  errorMessage.value = ''
  try {
    if (section.value === 'organizations') {
      organizations.value = trashView.value ? await getTrashedOrganizations() : await getOrganizations()
    } else if (section.value === 'client-admins') {
      const [clientRows, organizationRows] = await Promise.all([
        trashView.value ? getTrashedClients() : getClients(),
        getOrganizations(),
      ])
      clients.value = clientRows
      organizations.value = organizationRows
    } else if (isStaffSection.value) {
      const staffRows = isClientAdminStaff.value
        ? await (trashView.value
          ? getTrashedClientAdminStaff(staffRoleId.value)
          : getClientAdminStaff(staffRoleId.value))
        : await (trashView.value ? getTrashedStaff(staffRoleId.value) : getStaff(staffRoleId.value))
      staff.value = staffRows
      if (isClientAdminStaff.value && section.value === 'executives' && !trashView.value) {
        managers.value = (await getClientAdminStaff(3)).filter((manager) => Number(manager.status) === 1)
      } else if (!isClientAdminStaff.value) {
        organizations.value = await getOrganizations()
      }
    }
  } catch (error) {
    errorMessage.value = error.response?.data?.message || `Unable to load ${title.value.toLowerCase()}.`
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingId.value = null
  Object.assign(form, emptyForm())
  fieldErrors.value = {}
  errorMessage.value = ''
  modalOpen.value = true
}

function openEdit(item) {
  editingId.value = item.id
  Object.assign(form, emptyForm(), {
    name: item.name || '',
    email: item.email || '',
    phone: item.phone || '',
    city: item.city || '',
    state: item.state || '',
    country: item.country || '',
    role_id: String(item.role_id || staffRoleId.value),
    orgnization_id: item.orgnization_id ? String(item.orgnization_id) : '',
    manager_id: item.manager_id ? String(item.manager_id) : '',
    status: String(item.status),
  })
  fieldErrors.value = {}
  errorMessage.value = ''
  modalOpen.value = true
}

function validationErrors(error) {
  return Object.fromEntries(
    Object.entries(error.response?.data?.errors || {}).map(([key, messages]) => [key, messages[0]]),
  )
}

async function save() {
  saving.value = true
  errorMessage.value = ''
  fieldErrors.value = {}

  const payload = section.value === 'organizations'
    ? {
        name: form.name.trim(),
        email: form.email.trim() || null,
        phone: form.phone.trim(),
        city: form.city.trim(),
        state: form.state.trim(),
        country: form.country.trim(),
        status: Number(form.status),
      }
    : isClientAdminStaff.value
      ? {
          name: form.name.trim(),
          email: form.email.trim(),
          password: form.password || undefined,
          ...(section.value === 'executives' ? { manager_id: form.manager_id ? Number(form.manager_id) : null } : {}),
          status: Number(form.status),
        }
      : {
        name: form.name.trim(),
        email: form.email.trim(),
        password: form.password || undefined,
        orgnization_id: Number(form.orgnization_id),
        status: Number(form.status),
      }

  try {
    if (section.value === 'organizations') {
      if (editingId.value) await updateOrganization(editingId.value, payload)
      else await createOrganization(payload)
      successMessage.value = `Organization ${editingId.value ? 'updated' : 'created'} successfully.`
    } else if (section.value === 'client-admins') {
      if (editingId.value) await updateClient(editingId.value, payload)
      else await createClient(payload)
      successMessage.value = `Client Admin ${editingId.value ? 'updated' : 'created'} successfully.`
    } else {
      if (isClientAdminStaff.value) {
        if (editingId.value) await updateClientAdminStaff(staffRoleId.value, editingId.value, payload)
        else await createClientAdminStaff(staffRoleId.value, payload)
      } else if (editingId.value) await updateStaff(staffRoleId.value, editingId.value, payload)
      else await createStaff(staffRoleId.value, payload)
      successMessage.value = `${staffRoleName.value} ${editingId.value ? 'updated' : 'created'} successfully.`
    }

    modalOpen.value = false
    await loadData()
  } catch (error) {
    fieldErrors.value = validationErrors(error)
    errorMessage.value = error.response?.data?.message || 'Unable to save changes.'
  } finally {
    saving.value = false
  }
}

async function remove(item) {
  const subject = section.value === 'organizations'
    ? 'organization'
    : isStaffSection.value ? staffRoleName.value : 'Client Admin'
  if (!window.confirm(`Move ${subject} "${item.name}" to trash?`)) return

  errorMessage.value = ''
  successMessage.value = ''
  try {
    if (section.value === 'organizations') await deleteOrganization(item.id)
    else if (section.value === 'client-admins') await deleteClient(item.id)
    else if (isClientAdminStaff.value) await deleteClientAdminStaff(staffRoleId.value, item.id)
    else await deleteStaff(staffRoleId.value, item.id)
    successMessage.value = `${subject} moved to trash.`
    await loadData()
  } catch (error) {
    errorMessage.value = error.response?.data?.message || `Unable to delete ${subject.toLowerCase()}.`
  }
}

async function restore(item) {
  const subject = section.value === 'organizations'
    ? 'Organization'
    : isStaffSection.value ? staffRoleName.value : 'Client Admin'
  errorMessage.value = ''
  successMessage.value = ''
  try {
    if (section.value === 'organizations') await restoreOrganization(item.id)
    else if (section.value === 'client-admins') await restoreClient(item.id)
    else if (isClientAdminStaff.value) await restoreClientAdminStaff(staffRoleId.value, item.id)
    else await restoreStaff(staffRoleId.value, item.id)
    successMessage.value = `${subject} restored successfully.`
    await loadData()
  } catch (error) {
    errorMessage.value = error.response?.data?.message || `Unable to restore ${subject.toLowerCase()}.`
  }
}

async function makeManager(item) {
  if (!window.confirm(`Promote ${item.name} from Executive to Manager? Their organization and account status will remain unchanged.`)) return

  errorMessage.value = ''
  successMessage.value = ''
  try {
    if (isClientAdminStaff.value) await promoteClientAdminExecutive(item.id)
    else await promoteExecutive(item.id)
    successMessage.value = `${item.name} is now a Manager.`
    await loadData()
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Unable to promote this Executive.'
  }
}

onMounted(loadData)
</script>

<template>
  <section>
    <div class="page-heading">
      <div>
        <p class="eyebrow">{{ section === 'organizations' ? 'SYSTEM DIRECTORY' : 'ACCOUNT DIRECTORY' }}</p>
        <h1>{{ title }}</h1>
        <p class="page-subtitle">{{ description }}</p>
      </div>
      <div v-if="hasApi" class="dashboard-actions">
        <button class="button button-secondary" @click="trashView = !trashView">
          {{ trashView ? '← Active records' : 'View Trash' }}
        </button>
        <button v-if="!trashView" class="button button-primary" @click="openCreate">
          <span aria-hidden="true">＋</span> Add {{ section === 'organizations' ? 'Organization' : isStaffSection ? staffRoleName : 'Client Admin' }}
        </button>
      </div>
    </div>

    <div v-if="successMessage" class="notice notice-success" role="status">{{ successMessage }}</div>
    <div v-if="errorMessage" class="notice notice-error" role="alert">{{ errorMessage }}</div>
    <div v-if="isStaffSection && hasApi && !isClientAdminStaff" class="notice notice-info">
      Staff is grouped with Client Admins by organization. The current users table has no manager-assignment field, so Executive-to-Manager assignments cannot be shown or edited.
    </div>
    <div v-if="section === 'organizations' && !trashView && hasApi" class="notice notice-info">
      Move all assigned Client Admins, Managers, and Executives to trash before moving an organization to trash.
    </div>

    <template v-if="hasApi">
      <section class="panel">
        <div class="table-toolbar">
          <div>
            <h2>{{ trashView ? 'Trashed ' : '' }}{{ isStaffSection ? `${staffRoleName}s` : section === 'organizations' ? 'Organizations' : 'Client Admin accounts' }}</h2>
            <p>{{ rows.length }} record{{ rows.length === 1 ? '' : 's' }}</p>
          </div>
          <div class="table-filters">
            <label class="search-field">
              <span class="sr-only">Search records</span><span aria-hidden="true">⌕</span>
              <input v-model="search" type="search" :placeholder="section === 'organizations' ? 'Search organizations' : 'Search name, email, organization'">
            </label>
            <select v-model="status" aria-label="Filter by status">
              <option value="all">All statuses</option><option value="1">Active</option><option value="0">Inactive</option>
            </select>
          </div>
        </div>

        <div v-if="loading" class="table-state"><span class="spinner"></span>Loading {{ title.toLowerCase() }}…</div>
        <div v-else-if="visibleRows.length === 0" class="table-state">
          <span class="empty-state-icon">⌕</span><strong>{{ trashView ? 'Trash is empty' : 'No records found' }}</strong><span>{{ trashView ? 'Deleted records will appear here and can be restored.' : 'Add a record or change your search and filters.' }}</span>
        </div>
        <div v-else class="table-scroll">
          <table class="data-table">
            <template v-if="section === 'organizations'">
              <thead><tr><th>Organization</th><th>Contact</th><th>Location</th><th>Client Admins</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
                <tr v-for="organization in visibleRows" :key="organization.id">
                  <td><div class="person-cell"><span class="person-avatar">{{ organization.name.slice(0, 1).toUpperCase() }}</span><strong>{{ organization.name }}</strong></div></td>
                  <td>{{ organization.email || '—' }}<small class="table-subtext">{{ organization.phone }}</small></td>
                  <td>{{ [organization.city, organization.state, organization.country].filter(Boolean).join(', ') }}</td>
                  <td><span class="role-pill">{{ organization.client_admins_count ?? 0 }} admins</span></td>
                  <td><span class="status-pill" :class="Number(organization.status) === 1 ? 'status-active' : 'status-inactive'"><span></span>{{ Number(organization.status) === 1 ? 'Active' : 'Inactive' }}</span></td>
                  <td><div class="row-actions"><template v-if="trashView"><button class="button button-secondary button-small" @click="restore(organization)">Restore</button></template><template v-else><button class="button button-secondary button-small" @click="openEdit(organization)">Edit</button><button class="button button-danger button-small" @click="remove(organization)">Trash</button></template></div></td>
                </tr>
              </tbody>
            </template>
            <template v-else-if="section === 'client-admins'">
              <thead><tr><th>Client Admin</th><th>Email</th><th>Organization</th><th>Status</th><th>{{ trashView ? 'Deleted At' : 'Created' }}</th><th>Actions</th></tr></thead>
              <tbody>
                <tr v-for="client in visibleRows" :key="client.id">
                  <td><div class="person-cell"><span class="person-avatar">{{ client.name.slice(0, 1).toUpperCase() }}</span><strong>{{ client.name }}</strong></div></td>
                  <td>{{ client.email }}</td>
                  <td>{{ client.orgnization?.name || `Organization #${client.orgnization_id}` }}</td>
                  <td><span class="status-pill" :class="Number(client.status) === 1 ? 'status-active' : 'status-inactive'"><span></span>{{ Number(client.status) === 1 ? 'Active' : 'Inactive' }}</span></td>
                  <td>{{ (trashView ? client.deleted_at : client.created_at) ? new Date(trashView ? client.deleted_at : client.created_at).toLocaleDateString() : '—' }}</td>
                  <td><div class="row-actions"><template v-if="trashView"><button class="button button-secondary button-small" @click="restore(client)">Restore</button></template><template v-else><button class="button button-secondary button-small" @click="openEdit(client)">Edit</button><button class="button button-danger button-small" @click="remove(client)">Trash</button></template></div></td>
                </tr>
              </tbody>
            </template>
            <template v-else>
              <thead><tr><th>{{ staffRoleName }}</th><th>Email</th><th v-if="!isClientAdminStaff">Organization</th><th v-if="!isClientAdminStaff">Client Admins</th><th v-if="isClientAdminStaff && section === 'executives'">Manager</th><th>Status</th><th>{{ trashView ? 'Deleted At' : 'Created' }}</th><th>Actions</th></tr></thead>
              <tbody>
                <tr v-for="person in visibleRows" :key="person.id">
                  <td><div class="person-cell"><span class="person-avatar">{{ person.name.slice(0, 1).toUpperCase() }}</span><strong>{{ person.name }}</strong></div></td>
                  <td>{{ person.email }}</td>
                  <td v-if="!isClientAdminStaff">{{ person.orgnization?.name || `Organization #${person.orgnization_id}` }}</td>
                  <td v-if="!isClientAdminStaff">{{ person.orgnization?.client_admins?.map((clientAdmin) => clientAdmin.name).join(', ') || '—' }}</td>
                  <td v-if="isClientAdminStaff && section === 'executives'">{{ person.manager?.name || 'Unassigned' }}</td>
                  <td><span class="status-pill" :class="Number(person.status) === 1 ? 'status-active' : 'status-inactive'"><span></span>{{ Number(person.status) === 1 ? 'Active' : 'Inactive' }}</span></td>
                  <td>{{ (trashView ? person.deleted_at : person.created_at) ? new Date(trashView ? person.deleted_at : person.created_at).toLocaleDateString() : '—' }}</td>
                  <td><div class="row-actions"><template v-if="trashView"><button class="button button-secondary button-small" @click="restore(person)">Restore</button></template><template v-else><button v-if="section === 'executives'" class="button button-secondary button-small" @click="makeManager(person)">Make Manager</button><button class="button button-secondary button-small" @click="openEdit(person)">Edit</button><button class="button button-danger button-small" @click="remove(person)">Trash</button></template></div></td>
                </tr>
              </tbody>
            </template>
          </table>
        </div>

        <footer v-if="!loading && rows.length" class="table-pagination">
          <span>Showing {{ (page - 1) * pageSize + 1 }}–{{ Math.min(page * pageSize, rows.length) }} of {{ rows.length }}</span>
          <div><button class="button button-secondary button-small" :disabled="page <= 1" @click="page--">Previous</button><button class="button button-secondary button-small" :disabled="page >= pageCount" @click="page++">Next</button></div>
        </footer>
      </section>
    </template>

    <section v-else class="panel unavailable-panel">
      <span class="empty-state-icon">⌁</span>
      <div><h2>This section is not available yet</h2><p>The Laravel API does not currently expose a supported {{ title?.toLowerCase() }} list or management endpoint for this account.</p><p class="muted-copy">This page will not request data from other organizations or use placeholder records.</p></div>
    </section>

    <div v-if="modalOpen" class="modal-backdrop" @click.self="modalOpen = false">
      <section class="crud-modal" role="dialog" aria-modal="true" :aria-label="modalTitle">
        <header class="crud-modal-header">
          <div><p class="eyebrow">{{ editingId ? 'UPDATE RECORD' : 'NEW RECORD' }}</p><h2>{{ modalTitle }}</h2></div>
          <button class="close-button" aria-label="Close dialog" @click="modalOpen = false">×</button>
        </header>
        <form class="crud-form" @submit.prevent="save">
          <label class="field"><span>Name</span><input v-model="form.name" required maxlength="255" autocomplete="name"><small v-if="fieldErrors.name" class="field-error">{{ fieldErrors.name }}</small></label>
          <label class="field"><span>Email</span><input v-model="form.email" type="email" :required="section !== 'organizations'" autocomplete="email"><small v-if="fieldErrors.email" class="field-error">{{ fieldErrors.email }}</small></label>

          <template v-if="section === 'organizations'">
            <label class="field"><span>Phone</span><input v-model="form.phone" required maxlength="20" autocomplete="tel"><small v-if="fieldErrors.phone" class="field-error">{{ fieldErrors.phone }}</small></label>
            <div class="crud-form-grid">
              <label class="field"><span>City</span><input v-model="form.city" required maxlength="100"><small v-if="fieldErrors.city" class="field-error">{{ fieldErrors.city }}</small></label>
              <label class="field"><span>State</span><input v-model="form.state" required maxlength="100"><small v-if="fieldErrors.state" class="field-error">{{ fieldErrors.state }}</small></label>
            </div>
            <label class="field"><span>Country</span><input v-model="form.country" required maxlength="100"><small v-if="fieldErrors.country" class="field-error">{{ fieldErrors.country }}</small></label>
          </template>

          <template v-else>
          <label class="field"><span>Password {{ editingId ? '(leave blank to keep current password)' : '' }}</span><input v-model="form.password" type="password" :required="!editingId" minlength="8" autocomplete="new-password"><small v-if="fieldErrors.password" class="field-error">{{ fieldErrors.password }}</small></label>
          <label v-if="!isClientAdminStaff" class="field"><span>Organization</span><select v-model="form.orgnization_id" required><option value="" disabled>Select an organization</option><option v-for="organization in organizations" :key="organization.id" :value="String(organization.id)">{{ organization.name }}</option></select><small v-if="fieldErrors.orgnization_id" class="field-error">{{ fieldErrors.orgnization_id }}</small></label>
          <label v-if="isClientAdminStaff && section === 'executives'" class="field"><span>Assign to Manager</span><select v-model="form.manager_id"><option value="">Unassigned</option><option v-for="manager in managers" :key="manager.id" :value="String(manager.id)">{{ manager.name }}</option></select><small v-if="fieldErrors.manager_id" class="field-error">{{ fieldErrors.manager_id }}</small></label>
          </template>

          <label class="field"><span>Status</span><select v-model="form.status" required><option value="1">Active</option><option value="0">Inactive</option></select><small v-if="fieldErrors.status" class="field-error">{{ fieldErrors.status }}</small></label>
          <div v-if="['client-admins', 'managers', 'executives'].includes(section) && !isClientAdminStaff && organizations.length === 0" class="notice notice-error">Create an organization before creating this account.</div>
          <div v-if="errorMessage" class="notice notice-error" role="alert">{{ errorMessage }}</div>
          <footer class="crud-actions"><button class="button button-secondary" type="button" :disabled="saving" @click="modalOpen = false">Cancel</button><button class="button button-primary" type="submit" :disabled="saving || (['client-admins', 'managers', 'executives'].includes(section) && !isClientAdminStaff && organizations.length === 0)">{{ saving ? 'Saving…' : editingId ? 'Save changes' : 'Create' }}</button></footer>
        </form>
      </section>
    </div>
  </section>
</template>
