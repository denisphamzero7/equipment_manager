<script setup>
import { computed, onMounted, onBeforeUnmount, reactive, ref } from 'vue'
import { ability } from '@/core/casl/ability'
import { toast } from '@/core/helpers/toast'
import AppButton from '@/core/components/AppButton.vue'
import AppConfirmDialog from '@/core/components/AppConfirmDialog.vue'
import AppDataTable from '@/core/components/AppDataTable.vue'
import AppModal from '@/core/components/AppModal.vue'
import AppPageHeader from '@/core/components/AppPageHeader.vue'
import AppStatCard from '@/core/components/AppStatCard.vue'
import DefaultLayout from '@/core/layouts/DefaultLayout.vue'
import { useAuthStore } from '../../stores/authStore'
import { accessManagementService } from '../../services/accessManagementService'

const authStore = useAuthStore()
const canCreate = computed(() => ability.can('create', 'Users'))
const canUpdate = computed(() => ability.can('update', 'Users'))
const canDelete = computed(() => ability.can('delete', 'Users'))
const canAssignRoles = computed(() => ability.can('read', 'Roles'))
const items = ref([])
const roles = ref([])
const rolesLoaded = ref(false)
const loading = ref(false)
const saving = ref(false)
const page = ref(1)
const perPage = 15
const total = ref(0)
const search = ref('')
const statusFilter = ref('')
const stats = ref({ total: 0, active: 0, inactive: 0 })
const modalOpen = ref(false)
const deleteOpen = ref(false)
const selectedUser = ref(null)
const pendingDelete = ref(null)
const loadError = ref('')
let searchTimer

const organizationOptions = computed(() =>
  (authStore.availableOrganizations || []).map(org => ({
    id: Number(org.id),
    name: org.name,
  }))
)

const form = reactive({
  name: '',
  email: '',
  user_name: '',
  password: '',
  password_confirmation: '',
  status: 'active',
  assignments: [],
})

const headers = [
  { key: 'user', title: 'Người dùng' },
  { key: 'status', title: 'Trạng thái', width: '120px' },
  { key: 'assignments', title: 'Vai trò & tổ chức' },
  { key: 'actions', title: 'Thao tác', align: 'end', width: '110px' },
]

const statusOptions = [
  { title: 'Tất cả trạng thái', value: '' },
  { title: 'Đang hoạt động', value: 'active' },
  { title: 'Ngưng hoạt động', value: 'inactive' },
  { title: 'Bị khóa', value: 'banned' },
]

function responseItems(response) {
  if (Array.isArray(response)) return response
  if (Array.isArray(response?.data)) return response.data
  return []
}

function userParams(targetPage = page.value) {
  return {
    page: targetPage,
    limit: perPage,
    search: search.value || undefined,
    status: statusFilter.value || undefined,
    sort_by: 'created_at',
    sort_order: 'desc',
  }
}

async function loadUsers(targetPage = page.value, { silent = false, isRetry = false } = {}) {
  if (!silent) {
    loading.value = true
  }
  loadError.value = ''
  try {
    const [listResult, statsResult] = await Promise.allSettled([
      accessManagementService.getUsers(userParams(targetPage)),
      accessManagementService.getUserStats({
        search: search.value || undefined,
        status: statusFilter.value || undefined,
      }),
    ])

    if (listResult.status === 'fulfilled') {
      const listResponse = listResult.value
      items.value = responseItems(listResponse)
      page.value = listResponse?.meta?.current_page || targetPage
      total.value = listResponse?.meta?.total || items.value.length
      loadError.value = ''
    } else {
      console.error('Failed to load users list:', listResult.reason)
      if (!isRetry) {
        await new Promise(r => setTimeout(r, 600))
        return await loadUsers(targetPage, { silent, isRetry: true })
      }
      loadError.value = listResult.reason?.response?.data?.message || 'Không thể tải danh sách người dùng.'
      toast.error(loadError.value)
    }

    if (statsResult.status === 'fulfilled' && statsResult.value) {
      const statsData = statsResult.value
      stats.value = {
        total: Number(statsData.total ?? total.value ?? 0),
        active: Number(statsData.active ?? 0),
        inactive: Number(statsData.inactive ?? 0),
      }
    } else {
      const activeCount = items.value.filter(u => u.status === 'active').length
      stats.value = {
        total: total.value || items.value.length,
        active: activeCount,
        inactive: Math.max(0, (total.value || items.value.length) - activeCount),
      }
    }
  } catch (error) {
    console.error('Failed to load user management data:', error)
    if (!isRetry) {
      await new Promise(r => setTimeout(r, 600))
      return await loadUsers(targetPage, { silent, isRetry: true })
    }
    loadError.value = error.response?.data?.message || 'Không thể tải danh sách người dùng.'
    toast.error(loadError.value)
  } finally {
    if (!silent) loading.value = false
  }
}

async function loadRoles() {
  if (!canAssignRoles.value) {
    roles.value = []
    return
  }
  if (rolesLoaded.value) return
  const response = await accessManagementService.getRoles({ limit: 100 })
  roles.value = responseItems(response)
  rolesLoaded.value = true
}

function resetForm() {
  Object.assign(form, {
    name: '',
    email: '',
    user_name: '',
    password: '',
    password_confirmation: '',
    status: 'active',
    assignments: [],
  })
}

async function openForm(user = null) {
  selectedUser.value = user
  resetForm()
  if (user) {
    Object.assign(form, {
      name: user.name || '',
      email: user.email || '',
      user_name: user.user_name || '',
      status: user.status || 'active',
      assignments: canAssignRoles.value
        ? (user.assignments || []).map(assignment => ({
            role_id: Number(assignment.role_id),
            organization_ids: (assignment.organization_ids || []).map(Number),
          }))
        : [],
    })
  }
  try {
    await loadRoles()
    modalOpen.value = true
  } catch (error) {
    console.error('Failed to load roles for user form:', error)
    toast.error(error.response?.data?.message || 'Không thể tải danh sách vai trò.')
  }
}

function addAssignment() {
  form.assignments.push({ role_id: null, organization_ids: [] })
}

function removeAssignment(index) {
  form.assignments.splice(index, 1)
}

async function saveUser() {
  if (!form.name.trim() || !form.email.trim()) {
    toast.warning('Vui lòng nhập họ tên và email.')
    return
  }
  if (!selectedUser.value && !form.password) {
    toast.warning('Vui lòng đặt mật khẩu ban đầu cho tài khoản.')
    return
  }
  if (form.password && form.password !== form.password_confirmation) {
    toast.warning('Mật khẩu xác nhận chưa khớp.')
    return
  }
  if (canAssignRoles.value && form.assignments.some(row =>
    !row.role_id || !row.organization_ids?.length
  )) {
    toast.warning('Mỗi vai trò cần được gán ít nhất một tổ chức.')
    return
  }

  const payload = {
    name: form.name.trim(),
    email: form.email.trim(),
    user_name: form.user_name.trim() || null,
    status: form.status,
  }
  if (canAssignRoles.value) payload.assignments = form.assignments
  if (form.password) {
    payload.password = form.password
    payload.password_confirmation = form.password_confirmation
  }

  saving.value = true
  try {
    if (selectedUser.value) {
      const res = await accessManagementService.updateUser(selectedUser.value.id, payload)
      const updated = res?.data || res
      const idx = items.value.findIndex(u => Number(u.id) === Number(selectedUser.value.id))
      if (idx !== -1) {
        const oldUser = items.value[idx]
        if (oldUser.status !== payload.status) {
          if (oldUser.status === 'active') stats.value.active = Math.max(0, stats.value.active - 1)
          else stats.value.inactive = Math.max(0, stats.value.inactive - 1)

          if (payload.status === 'active') stats.value.active += 1
          else stats.value.inactive += 1
        }
        items.value.splice(idx, 1, { ...oldUser, ...payload, ...(updated?.id ? updated : {}) })
      }
      modalOpen.value = false
      toast.success('Đã cập nhật tài khoản.')
      await loadUsers(page.value, { silent: true })
    } else {
      const res = await accessManagementService.createUser(payload)
      const created = res?.data || res
      if (created?.id) {
        if (page.value === 1) {
          items.value.unshift(created)
          if (items.value.length > perPage) items.value.pop()
        }
        total.value += 1
        stats.value.total += 1
        if (created.status === 'active') stats.value.active += 1
        else stats.value.inactive += 1
      }
      modalOpen.value = false
      toast.success('Đã tạo tài khoản mới.')
      await loadUsers(1, { silent: true })
    }
  } catch (error) {
    console.error('Failed to save user:', error)
    toast.error(error.response?.data?.message || 'Không thể lưu tài khoản.')
  } finally {
    saving.value = false
  }
}

function confirmDelete(user) {
  pendingDelete.value = user
  deleteOpen.value = true
}

async function deleteUser() {
  if (!pendingDelete.value) return
  const deletedId = pendingDelete.value.id
  const oldUser = items.value.find(u => Number(u.id) === Number(deletedId))
  saving.value = true
  try {
    await accessManagementService.deleteUser(deletedId)
    if (oldUser) {
      items.value = items.value.filter(u => Number(u.id) !== Number(deletedId))
      total.value = Math.max(0, total.value - 1)
      stats.value.total = Math.max(0, stats.value.total - 1)
      if (oldUser.status === 'active') stats.value.active = Math.max(0, stats.value.active - 1)
      else stats.value.inactive = Math.max(0, stats.value.inactive - 1)
    }
    toast.success('Đã xóa tài khoản.')
    deleteOpen.value = false
    pendingDelete.value = null
    await loadUsers(page.value, { silent: true })
  } catch (error) {
    console.error('Failed to delete user:', error)
    toast.error(error.response?.data?.message || 'Không thể xóa tài khoản.')
  } finally {
    saving.value = false
  }
}

function statusLabel(status) {
  return ({ active: 'Hoạt động', inactive: 'Ngưng hoạt động', banned: 'Bị khóa' })[status] || status || 'Chưa rõ'
}

function formatAssignments(assignments) {
  if (!Array.isArray(assignments)) return []
  return assignments.map(assignment => {
    const organizations = assignment.organizations?.map(org => org.name).join(', ')
      || assignment.organization_ids?.map(id => `#${id}`).join(', ')
    return { role: assignment.role_name, organizations }
  })
}

function handleSearch(value) {
  search.value = value || ''
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadUsers(1), 300)
}

onMounted(() => loadUsers(1))
onBeforeUnmount(() => clearTimeout(searchTimer))
</script>

<template>
  <DefaultLayout>
    <AppPageHeader
      title="Quản lý người dùng"
      subtitle="Tạo tài khoản, cập nhật thông tin và gán vai trò theo từng tổ chức."
      :breadcrumbs="[{ title: 'Quản trị' }, { title: 'Người dùng' }]"
    >
      <template #actions>
        <AppButton v-if="canCreate" icon="mdi-account-plus-outline" @click="openForm()">
          Thêm người dùng
        </AppButton>
      </template>
    </AppPageHeader>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
      <AppStatCard title="Tổng người dùng" :value="stats.total" icon="mdi-account-group-outline" color="primary" />
      <AppStatCard title="Đang hoạt động" :value="stats.active" icon="mdi-account-check-outline" color="success" />
      <AppStatCard title="Ngưng / bị khóa" :value="stats.inactive" icon="mdi-account-cancel-outline" color="warning" />
    </div>

    <div class="bg-white rounded-2xl p-3 sm:p-4 border border-slate-200/80 mb-4">
      <div class="grid grid-cols-1 sm:grid-cols-[1fr_240px] gap-3">
        <v-text-field
          :model-value="search"
          label="Tìm người dùng"
          placeholder="Tên, email hoặc tên đăng nhập"
          prepend-inner-icon="mdi-magnify"
          clearable
          hide-details
          density="comfortable"
          variant="outlined"
          @update:model-value="handleSearch"
        />
        <v-select
          v-model="statusFilter"
          :items="statusOptions"
          item-title="title"
          item-value="value"
          label="Trạng thái"
          hide-details
          density="comfortable"
          variant="outlined"
          @update:model-value="loadUsers(1)"
        />
      </div>
    </div>

    <div v-if="loadError && !items.length" class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
      <div class="flex items-center gap-2">
        <v-icon icon="mdi-alert-circle-outline" size="small" color="error" />
        <span>{{ loadError }}</span>
      </div>
      <AppButton size="small" variant="outlined" color="error" @click="loadUsers(1)">
        Thử lại
      </AppButton>
    </div>

    <AppDataTable
      :headers="headers"
      :items="items"
      :loading="loading"
      :page="page"
      :items-per-page="perPage"
      :total-items="total"
      empty-text="Chưa có người dùng phù hợp."
      @update:page="loadUsers"
    >
      <template #item.user="{ item }">
        <div class="min-w-[180px]">
          <p class="font-semibold text-slate-800">{{ item.name }}</p>
          <p class="text-xs text-slate-500">{{ item.email }}</p>
          <p v-if="item.user_name" class="text-[11px] text-slate-400">@{{ item.user_name }}</p>
        </div>
      </template>
      <template #item.status="{ item }">
        <span
          class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold"
          :class="item.status === 'active' ? 'bg-emerald-50 text-emerald-700' : item.status === 'banned' ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-600'"
        >
          {{ statusLabel(item.status) }}
        </span>
      </template>
      <template #item.assignments="{ item }">
        <div v-if="formatAssignments(item.assignments).length" class="space-y-1.5 min-w-[180px]">
          <div v-for="(assignment, index) in formatAssignments(item.assignments)" :key="`${assignment.role}-${index}`">
            <span class="text-xs font-semibold text-blue-700">{{ assignment.role }}</span>
            <span class="text-[11px] text-slate-500"> · {{ assignment.organizations || 'Chưa có tổ chức' }}</span>
          </div>
        </div>
        <span v-else class="text-xs text-slate-400">Chưa gán vai trò</span>
      </template>
      <template #item.actions="{ item }">
        <div class="flex justify-end gap-1" @click.stop>
          <v-btn
            v-if="canUpdate"
            icon="mdi-pencil-outline"
            variant="text"
            size="small"
            color="primary"
            title="Sửa người dùng"
            @click="openForm(item)"
          />
          <v-btn
            v-if="canDelete && Number(item.id) !== Number(authStore.user?.id)"
            icon="mdi-delete-outline"
            variant="text"
            size="small"
            color="error"
            title="Xóa người dùng"
            @click="confirmDelete(item)"
          />
        </div>
      </template>
    </AppDataTable>

    <AppModal
      v-model="modalOpen"
      :title="selectedUser ? 'Cập nhật người dùng' : 'Tạo người dùng mới'"
      subtitle="Thông tin tài khoản và phạm vi sử dụng được lưu cùng nhau."
      :max-width="760"
    >
      <div class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <v-text-field v-model="form.name" label="Họ và tên *" variant="outlined" density="comfortable" hide-details="auto" />
          <v-text-field v-model="form.email" label="Email *" type="email" variant="outlined" density="comfortable" hide-details="auto" />
          <v-text-field v-model="form.user_name" label="Tên đăng nhập" variant="outlined" density="comfortable" hide-details="auto" />
          <v-select
            v-model="form.status"
            :items="statusOptions.filter(option => option.value)"
            item-title="title"
            item-value="value"
            label="Trạng thái"
            variant="outlined"
            density="comfortable"
            hide-details
          />
          <v-text-field
            v-model="form.password"
            :label="selectedUser ? 'Mật khẩu mới (để trống nếu không đổi)' : 'Mật khẩu ban đầu *'"
            type="password"
            autocomplete="new-password"
            variant="outlined"
            density="comfortable"
            hide-details="auto"
          />
          <v-text-field
            v-model="form.password_confirmation"
            label="Xác nhận mật khẩu"
            type="password"
            autocomplete="new-password"
            variant="outlined"
            density="comfortable"
            hide-details="auto"
          />
        </div>

        <section v-if="canAssignRoles" class="rounded-xl border border-slate-200 p-3 sm:p-4">
          <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <div>
              <h3 class="text-sm font-bold text-slate-800">Vai trò theo tổ chức</h3>
              <p class="text-xs text-slate-500">Một vai trò có thể áp dụng cho nhiều tổ chức.</p>
            </div>
            <v-btn size="small" variant="tonal" prepend-icon="mdi-plus" @click="addAssignment">
              Thêm vai trò
            </v-btn>
          </div>
          <div v-if="!organizationOptions.length" class="rounded-lg bg-amber-50 p-3 text-xs text-amber-800">
            Tài khoản của bạn chưa có danh sách tổ chức để gán. Hãy kiểm tra quyền truy cập tổ chức trước khi phân vai trò.
          </div>
          <div v-else-if="!roles.length" class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
            Chưa có vai trò khả dụng. Hãy tạo vai trò ở mục Quản lý vai trò trước.
          </div>
          <div v-for="(assignment, index) in form.assignments" :key="index" class="grid grid-cols-1 sm:grid-cols-[1fr_1.4fr_auto] gap-2 mb-2 items-start">
            <v-select
              v-model="assignment.role_id"
              :items="roles"
              item-title="name"
              item-value="id"
              label="Vai trò"
              variant="outlined"
              density="compact"
              hide-details
            />
            <v-select
              v-model="assignment.organization_ids"
              :items="organizationOptions"
              item-title="name"
              item-value="id"
              label="Áp dụng tại tổ chức"
              multiple
              chips
              closable-chips
              variant="outlined"
              density="compact"
              hide-details
            />
            <v-btn icon="mdi-close" variant="text" color="error" title="Bỏ vai trò" @click="removeAssignment(index)" />
          </div>
          <p v-if="!form.assignments.length" class="text-xs text-slate-400">Chưa gán vai trò. Người dùng có thể không truy cập được các chức năng quản trị.</p>
        </section>

        <p v-else class="rounded-lg bg-blue-50 p-3 text-xs text-blue-800">
          Bạn có thể quản lý tài khoản, nhưng không có quyền xem danh sách vai trò nên phần gán vai trò được giữ nguyên.
        </p>
      </div>
      <template #footer>
        <v-btn variant="text" :disabled="saving" @click="modalOpen = false">Hủy</v-btn>
        <v-btn color="primary" :loading="saving" prepend-icon="mdi-content-save-outline" @click="saveUser">
          {{ selectedUser ? 'Lưu thay đổi' : 'Tạo tài khoản' }}
        </v-btn>
      </template>
    </AppModal>

    <AppConfirmDialog
      v-model="deleteOpen"
      title="Xóa tài khoản người dùng?"
      :message="`Tài khoản ${pendingDelete?.name || ''} sẽ bị xóa. Thao tác này không thể hoàn tác.`"
      confirm-text="Xóa tài khoản"
      :loading="saving"
      @confirm="deleteUser"
    />
  </DefaultLayout>
</template>
