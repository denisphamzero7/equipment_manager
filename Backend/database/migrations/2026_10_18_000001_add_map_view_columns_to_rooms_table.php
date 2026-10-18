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
                if (!Schema::hasColumn('rooms', 'block')) {
                    $table->string('block', 50)->nullable()->after('room_number')->comment('Block A, Block B, Condos 1, Penthouse...');
                }
                if (!Schema::hasColumn('rooms', 'floor')) {
                    $table->integer('floor')->nullable()->after('block')->comment('Tầng 1..7');
                }
                if (!Schema::hasColumn('rooms', 'grid_row')) {
                    $table->integer('grid_row')->nullable()->after('floor')->comment('Tọa độ dòng trên Map View');
                }
                if (!Schema::hasColumn('rooms', 'grid_col')) {
                    $table->integer('grid_col')->nullable()->after('grid_row')->comment('Tọa độ cột trên Map View');
                }
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
                $columns = ['block', 'floor', 'grid_row', 'grid_col'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('rooms', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
