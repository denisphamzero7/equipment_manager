<script setup>
import { computed, onMounted, onBeforeUnmount, reactive, ref } from 'vue'
import { ability } from '@/core/casl/ability'
import { toast } from '@/core/helpers/toast'
import AppButton from '@/core/components/AppButton.vue'
import AppConfirmDialog from '@/core/components/AppConfirmDialog.vue'
import AppBulkActionsBar from '@/core/components/AppBulkActionsBar.vue'
import AppDataTable from '@/core/components/AppDataTable.vue'
import AppModal from '@/core/components/AppModal.vue'
import AppPageHeader from '@/core/components/AppPageHeader.vue'
import DefaultLayout from '@/core/layouts/DefaultLayout.vue'
import { accessManagementService } from '../../services/accessManagementService'

const canCreate = computed(() => ability.can('create', 'Roles'))
const canUpdate = computed(() => ability.can('update', 'Roles'))
const canDelete = computed(() => ability.can('delete', 'Roles'))
const canReadPermissions = computed(() => ability.can('tree', 'Permissions'))
const items = ref([])
const permissionTree = ref([])
const permissionTreeLoaded = ref(false)
const loading = ref(false)
const saving = ref(false)
const page = ref(1)
const perPage = 15
const total = ref(0)
const search = ref('')
const modalOpen = ref(false)
const deleteOpen = ref(false)
const selectedRole = ref(null)
const pendingDelete = ref(null)
const errorMessage = ref('')
const form = reactive({ name: '', permission_ids: [] })

const headers = [
  { key: 'role', title: 'Vai trò' },
  { key: 'permissions', title: 'Quyền được cấp' },
  { key: 'actions', title: 'Thao tác', align: 'end', width: '110px' },
]

function responseItems(response) {
  return Array.isArray(response?.data) ? response.data : []
}

function flattenPermissions(nodes, result = []) {
  for (const permission of nodes) {
    result.push(permission)
    flattenPermissions(permission.children || [], result)
  }
  return result
}

const permissionGroups = computed(() => permissionTree.value.map(group => ({
  ...group,
  permissions: flattenPermissions([group]).map(permission => ({
    ...permission,
    label: permission.name,
  })),
})))

async function loadData(targetPage = page.value, { silent = false } = {}) {
  if (!silent) {
    loading.value = true
  }
  errorMessage.value = ''
  try {
    const params = {
      page: targetPage,
      limit: perPage,
      search: search.value || undefined,
      sort_by: 'name',
      sort_order: 'asc',
    }
    const roleResponse = await accessManagementService.getRoles(params)
    items.value = responseItems(roleResponse)
    page.value = roleResponse?.meta?.current_page || targetPage
    total.value = roleResponse?.meta?.total || 0
  } catch (error) {
    console.error('Failed to load role management data:', error)
    errorMessage.value = error.response?.data?.message || 'Không thể tải dữ liệu vai trò.'
    if (!silent) toast.error(errorMessage.value)
  } finally {
    if (!silent) {
      loading.value = false
    }
  }
}

async function openForm(role = null) {
  if (canReadPermissions.value && !permissionTreeLoaded.value) {
    try {
      permissionTree.value = responseItems(await accessManagementService.getPermissionTree())
      permissionTreeLoaded.value = true
    } catch (error) {
      console.error('Failed to load permissions for role form:', error)
      toast.error(error.response?.data?.message || 'Không thể tải danh sách quyền.')
      return
    }
  }
  selectedRole.value = role
  form.name = role?.name || ''
  const permissionNames = new Set(role?.permissions || [])
  form.permission_ids = canReadPermissions.value
    ? flattenPermissions(permissionTree.value).filter(item => permissionNames.has(item.name)).map(item => Number(item.id))
    : []
  modalOpen.value = true
}

function setGroupPermissions(group, checked) {
  const groupIds = group.permissions.map(item => Number(item.id))
  const current = new Set(form.permission_ids)
  groupIds.forEach(id => checked ? current.add(id) : current.delete(id))
  form.permission_ids = [...current]
}

function isGroupSelected(group) {
  return group.permissions.length > 0
    && group.permissions.every(permission => form.permission_ids.includes(Number(permission.id)))
}

async function saveRole() {
  if (!form.name.trim()) {
    toast.warning('Vui lòng nhập tên vai trò.')
    return
  }
  const payload = { name: form.name.trim() }
  if (canReadPermissions.value) payload.permission_ids = form.permission_ids
  saving.value = true
  try {
    if (selectedRole.value) {
      const res = await accessManagementService.updateRole(selectedRole.value.id, payload)
      const updated = res?.data || res
      const idx = items.value.findIndex(r => Number(r.id) === Number(selectedRole.value.id))
      if (idx !== -1) {
        items.value.splice(idx, 1, { ...items.value[idx], ...payload, ...(updated?.id ? updated : {}) })
      }
      modalOpen.value = false
      toast.success('Đã cập nhật vai trò và quyền được cấp.')
      await loadData(page.value, { silent: true })
    } else {
      const res = await accessManagementService.createRole(payload)
      const created = res?.data || res
      if (created?.id) {
        items.value.unshift(created)
        total.value += 1
      }
      modalOpen.value = false
      toast.success('Đã tạo vai trò.')
      await loadData(1, { silent: true })
    }
  } catch (error) {
    console.error('Failed to save role:', error)
    toast.error(error.response?.data?.message || 'Không thể lưu vai trò.')
  } finally {
    saving.value = false
  }
}

function confirmDelete(role) {
  pendingDelete.value = role
  deleteOpen.value = true
}

async function deleteRole() {
  if (!pendingDelete.value) return
  const roleId = pendingDelete.value.id
  saving.value = true
  try {
    await accessManagementService.deleteRole(roleId)
    items.value = items.value.filter(r => Number(r.id) !== Number(roleId))
    total.value = Math.max(0, total.value - 1)
    toast.success('Đã xóa vai trò.')
    deleteOpen.value = false
    pendingDelete.value = null
    await loadData(page.value, { silent: true })
  } catch (error) {
    console.error('Failed to delete role:', error)
    toast.error(error.response?.data?.message || 'Không thể xóa vai trò.')
  } finally {
    saving.value = false
  }
}

let searchTimer
function handleSearch(value) {
  search.value = value || ''
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadData(1), 300)
}

// Bulk Actions
const selectedRoleIds = ref([])
const bulkActionLoading = ref(false)

async function handleBulkDeleteRoles(ids) {
  const protectedRoles = ['Super Admin', 'Admin']
  const selectedRoles = items.value.filter(r => ids.includes(r.id))
  const safeIds = selectedRoles
    .filter(r => !protectedRoles.includes(r.name))
    .map(r => r.id)

  if (safeIds.length !== ids.length) {
    toast.warning('Các vai trò hệ thống (Super Admin, Admin) không thể bị xóa và đã được tự động bỏ qua.')
  }
  if (!safeIds.length) return

  bulkActionLoading.value = true
  try {
    const res = await accessManagementService.bulkDestroyRoles(safeIds)
    toast.success(res?.message || `Đã xóa ${safeIds.length} vai trò thành công!`)
    selectedRoleIds.value = []
    await loadData(page.value)
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Lỗi khi xóa nhiều vai trò.'
    toast.error(msg)
  } finally {
    bulkActionLoading.value = false
  }
}

onMounted(() => loadData(1))
onBeforeUnmount(() => clearTimeout(searchTimer))
</script>

<template>
  <DefaultLayout>
    <AppPageHeader
      title="Quản lý vai trò"
      subtitle="Tạo vai trò theo nghiệp vụ và kiểm soát tập quyền được cấp."
      :breadcrumbs="[{ title: 'Quản trị' }, { title: 'Vai trò' }]"
    >
      <template #actions>
        <AppButton v-if="canCreate" icon="mdi-shield-plus-outline" @click="openForm()">
          Thêm vai trò
        </AppButton>
      </template>
    </AppPageHeader>

    <div class="bg-white rounded-2xl p-3 sm:p-4 border border-slate-200/80 mb-4">
      <v-text-field
        :model-value="search"
        label="Tìm vai trò"
        placeholder="Nhập tên vai trò..."
        prepend-inner-icon="mdi-magnify"
        clearable
        hide-details
        density="comfortable"
        variant="outlined"
        @update:model-value="handleSearch"
      />
    </div>

    <div v-if="!canReadPermissions" class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
      Bạn có thể quản lý tên vai trò, nhưng chưa có quyền xem/sửa danh sách quyền chi tiết.
    </div>
    <div v-if="errorMessage && !items.length" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
      {{ errorMessage }}
    </div>

    <!-- Bulk Actions Bar -->
    <AppBulkActionsBar
      v-model:selected-ids="selectedRoleIds"
      :status-options="[]"
      :can-update-status="false"
      :loading="bulkActionLoading"
      item-label="vai trò"
      delete-confirm-title="Xác nhận xóa vai trò"
      :delete-confirm-message="`Bạn có chắc chắn muốn xóa ${selectedRoleIds.length} vai trò đã chọn? Các tài khoản được gán vai trò này sẽ bị mất các quyền tương ứng.`"
      :can-delete="canDelete"
      @delete="handleBulkDeleteRoles"
      @clear="selectedRoleIds = []"
    />

    <AppDataTable
      v-model="selectedRoleIds"
      show-select
      :headers="headers"
      :items="items"
      :loading="loading"
      :page="page"
      :items-per-page="perPage"
      :total-items="total"
      empty-text="Chưa có vai trò phù hợp."
      @update:page="p => { selectedRoleIds = []; loadData(p) }"
    >
      <template #item.role="{ item }">
        <div>
          <p class="font-semibold text-slate-800">{{ item.name }}</p>
          <p class="text-[11px] text-slate-400">Guard: {{ item.guard_name || 'web' }}</p>
        </div>
      </template>
      <template #item.permissions="{ item }">
        <div class="flex flex-wrap gap-1.5 max-w-3xl">
          <span
            v-for="permission in (item.permissions || []).slice(0, 6)"
            :key="permission"
            class="rounded-md bg-blue-50 px-2 py-1 text-[11px] font-medium text-blue-700"
          >
            {{ permission }}
          </span>
          <span v-if="item.permissions?.length > 6" class="rounded-md bg-slate-100 px-2 py-1 text-[11px] text-slate-600">
            +{{ item.permissions.length - 6 }} quyền
          </span>
          <span v-if="!item.permissions?.length" class="text-xs text-slate-400">Chưa gán quyền</span>
        </div>
      </template>
      <template #item.actions="{ item }">
        <div class="flex justify-end gap-1" @click.stop>
          <v-btn v-if="canUpdate" icon="mdi-pencil-outline" variant="text" size="small" color="primary" title="Sửa vai trò" @click="openForm(item)" />
          <v-btn v-if="canDelete" icon="mdi-delete-outline" variant="text" size="small" color="error" title="Xóa vai trò" @click="confirmDelete(item)" />
        </div>
      </template>
    </AppDataTable>

    <AppModal
      v-model="modalOpen"
      :title="selectedRole ? 'Cập nhật vai trò' : 'Tạo vai trò'"
      subtitle="Thay đổi quyền của vai trò sẽ tác động đến các tài khoản đang được gán vai trò này."
      :max-width="900"
    >
      <div class="space-y-4">
        <v-text-field
          v-model="form.name"
          label="Tên vai trò *"
          placeholder="Ví dụ: Quản lý thiết bị"
          variant="outlined"
          density="comfortable"
          hide-details="auto"
        />
        <section v-if="canReadPermissions" class="rounded-xl border border-slate-200 p-3 sm:p-4">
          <div class="flex items-center justify-between gap-2 mb-3">
            <div>
              <h3 class="text-sm font-bold text-slate-800">Quyền được cấp</h3>
              <p class="text-xs text-slate-500">{{ form.permission_ids.length }} quyền đã chọn</p>
            </div>
            <v-btn
              v-if="form.permission_ids.length"
              size="small"
              variant="text"
              color="secondary"
              @click="form.permission_ids = []"
            >
              Bỏ chọn tất cả
            </v-btn>
          </div>
          <div v-if="!permissionGroups.length" class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
            Chưa có quyền nào. Hãy tạo quyền ở mục Quản lý quyền trước.
          </div>
          <div v-else class="max-h-[48vh] overflow-y-auto space-y-2 pr-1">
            <details v-for="group in permissionGroups" :key="group.id" class="rounded-lg border border-slate-200">
              <summary class="flex cursor-pointer list-none items-center gap-2 px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                <v-checkbox-btn
                  :model-value="isGroupSelected(group)"
                  :indeterminate="group.permissions.some(permission => form.permission_ids.includes(Number(permission.id))) && !isGroupSelected(group)"
                  @click.stop
                  @update:model-value="setGroupPermissions(group, $event)"
                />
                <span class="flex-1">{{ group.name }}</span>
                <span class="text-[11px] font-normal text-slate-400">{{ group.permissions.length }} quyền</span>
                <v-icon icon="mdi-chevron-down" size="16" />
              </summary>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4 px-3 pb-3">
                <v-checkbox
                  v-for="permission in group.permissions"
                  :key="permission.id"
                  v-model="form.permission_ids"
                  :value="Number(permission.id)"
                  :label="permission.label"
                  color="primary"
                  density="compact"
                  hide-details
                />
              </div>
            </details>
          </div>
        </section>
      </div>
      <template #footer>
        <v-btn variant="text" :disabled="saving" @click="modalOpen = false">Hủy</v-btn>
        <v-btn color="primary" :loading="saving" prepend-icon="mdi-content-save-outline" @click="saveRole">
          {{ selectedRole ? 'Lưu thay đổi' : 'Tạo vai trò' }}
        </v-btn>
      </template>
    </AppModal>

    <AppConfirmDialog
      v-model="deleteOpen"
      title="Xóa vai trò?"
      :message="`Vai trò ${pendingDelete?.name || ''} sẽ bị xóa. Các tài khoản đang gán vai trò này có thể mất quyền truy cập tương ứng.`"
      confirm-text="Xóa vai trò"
      :loading="saving"
      @confirm="deleteRole"
    />
  </DefaultLayout>
</template>
