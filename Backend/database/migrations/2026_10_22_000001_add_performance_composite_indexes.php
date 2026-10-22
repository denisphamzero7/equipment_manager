<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('rooms')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->index(['organization_id', 'room_number'], 'rooms_org_room_number_idx');
                $table->index(['organization_id', 'status'], 'rooms_org_status_idx');
            });
        }

        if (Schema::hasTable('room_equipments')) {
            Schema::table('room_equipments', function (Blueprint $table) {
                $table->index(['organization_id', 'serial_number'], 'equipments_org_serial_idx');
                $table->index(['organization_id', 'equipment_model_id'], 'equipments_org_model_idx');
                $table->index(['organization_id', 'room_id'], 'equipments_org_room_idx');
            });
        }

        if (Schema::hasTable('equipment_maintenance_logs')) {
            Schema::table('equipment_maintenance_logs', function (Blueprint $table) {
                $table->index(['room_equipment_id', 'status'], 'maint_logs_eq_status_idx');
            });
        }

        if (Schema::hasTable('log_activities')) {
            Schema::table('log_activities', function (Blueprint $table) {
                $table->index(['created_at', 'organization_id'], 'log_activities_created_org_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('rooms')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->dropIndex('rooms_org_room_number_idx');
                $table->dropIndex('rooms_org_status_idx');
            });
        }

        if (Schema::hasTable('room_equipments')) {
            Schema::table('room_equipments', function (Blueprint $table) {
                $table->dropIndex('equipments_org_serial_idx');
                $table->dropIndex('equipments_org_model_idx');
                $table->dropIndex('equipments_org_room_idx');
            });
        }

        if (Schema::hasTable('equipment_maintenance_logs')) {
            Schema::table('equipment_maintenance_logs', function (Blueprint $table) {
                $table->dropIndex('maint_logs_eq_status_idx');
            });
        }

        if (Schema::hasTable('log_activities')) {
            Schema::table('log_activities', function (Blueprint $table) {
                $table->dropIndex('log_activities_created_org_idx');
            });
        }
    }
};
