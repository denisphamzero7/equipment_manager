<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Enums\UserStatusEnum;
use App\Modules\Core\Exports\UsersExport;
use App\Modules\Core\Imports\UsersImport;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserService
{
    public function stats(array $filters): array
    {
        $base = User::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', UserStatusEnum::Active->value)->count(),
            'inactive' => (clone $base)->where('status', '!=', UserStatusEnum::Active->value)->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        $users = User::query()
            ->with(['creator:id,name', 'editor:id,name'])
            ->filter($filters)
            ->paginate($limit);

        $this->loadRoleAssignments($users->getCollection());

        return $users;
    }

    public function store(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $assignments = $this->normalizeAssignments($data['assignments'] ?? []);
            unset($data['assignments']);
            $data['password'] = Hash::make($data['password']);

            $user = User::create($data);
            $this->syncUserAssignments($user, $assignments);

            return $user;
        });
    }

    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $hasAssignments = array_key_exists('assignments', $data);
            $assignments = $this->normalizeAssignments($data['assignments'] ?? []);
            unset($data['assignments']);

            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            $user->update($data);

            if ($hasAssignments) {
                $this->syncUserAssignments($user, $assignments);
            }

            return $user;
        });
    }

    public function destroy(User $user): void
    {
        $user->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        User::destroy($ids);
    }

    public function bulkUpdateStatus(array $ids, string $status): void
    {
        User::whereIn('id', $ids)->update(['status' => $status]);
    }

    public function changeStatus(User $user, string $status): User
    {
        $user->update(['status' => $status]);

        return $user;
    }

    public function export(array $filters): BinaryFileResponse
    {
        return Excel::download(new UsersExport($filters), 'users.xlsx');
    }

    public function import($file): void
    {
        Excel::import(new UsersImport, $file);
    }

    /**
     * Chuẩn hóa payload assignments:
     * [
     *   ['role_id' => 1, 'organization_ids' => [2,3]],
     *   ['role_id' => 5, 'organization_ids' => [9]],
     * ]
     * => [organization_id => [role_id, ...]]
     */
    protected function normalizeAssignments(array $assignments): array
    {
        $map = [];
        $roleIds = collect($assignments)
            ->pluck('role_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $roles = Role::query()
            ->whereIn('id', $roleIds)
            ->get()
            ->keyBy('id');

        foreach ($assignments as $assignment) {
            $roleId = (int) ($assignment['role_id'] ?? 0);
            $organizationIds = collect($assignment['organization_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $role = $roles->get($roleId);
            if (! $role) {
                throw ValidationException::withMessages([
                    'assignments' => ["Vai trò #{$roleId} không tồn tại."],
                ]);
            }

            foreach ($organizationIds as $organizationId) {
                // Tương thích ngược: nếu role còn gắn organization_id thì phải khớp tổ chức được gán.
                if (isset($role->organization_id) && $role->organization_id !== null && (int) $role->organization_id !== $organizationId) {
                    throw ValidationException::withMessages([
                        'assignments' => ["Vai trò '{$role->name}' chỉ áp dụng cho tổ chức #{$role->organization_id}, không thể gán cho tổ chức #{$organizationId}."],
                    ]);
                }

                $map[$organizationId] ??= [];
                $map[$organizationId][] = $roleId;
            }
        }

        foreach ($map as $organizationId => $orgRoleIds) {
            $map[$organizationId] = array_values(array_unique($orgRoleIds));
        }

        return $map;
    }

    protected function syncUserAssignments(User $user, array $assignments): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $modelHasRolesTable = $tableNames['model_has_roles'] ?? 'model_has_roles';
        $rolePivotKey = $columnNames['role_pivot_key'] ?? 'role_id';
        $modelMorphKey = $columnNames['model_morph_key'] ?? 'model_id';
        $teamForeignKey = $columnNames['team_foreign_key'] ?? 'organization_id';

        DB::table($modelHasRolesTable)
            ->where($modelMorphKey, $user->id)
            ->where('model_type', User::class)
            ->delete();

        if (empty($assignments)) {
            return;
        }

        $rows = [];
        foreach ($assignments as $organizationId => $roleIds) {
            foreach ($roleIds as $roleId) {
                $rows[] = [
                    $teamForeignKey => (int) $organizationId,
                    $rolePivotKey => (int) $roleId,
                    'model_type' => User::class,
                    $modelMorphKey => $user->id,
                ];
            }
        }

        if (! empty($rows)) {
            DB::table($modelHasRolesTable)->insert($rows);
        }
    }

    protected function loadRoleAssignments(Collection $users): void
    {
        if ($users->isEmpty()) {
            return;
        }

        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $modelHasRolesTable = $tableNames['model_has_roles'] ?? 'model_has_roles';
        $rolePivotKey = $columnNames['role_pivot_key'] ?? 'role_id';
        $modelMorphKey = $columnNames['model_morph_key'] ?? 'model_id';
        $teamForeignKey = $columnNames['team_foreign_key'] ?? 'organization_id';
        $userIds = $users->pluck('id')->map(fn ($id) => (int) $id)->all();

        $rows = DB::table($modelHasRolesTable.' as user_roles')
            ->leftJoin(($tableNames['roles'] ?? 'roles').' as roles', 'roles.id', '=', 'user_roles.'.$rolePivotKey)
            ->leftJoin('organizations', 'organizations.id', '=', 'user_roles.'.$teamForeignKey)
            ->whereIn('user_roles.'.$modelMorphKey, $userIds)
            ->where('user_roles.model_type', User::class)
            ->select([
                'user_roles.'.$modelMorphKey.' as user_id',
                'user_roles.'.$teamForeignKey.' as organization_id',
                'user_roles.'.$rolePivotKey.' as role_id',
                'roles.name as role_name',
                'organizations.name as organization_name',
            ])
            ->get()
            ->groupBy('user_id');

        foreach ($users as $user) {
            $assignments = $rows->get($user->id, collect())
                ->groupBy('role_id')
                ->map(function ($roleRows, $roleId) {
                    $organizations = $roleRows
                        ->unique('organization_id')
                        ->values()
                        ->map(fn ($item) => [
                            'id' => (int) $item->organization_id,
                            'name' => $item->organization_name,
                        ]);

                    return [
                        'role_id' => (int) $roleId,
                        'role_name' => $roleRows->first()->role_name,
                        'organization_ids' => $roleRows
                            ->pluck('organization_id')
                            ->map(fn ($id) => (int) $id)
                            ->unique()
                            ->values()
                            ->all(),
                        'organizations' => $organizations->all(),
                    ];
                })
                ->values()
                ->all();

            $user->setAttribute('role_assignments_data', $assignments);
        }
    }
}
