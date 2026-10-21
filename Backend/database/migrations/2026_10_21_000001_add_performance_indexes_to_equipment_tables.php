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
                $table->index(['organization_id', 'block'], 'rooms_org_block_idx');
                $table->index(['organization_id', 'floor'], 'rooms_org_floor_idx');
                $table->index('room_type_id', 'rooms_room_type_id_idx');
            });
        }

        if (Schema::hasTable('room_equipments')) {
            Schema::table('room_equipments', function (Blueprint $table) {
                $table->index(['organization_id', 'status'], 'equipments_org_status_idx');
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
                $table->dropIndex('rooms_org_block_idx');
                $table->dropIndex('rooms_org_floor_idx');
                $table->dropIndex('rooms_room_type_id_idx');
            });
        }

        if (Schema::hasTable('room_equipments')) {
            Schema::table('room_equipments', function (Blueprint $table) {
                $table->dropIndex('equipments_org_status_idx');
            });
        }
    }
};
