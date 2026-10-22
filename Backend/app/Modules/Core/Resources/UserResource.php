<?php

namespace App\Modules\Core\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $auditUserNames = DB::table('users')
            ->whereIn('id', array_filter([$this->created_by, $this->updated_by]))
            ->pluck('name', 'id');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'user_name' => $this->user_name,
            'status' => $this->status,
            'created_by' => $auditUserNames->get($this->created_by) ?? 'N/A',
            'updated_by' => $auditUserNames->get($this->updated_by) ?? 'N/A',
            'assignments' => $this->roleAssignments(),
            'created_at' => $this->created_at?->format('d/m/Y H:i:s'),
            'updated_at' => $this->updated_at?->format('d/m/Y H:i:s'),
        ];
    }

    protected function roleAssignments(): array
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $modelHasRolesTable = $tableNames['model_has_roles'] ?? 'model_has_roles';
        $rolePivotKey = $columnNames['role_pivot_key'] ?? 'role_id';
        $modelMorphKey = $columnNames['model_morph_key'] ?? 'model_id';
        $teamForeignKey = $columnNames['team_foreign_key'] ?? 'organization_id';

        $rows = DB::table($modelHasRolesTable.' as user_roles')
            ->leftJoin(($tableNames['roles'] ?? 'roles').' as roles', 'roles.id', '=', 'user_roles.'.$rolePivotKey)
            ->leftJoin('organizations', 'organizations.id', '=', 'user_roles.'.$teamForeignKey)
            ->where('user_roles.'.$modelMorphKey, $this->id)
            ->where('user_roles.model_type', \App\Modules\Core\Models\User::class)
            ->select([
                'user_roles.'.$teamForeignKey.' as organization_id',
                'user_roles.'.$rolePivotKey.' as role_id',
                'roles.name as role_name',
                'organizations.name as organization_name',
            ])
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        return $rows
            ->groupBy('role_id')
            ->map(function ($items, $roleId) {
                $organizations = $items
                    ->unique('organization_id')
                    ->values()
                    ->map(fn ($item) => [
                        'id' => (int) $item->organization_id,
                        'name' => $item->organization_name,
                    ]);

                return [
                    'role_id' => (int) $roleId,
                    'role_name' => $items->first()->role_name,
                    'organization_ids' => $items
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
    }
}
