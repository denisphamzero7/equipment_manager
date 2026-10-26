<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
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

const canCreate = computed(() => ability.can('create', 'Permissions'))
const canUpdate = computed(() => ability.can('update', 'Permissions'))
const canDelete = computed(() => ability.can('delete', 'Permissions'))
const permissionTree = ref([])
const loading = ref(false)
const saving = ref(false)
const search = ref('')
const modalOpen = ref(false)
const deleteOpen = ref(false)
const selectedPermission = ref(null)
const pendingDelete = ref(null)
const errorMessage = ref('')
const form = reactive({
  name: '',
  description: '',
  parent_id: null,
  sort_order: 0,
})

const headers = [
  { key: 'name', title: 'Quyền', width: '34%' },
  { key: 'description', title: 'Mô tả' },
  { key: 'parent', title: 'Nhóm' },
  { key: 'actions', title: 'Thao tác', align: 'end', width: '110px' },
]

function responseItems(response) {
  return Array.isArray(response?.data) ? response.data : []
}

function flattenTree(nodes, level = 0, result = []) {
  for (const node of nodes) {
    result.push({
      ...node,
      level,
      hasChildren: Boolean(node.children?.length),
    })
    flattenTree(node.children || [], level + 1, result)
  }
  return result
}

const flatPermissions = computed(() => flattenTree(permissionTree.value))
const filteredPermissions = computed(() => {
  const keyword = search.value.trim().toLocaleLowerCase()
  if (!keyword) return flatPermissions.value
  return flatPermissions.value.filter(permission =>
    `${permission.name} ${permission.description || ''}`.toLocaleLowerCase().includes(keyword)
  )
})
const parentOptions = computed(() => {
  const excludedIds = new Set()
  if (selectedPermission.value) {
    const collectDescendants = node => {
      excludedIds.add(Number(node.id))
      ;(node.children || []).forEach(collectDescendants)
    }
    const findNode = nodes => {
      for (const node of nodes) {
        if (Number(node.id) === Number(selectedPermission.value.id)) {
          collectDescendants(node)
          return true
        }
        if (findNode(node.children || [])) return true
      }
      return false
    }
    findNode(permissionTree.value)
  }

  return [
    { id: null, label: 'Không có nhóm (quyền gốc)' },
    ...flatPermissions.value
      .filter(permission => !excludedIds.has(Number(permission.id)))
      .map(permission => ({
        id: Number(permission.id),
        label: `${'　'.repeat(permission.level)}${permission.name}`,
      })),
  ]
})

async function loadPermissions({ silent = false } = {}) {
  if (!silent) {
    loading.value = true
  }
  errorMessage.value = ''
  try {
    const response = await accessManagementService.getPermissionTree()
    permissionTree.value = responseItems(response)
  } catch (error) {
    console.error('Failed to load permission tree:', error)
    errorMessage.value = error.response?.data?.message || 'Không thể tải danh sách quyền.'
    if (!silent) toast.error(errorMessage.value)
  } finally {
    if (!silent) {
      loading.value = false
    }
  }
}

function openForm(permission = null) {
  selectedPermission.value = permission
  Object.assign(form, {
    name: permission?.name || '',
    description: permission?.description || '',
    parent_id: permission?.parent_id ? Number(permission.parent_id) : null,
    sort_order: Number(permission?.sort_order || 0),
  })
  modalOpen.value = true
}

async function savePermission() {
  if (!form.name.trim()) {
    toast.warning('Vui lòng nhập mã quyền.')
    return
  }
  const payload = {
    name: form.name.trim(),
    description: form.description.trim() || null,
    parent_id: form.parent_id || null,
    sort_order: Number(form.sort_order) || 0,
    guard_name: 'web',
  }
  saving.value = true
  try {
    if (selectedPermission.value) {
      await accessManagementService.updatePermission(selectedPermission.value.id, payload)
      toast.success('Đã cập nhật quyền.')
    } else {
      await accessManagementService.createPermission(payload)
      toast.success('Đã tạo quyền.')
    }
    modalOpen.value = false
    await loadPermissions({ silent: true })
  } catch (error) {
    console.error('Failed to save permission:', error)
    toast.error(error.response?.data?.message || 'Không thể lưu quyền.')
  } finally {
    saving.value = false
  }
}

function confirmDelete(permission) {
  pendingDelete.value = permission
  deleteOpen.value = true
}

async function deletePermission() {
  if (!pendingDelete.value) return
  saving.value = true
  try {
    await accessManagementService.deletePermission(pendingDelete.value.id)
    toast.success('Đã xóa quyền.')
    deleteOpen.value = false
    pendingDelete.value = null
    await loadPermissions({ silent: true })
  } catch (error) {
    console.error('Failed to delete permission:', error)
    toast.error(error.response?.data?.message || 'Không thể xóa quyền.')
  } finally {
    saving.value = false
  }
}

// Bulk Actions
const selectedPermissionIds = ref([])
const bulkActionLoading = ref(false)

async function handleBulkDeletePermissions(ids) {
  bulkActionLoading.value = true
  try {
    const res = await accessManagementService.bulkDestroyPermissions(ids)
    toast.success(res?.message || `Đã xóa ${ids.length} quyền thành công!`)
    selectedPermissionIds.value = []
    await loadPermissions({ silent: true })
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Lỗi khi xóa nhiều quyền.'
    toast.error(msg)
  } finally {
    bulkActionLoading.value = false
  }
}

onMounted(loadPermissions)
</script>

<template>
  <DefaultLayout>
    <AppPageHeader
      title="Quản lý quyền"
      subtitle="Tổ chức quyền theo nhóm và quản lý chính xác mã quyền được hệ thống sử dụng."
      :breadcrumbs="[{ title: 'Quản trị' }, { title: 'Quyền' }]"
    >
      <template #actions>
        <AppButton v-if="canCreate" icon="mdi-key-plus" @click="openForm()">
          Thêm quyền
        </AppButton>
      </template>
    </AppPageHeader>

    <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-relaxed text-amber-900">
      Mã quyền được dùng trong middleware/API (ví dụ <code>users.index</code>). Đổi hoặc xóa mã có thể làm thay đổi quyền truy cập; hãy rà soát role và cấu hình route trước khi áp dụng.
    </div>

    <div class="bg-white rounded-2xl p-3 sm:p-4 border border-slate-200/80 mb-4">
      <v-text-field
        v-model="search"
        label="Tìm quyền"
        placeholder="Tên quyền hoặc mô tả..."
        prepend-inner-icon="mdi-magnify"
        clearable
        hide-details
        density="comfortable"
        variant="outlined"
      />
    </div>
    <div v-if="errorMessage && !permissionTree.length" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
      {{ errorMessage }}
    </div>

    <!-- Bulk Actions Bar -->
    <AppBulkActionsBar
      v-model="selectedPermissionIds"
      :all-ids="filteredPermissions.map(p => p.id)"
      :status-options="[]"
      :can-update-status="false"
      :loading="bulkActionLoading"
      item-label="quyền"
      delete-confirm-title="Xác nhận xóa quyền"
      :delete-confirm-message="`Bạn có chắc chắn muốn xóa ${selectedPermissionIds.length} quyền đã chọn? Việc này có thể ảnh hưởng đến các vai trò đang sở hữu các quyền này.`"
      :can-delete="canDelete"
      @delete="handleBulkDeletePermissions"
      @clear="selectedPermissionIds = []"
    />

    <AppDataTable
      v-model="selectedPermissionIds"
      show-select
      :headers="headers"
      :items="filteredPermissions"
      :loading="loading"
      :total-items="filteredPermissions.length"
      :page="1"
      :items-per-page="filteredPermissions.length || 1"
      empty-text="Chưa có quyền phù hợp."
    >
      <template #item.name="{ item }">
        <div class="flex items-center gap-2 min-w-[200px]" :style="{ paddingLeft: `${item.level * 18}px` }">
          <v-icon :icon="item.hasChildren ? 'mdi-folder-key-outline' : 'mdi-key-outline'" size="17" :color="item.hasChildren ? 'primary' : 'secondary'" />
          <span class="font-semibold text-slate-800">{{ item.name }}</span>
        </div>
      </template>
      <template #item.description="{ item }">
        <span class="text-sm text-slate-600">{{ item.description || '—' }}</span>
      </template>
      <template #item.parent="{ item }">
        <span class="text-xs text-slate-500">{{ item.parent_id ? `#${item.parent_id}` : 'Quyền gốc' }}</span>
      </template>
      <template #item.actions="{ item }">
        <div class="flex justify-end gap-1" @click.stop>
          <v-btn v-if="canUpdate" icon="mdi-pencil-outline" variant="text" size="small" color="primary" title="Sửa quyền" @click="openForm(item)" />
          <v-btn
            v-if="canDelete"
            icon="mdi-delete-outline"
            variant="text"
            size="small"
            color="error"
            :disabled="item.hasChildren"
            :title="item.hasChildren ? 'Hãy xóa hoặc chuyển các quyền con trước' : 'Xóa quyền'"
            @click="confirmDelete(item)"
          />
        </div>
      </template>
    </AppDataTable>

    <AppModal
      v-model="modalOpen"
      :title="selectedPermission ? 'Cập nhật quyền' : 'Tạo quyền mới'"
      subtitle="Dùng mã ổn định, dễ hiểu và khớp với tên permission trong route/middleware."
      :max-width="680"
    >
      <div class="space-y-3">
        <v-text-field
          v-model="form.name"
          label="Mã quyền *"
          placeholder="Ví dụ: users.index"
          hint="Mã quyền phải khớp chính xác với cấu hình middleware."
          persistent-hint
          variant="outlined"
          density="comfortable"
        />
        <v-text-field
          v-model="form.description"
          label="Mô tả hiển thị"
          placeholder="Ví dụ: Xem danh sách người dùng"
          variant="outlined"
          density="comfortable"
          hide-details
        />
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <v-select
            v-model="form.parent_id"
            :items="parentOptions"
            item-title="label"
            item-value="id"
            label="Nhóm quyền cha"
            variant="outlined"
            density="comfortable"
            hide-details
          />
          <v-text-field
            v-model.number="form.sort_order"
            label="Thứ tự hiển thị"
            type="number"
            min="0"
            variant="outlined"
            density="comfortable"
            hide-details
          />
        </div>
      </div>
      <template #footer>
        <v-btn variant="text" :disabled="saving" @click="modalOpen = false">Hủy</v-btn>
        <v-btn color="primary" :loading="saving" prepend-icon="mdi-content-save-outline" @click="savePermission">
          {{ selectedPermission ? 'Lưu thay đổi' : 'Tạo quyền' }}
        </v-btn>
      </template>
    </AppModal>

    <AppConfirmDialog
      v-model="deleteOpen"
      title="Xóa quyền?"
      :message="`Quyền ${pendingDelete?.name || ''} sẽ bị xóa và có thể bị gỡ khỏi các vai trò đang sử dụng. Hãy xác nhận đã rà soát tác động.`"
      confirm-text="Xóa quyền"
      :loading="saving"
      @confirm="deletePermission"
    />
  </DefaultLayout>
</template>
