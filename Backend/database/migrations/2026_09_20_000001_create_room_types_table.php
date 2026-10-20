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
        if (!Schema::hasTable('room_types')) {
            Schema::create('room_types', function (Blueprint $table) {
                $table->id();
                $table->string('name')->comment('Tên loại phòng: Tiêu chuẩn, Cao cấp, VIP Suite...');
                $table->string('code', 50)->nullable()->comment('Mã: STD, DLX, SUT, FAM');
                $table->text('description')->nullable();
                $table->decimal('hourly_rate', 12, 2)->nullable()->comment('Giá thuê theo giờ');
                $table->decimal('daily_rate', 12, 2)->nullable()->comment('Giá thuê theo ngày');
                $table->unsignedBigInteger('organization_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};
