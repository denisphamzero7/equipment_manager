<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rooms')) {
            Schema::table('rooms', function (Blueprint $table) {
                if (!Schema::hasColumn('rooms', 'bedroom_count')) {
                    $table->unsignedInteger('bedroom_count')->default(1)->after('room_number')->comment('Số phòng ngủ (0 = Studio/Office, 1, 2, 3...)');
                }
                if (!Schema::hasColumn('rooms', 'living_room_count')) {
                    $table->unsignedInteger('living_room_count')->default(0)->after('bedroom_count')->comment('Số phòng khách (0 = Không có PK riêng, 1, 2...)');
                }
                if (!Schema::hasColumn('rooms', 'wifi_mode')) {
                    $table->string('wifi_mode', 30)->default('dedicated')->after('living_room_count')->comment('dedicated: router riêng; shared: dùng ké wifi; none: không wifi');
                }
                if (!Schema::hasColumn('rooms', 'shared_wifi_room_id')) {
                    $table->unsignedBigInteger('shared_wifi_room_id')->nullable()->after('wifi_mode')->comment('ID phòng phát wifi dùng ké');
                }
                if (!Schema::hasColumn('rooms', 'shared_wifi_note')) {
                    $table->string('shared_wifi_note', 255)->nullable()->after('shared_wifi_room_id')->comment('Ghi chú dùng ké wifi');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rooms')) {
            Schema::table('rooms', function (Blueprint $table) {
                $columns = ['bedroom_count', 'living_room_count', 'wifi_mode', 'shared_wifi_room_id', 'shared_wifi_note'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('rooms', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
