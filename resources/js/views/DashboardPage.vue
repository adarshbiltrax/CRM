<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { getClients } from '../api/clients'
import { getOrganizations } from '../api/organizations'
import { getClientAdminStaff, getStaff } from '../api/staff'
import { getManagerExecutives, getMyTasks } from '../api/tasks'
import { auth, roleFor } from '../auth/store'

const clientsCount = ref(null)
const organizations = ref([])
const clients = ref([])
const managers = ref([])
const executives = ref([])
const managerExecutives = ref([])
const myTasks = ref([])
const loading = ref(false)
const errorMessage = ref('')

onMounted(async () => {
  if (auth.user?.role_id === 2) {
    loading.value = true
    const results = await Promise.allSettled([
      getClientAdminStaff(3),
      getClientAdminStaff(4),
    ])
    if (results[0].status === 'fulfilled') {
      managers.value = results[0].value
    } else {
      errorMessage.value = results[0].reason.response?.data?.message || 'Manager data could not be loaded.'
    }
    if (results[1].status === 'fulfilled') {
      executives.value = results[1].value
    } else {
      errorMessage.value = results[1].reason.response?.data?.message || 'Executive data could not be loaded.'
    }
    loading.value = false
    return
  }
  if (auth.user?.role_id === 3) {
    loading.value = true
    try {
      managerExecutives.value = await getManagerExecutives()
    } catch (error) {
      errorMessage.value = error.response?.data?.message || 'Assigned Executive data could not be loaded.'
    } finally {
      loading.value = false
    }
    return
  }
  if (auth.user?.role_id === 4) {
    loading.value = true
    try {
      myTasks.value = await getMyTasks()
    } catch (error) {
      errorMessage.value = error.response?.data?.message || 'Your assigned tasks could not be loaded.'
    } finally {
      loading.value = false
    }
    return
  }
  if (auth.user?.role_id !== 1) return

  loading.value = true
  const results = await Promise.allSettled([
    getClients(),
    getOrganizations(),
    getStaff(3),
    getStaff(4),
  ])
  if (results[0].status === 'fulfilled') {
    clients.value = results[0].value
    clientsCount.value = clients.value.length
  } else {
    errorMessage.value = results[0].reason.response?.data?.message || 'Client Admin data could not be loaded.'
  }
  if (results[1].status === 'fulfilled') {
    organizations.value = results[1].value
  } else {
    errorMessage.value = results[1].reason.response?.data?.message || 'Organization data could not be loaded.'
  }
  if (results[2].status === 'fulfilled') {
    managers.value = results[2].value
  } else {
    errorMessage.value = results[2].reason.response?.data?.message || 'Manager data could not be loaded.'
  }
  if (results[3].status === 'fulfilled') {
    executives.value = results[3].value
  } else {
    errorMessage.value = results[3].reason.response?.data?.message || 'Executive data could not be loaded.'
  }
  loading.value = false
})

const activeClientAdmins = () => clients.value.filter((client) => Number(client.status) === 1).length
</script>

<template>
  <section>
    <div class="page-heading">
      <div>
        <p class="eyebrow">OVERVIEW</p>
        <h1>Welcome, {{ auth.user.name }}</h1>
        <p class="page-subtitle">{{ roleFor(auth.user.role_id) }} workspace</p>
      </div>
      <div v-if="auth.user.role_id === 1" class="dashboard-actions">
        <RouterLink class="button button-secondary" to="/super-admin/organizations?create=1">＋ Add Organization</RouterLink>
        <RouterLink class="button button-primary" to="/super-admin/client-admins?create=1">＋ Add Client Admin</RouterLink>
      </div>
      <RouterLink v-else-if="auth.user.role_id === 3" class="button button-primary" to="/manager/executives">View My Executives</RouterLink>
    </div>

    <template v-if="auth.user.role_id === 1">
      <div class="stat-grid stat-grid-four">
        <article class="stat-card">
          <div class="stat-topline"><span class="stat-icon stat-icon-blue">OR</span><span class="stat-label">Organizations</span></div>
          <strong class="stat-value">{{ loading ? '…' : organizations.length }}</strong>
          <span class="stat-caption">Records from organizations API</span>
        </article>
        <article class="stat-card">
          <div class="stat-topline"><span class="stat-icon stat-icon-blue">CA</span><span class="stat-label">Client Admins</span></div>
          <strong class="stat-value">{{ loading ? '…' : clientsCount ?? '—' }}</strong>
          <span class="stat-caption">Assigned across all organizations</span>
        </article>
        <article class="stat-card">
          <div class="stat-topline"><span class="stat-icon stat-icon-green">ON</span><span class="stat-label">Active Client Admins</span></div>
          <strong class="stat-value">{{ loading ? '…' : activeClientAdmins() }}</strong>
          <span class="stat-caption">Based on current account status</span>
        </article>
        <article class="stat-card">
          <div class="stat-topline"><span class="stat-icon stat-icon-violet">ORG</span><span class="stat-label">Organizations with admins</span></div>
          <strong class="stat-value">{{ loading ? '…' : organizations.filter((organization) => organization.client_admins_count > 0).length }}</strong>
          <span class="stat-caption">Organizations with assigned Client Admins</span>
        </article>
        <article class="stat-card">
          <div class="stat-topline"><span class="stat-icon stat-icon-blue">M</span><span class="stat-label">Managers</span></div>
          <strong class="stat-value">{{ loading ? '…' : managers.length }}</strong>
          <span class="stat-caption">Across all organizations</span>
        </article>
        <article class="stat-card">
          <div class="stat-topline"><span class="stat-icon stat-icon-violet">E</span><span class="stat-label">Executives</span></div>
          <strong class="stat-value">{{ loading ? '…' : executives.length }}</strong>
          <span class="stat-caption">Across all organizations</span>
        </article>
      </div>
      <div v-if="errorMessage" class="notice notice-error">{{ errorMessage }}</div>
      <section class="panel">
        <div class="panel-heading">
          <div><h2>Recent organizations</h2><p>{{ organizations.length }} organizations in the system</p></div>
          <RouterLink class="button button-secondary button-small" to="/super-admin/organizations">Manage organizations</RouterLink>
        </div>
        <div v-if="loading" class="table-state"><span class="spinner"></span>Loading dashboard…</div>
        <div v-else-if="organizations.length === 0" class="table-state">
          <span class="empty-state-icon">＋</span><strong>No organizations yet</strong><span>Create an organization to get started.</span>
        </div>
        <div v-else class="table-scroll">
          <table class="data-table">
            <thead><tr><th>Organization</th><th>Location</th><th>Client Admins</th><th>Status</th></tr></thead>
            <tbody>
              <tr v-for="organization in organizations.slice(0, 5)" :key="organization.id">
                <td><div class="person-cell"><span class="person-avatar">{{ organization.name.slice(0, 1).toUpperCase() }}</span><strong>{{ organization.name }}</strong></div></td>
                <td>{{ [organization.city, organization.state].filter(Boolean).join(', ') || '—' }}</td>
                <td><span class="role-pill">{{ organization.client_admins_count ?? 0 }} admins</span></td>
                <td><span class="status-pill" :class="Number(organization.status) === 1 ? 'status-active' : 'status-inactive'"><span></span>{{ Number(organization.status) === 1 ? 'Active' : 'Inactive' }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="panel dashboard-clients-panel">
        <div class="panel-heading">
          <div><h2>Recent Client Admins</h2><p>{{ clients.length }} Client Admin accounts across all organizations</p></div>
          <RouterLink class="button button-secondary button-small" to="/super-admin/client-admins">Manage Client Admins</RouterLink>
        </div>
        <div v-if="!loading && clients.length === 0" class="table-state"><strong>No Client Admin accounts yet</strong><span>Create a Client Admin and assign an organization.</span></div>
        <div v-else-if="!loading" class="table-scroll">
          <table class="data-table">
            <thead><tr><th>Client Admin</th><th>Email</th><th>Organization</th><th>Status</th></tr></thead>
            <tbody>
              <tr v-for="client in clients.slice(0, 5)" :key="client.id">
                <td><div class="person-cell"><span class="person-avatar">{{ client.name.slice(0, 1).toUpperCase() }}</span><strong>{{ client.name }}</strong></div></td>
                <td>{{ client.email }}</td>
                <td>{{ client.orgnization?.name || 'Organization unavailable' }}</td>
                <td><span class="status-pill" :class="Number(client.status) === 1 ? 'status-active' : 'status-inactive'"><span></span>{{ Number(client.status) === 1 ? 'Active' : 'Inactive' }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="panel dashboard-clients-panel">
        <div class="panel-heading">
          <div><h2>Managers across organizations</h2><p>{{ managers.length }} manager accounts</p></div>
          <RouterLink class="button button-secondary button-small" to="/super-admin/managers">Manage Managers</RouterLink>
        </div>
        <div v-if="!loading && managers.length === 0" class="table-state"><strong>No Managers yet</strong><span>Create a Manager from the management page.</span></div>
        <div v-else-if="!loading" class="table-scroll">
          <table class="data-table">
            <thead><tr><th>Manager</th><th>Email</th><th>Organization</th><th>Client Admins</th><th>Status</th></tr></thead>
            <tbody>
              <tr v-for="manager in managers.slice(0, 5)" :key="manager.id">
                <td><div class="person-cell"><span class="person-avatar">{{ manager.name.slice(0, 1).toUpperCase() }}</span><strong>{{ manager.name }}</strong></div></td>
                <td>{{ manager.email }}</td><td>{{ manager.orgnization?.name || `Organization #${manager.orgnization_id}` }}</td>
                <td>{{ manager.orgnization?.client_admins?.map((clientAdmin) => clientAdmin.name).join(', ') || '—' }}</td>
                <td><span class="status-pill" :class="Number(manager.status) === 1 ? 'status-active' : 'status-inactive'"><span></span>{{ Number(manager.status) === 1 ? 'Active' : 'Inactive' }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="panel dashboard-clients-panel">
        <div class="panel-heading">
          <div><h2>Executives across organizations</h2><p>{{ executives.length }} executive accounts</p></div>
          <RouterLink class="button button-secondary button-small" to="/super-admin/executives">Manage Executives</RouterLink>
        </div>
        <div v-if="!loading && executives.length === 0" class="table-state"><strong>No Executives yet</strong><span>Create an Executive from the management page.</span></div>
        <div v-else-if="!loading" class="table-scroll">
          <table class="data-table">
            <thead><tr><th>Executive</th><th>Email</th><th>Organization</th><th>Client Admins</th><th>Status</th></tr></thead>
            <tbody>
              <tr v-for="executive in executives.slice(0, 5)" :key="executive.id">
                <td><div class="person-cell"><span class="person-avatar">{{ executive.name.slice(0, 1).toUpperCase() }}</span><strong>{{ executive.name }}</strong></div></td>
                <td>{{ executive.email }}</td><td>{{ executive.orgnization?.name || `Organization #${executive.orgnization_id}` }}</td>
                <td>{{ executive.orgnization?.client_admins?.map((clientAdmin) => clientAdmin.name).join(', ') || '—' }}</td>
                <td><span class="status-pill" :class="Number(executive.status) === 1 ? 'status-active' : 'status-inactive'"><span></span>{{ Number(executive.status) === 1 ? 'Active' : 'Inactive' }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <template v-else-if="auth.user.role_id === 2">
      <div class="client-overview">
        <article class="client-org-card">
          <div class="client-org-icon">ORG</div>
          <div>
            <span class="client-card-label">YOUR ORGANIZATION</span>
            <strong>{{ auth.user.orgnization_id ? `Organization #${auth.user.orgnization_id}` : 'Not assigned' }}</strong>
            <small>{{ auth.user.orgnization_id ? 'Organization assignment from your account' : 'No organization is assigned to this account yet' }}</small>
          </div>
        </article>
        <article class="client-scope-card">
          <span class="stat-icon stat-icon-blue">CA</span>
          <div><span class="client-card-label">SIGNED-IN ROLE</span><strong>Client Admin</strong><small>Access is limited to your assigned workspace</small></div>
        </article>
      </div>
      <div class="stat-grid stat-grid-four">
        <article class="stat-card">
          <div class="stat-topline"><span class="stat-icon stat-icon-blue">M</span><span class="stat-label">Managers</span></div>
          <strong class="stat-value">{{ loading ? '…' : managers.length }}</strong>
          <span class="stat-caption">In your organization</span>
        </article>
        <article class="stat-card">
          <div class="stat-topline"><span class="stat-icon stat-icon-violet">E</span><span class="stat-label">Executives</span></div>
          <strong class="stat-value">{{ loading ? '…' : executives.length }}</strong>
          <span class="stat-caption">In your organization</span>
        </article>
      </div>
      <div v-if="errorMessage" class="notice notice-error" role="alert">{{ errorMessage }}</div>

      <section class="panel client-workspace-panel">
        <div class="panel-heading">
          <div><h2>Your workspace</h2><p>Quick access to your team and organization profile.</p></div>
        </div>
        <div class="client-shortcuts">
          <RouterLink class="client-shortcut" to="/client-admin/managers">
            <span class="shortcut-icon">M</span>
            <span><strong>Managers</strong><small>Open manager workspace</small></span>
            <span class="shortcut-arrow">→</span>
          </RouterLink>
          <RouterLink class="client-shortcut" to="/client-admin/executives">
            <span class="shortcut-icon shortcut-icon-violet">E</span>
            <span><strong>Executives</strong><small>Open executive workspace</small></span>
            <span class="shortcut-arrow">→</span>
          </RouterLink>
          <RouterLink class="client-shortcut" to="/client-admin/profile">
            <span class="shortcut-icon shortcut-icon-green">P</span>
            <span><strong>My profile</strong><small>View your account details</small></span>
            <span class="shortcut-arrow">→</span>
          </RouterLink>
          <RouterLink class="client-shortcut" to="/client-admin/tasks">
            <span class="shortcut-icon shortcut-icon-violet">T</span>
            <span><strong>Assign tasks</strong><small>Select tasks for your Managers</small></span>
            <span class="shortcut-arrow">→</span>
          </RouterLink>
        </div>
      </section>

      <section class="panel dashboard-clients-panel">
        <div class="panel-heading">
          <div><h2>Managers in your organization</h2><p>{{ managers.length }} Manager account{{ managers.length === 1 ? '' : 's' }}</p></div>
          <RouterLink class="button button-secondary button-small" to="/client-admin/managers">Manage Managers</RouterLink>
        </div>
        <div v-if="loading" class="table-state"><span class="spinner"></span>Loading Managers…</div>
        <div v-else-if="managers.length === 0" class="table-state"><strong>No Managers yet</strong><span>Managers you add to your organization will appear here.</span></div>
        <div v-else class="table-scroll">
          <table class="data-table">
            <thead><tr><th>Manager</th><th>Email</th><th>Status</th></tr></thead>
            <tbody>
              <tr v-for="manager in managers.slice(0, 5)" :key="manager.id">
                <td><div class="person-cell"><span class="person-avatar">{{ manager.name.slice(0, 1).toUpperCase() }}</span><strong>{{ manager.name }}</strong></div></td>
                <td>{{ manager.email }}</td>
                <td><span class="status-pill" :class="Number(manager.status) === 1 ? 'status-active' : 'status-inactive'"><span></span>{{ Number(manager.status) === 1 ? 'Active' : 'Inactive' }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="panel dashboard-clients-panel">
        <div class="panel-heading">
          <div><h2>Executives in your organization</h2><p>{{ executives.length }} Executive account{{ executives.length === 1 ? '' : 's' }}</p></div>
          <RouterLink class="button button-secondary button-small" to="/client-admin/executives">Manage Executives</RouterLink>
        </div>
        <div v-if="loading" class="table-state"><span class="spinner"></span>Loading Executives…</div>
        <div v-else-if="executives.length === 0" class="table-state"><strong>No Executives yet</strong><span>Executives you add to your organization will appear here.</span></div>
        <div v-else class="table-scroll">
          <table class="data-table">
            <thead><tr><th>Executive</th><th>Email</th><th>Manager</th><th>Status</th></tr></thead>
            <tbody>
              <tr v-for="executive in executives.slice(0, 5)" :key="executive.id">
                <td><div class="person-cell"><span class="person-avatar">{{ executive.name.slice(0, 1).toUpperCase() }}</span><strong>{{ executive.name }}</strong></div></td>
                <td>{{ executive.email }}</td>
                <td>{{ executive.manager?.name || 'Unassigned' }}</td>
                <td><span class="status-pill" :class="Number(executive.status) === 1 ? 'status-active' : 'status-inactive'"><span></span>{{ Number(executive.status) === 1 ? 'Active' : 'Inactive' }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <template v-else-if="auth.user.role_id === 3">
      <div class="stat-grid stat-grid-four">
        <article class="stat-card"><div class="stat-topline"><span class="stat-icon stat-icon-blue">E</span><span class="stat-label">Assigned Executives</span></div><strong class="stat-value">{{ loading ? '…' : managerExecutives.length }}</strong><span class="stat-caption">Visible only to you and your Client Admin</span></article>
      </div>
      <div v-if="errorMessage" class="notice notice-error" role="alert">{{ errorMessage }}</div>
      <section class="panel dashboard-clients-panel">
        <div class="panel-heading"><div><h2>My Executives</h2><p>Executives allocated to your manager account</p></div><RouterLink class="button button-secondary button-small" to="/manager/executives">Manage Executive Tasks</RouterLink></div>
        <div v-if="loading" class="table-state"><span class="spinner"></span>Loading assigned Executives…</div>
        <div v-else-if="managerExecutives.length === 0" class="table-state"><strong>No Executives assigned</strong><span>Your Client Admin can allocate Executives to you.</span></div>
        <div v-else class="table-scroll"><table class="data-table"><thead><tr><th>Executive</th><th>Email</th><th>Status</th></tr></thead><tbody><tr v-for="executive in managerExecutives.slice(0, 5)" :key="executive.id"><td><div class="person-cell"><span class="person-avatar">{{ executive.name.slice(0, 1).toUpperCase() }}</span><strong>{{ executive.name }}</strong></div></td><td>{{ executive.email }}</td><td><span class="status-pill" :class="Number(executive.status) === 1 ? 'status-active' : 'status-inactive'"><span></span>{{ Number(executive.status) === 1 ? 'Active' : 'Inactive' }}</span></td></tr></tbody></table></div>
      </section>
    </template>

    <template v-else-if="auth.user.role_id === 4">
      <div v-if="errorMessage" class="notice notice-error" role="alert">{{ errorMessage }}</div>
      <section class="panel dashboard-clients-panel">
        <div class="panel-heading"><div><h2>My Tasks</h2><p>{{ myTasks.length }} assigned task{{ myTasks.length === 1 ? '' : 's' }}</p></div></div>
        <div v-if="loading" class="table-state"><span class="spinner"></span>Loading your tasks…</div>
        <div v-else-if="myTasks.length === 0" class="table-state"><strong>No tasks assigned</strong><span>Tasks assigned by your Manager will appear here.</span></div>
        <div v-else class="table-scroll"><table class="data-table"><thead><tr><th>Task</th><th>Assigned by</th><th>Status</th><th>Due date</th></tr></thead><tbody><tr v-for="task in myTasks" :key="task.id"><td><strong>{{ task.title }}</strong><small v-if="task.description" class="table-subtext">{{ task.description }}</small></td><td>{{ task.manager?.name || 'Manager' }}</td><td><span class="role-pill">{{ task.status.replace('_', ' ') }}</span></td><td>{{ task.due_date ? new Date(`${task.due_date.slice(0, 10)}T00:00:00`).toLocaleDateString() : '—' }}</td></tr></tbody></table></div>
      </section>
    </template>

    <section v-else class="panel role-empty-state">
      <span class="empty-state-icon">↗</span>
      <h2>Your {{ roleFor(auth.user.role_id) }} workspace</h2>
      <p>The current API does not provide organization-scoped dashboard data for this role. No other organization's data is requested or shown.</p>
    </section>

    <div class="dashboard-footer">Signed in as <strong>{{ auth.user.email }}</strong></div>
  </section>
</template>
