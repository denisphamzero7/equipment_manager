<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Kênh riêng tư theo từng Tổ chức / Khách sạn thành viên (Multi-tenancy).
 * Đảm bảo chỉ người dùng thuộc tổ chức mới có thể nhận sự kiện realtime của tổ chức đó.
 */
Broadcast::channel('organization.{orgId}', function ($user, $orgId) {
    if (! $user) {
        return false;
    }

    // Super Admin có quyền truy cập tất cả tổ chức
    if (method_exists($user, 'hasRole') && $user->hasRole('Super Admin')) {
        return true;
    }

    $columnNames = config('permission.column_names');
    $teamForeignKey = $columnNames['team_foreign_key'] ?? 'organization_id';
    $tableNames = config('permission.table_names');

    return DB::table($tableNames['model_has_roles'])
        ->where('model_id', $user->id)
        ->where('model_type', get_class($user))
        ->where($teamForeignKey, (int) $orgId)
        ->exists();
});

