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
        // 1. Bảng nhóm thiết bị (TV, Remote, Box, Wifi, AC, Tủ lạnh...)
        if (!Schema::hasTable('equipment_categories')) {
            Schema::create('equipment_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name')->comment('Tên loại: TV, Điều khiển, TV Box, Thiết bị Wifi...');
                $table->string('code')->unique()->comment('Mã: tv, remote, box, wifi...');
                $table->string('icon')->nullable()->comment('Icon hiển thị UI');
                $table->text('description')->nullable();
                $table->integer('sort_order')->default(0);
                $table->unsignedBigInteger('organization_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        // 2. Bảng model / quy cách thiết bị (TV 32 inch, TV 40 inch, Box FPT, Remote Sony...)
        if (!Schema::hasTable('equipment_models')) {
            Schema::create('equipment_models', function (Blueprint $table) {
                $table->id();
                $table->foreignId('equipment_category_id')->constrained('equipment_categories')->cascadeOnDelete();
                $table->string('name')->comment('Tên model: TV Samsung 32 inch, TV LG 40 inch...');
                $table->string('brand')->nullable()->comment('Hãng: Samsung, LG, Sony, TP-Link...');
                $table->string('model_code')->nullable()->comment('Mã hiệu nhà sản xuất');
                $table->json('specs')->nullable()->comment('Thông số kỹ thuật JSON (kích thước, cổng...)');
                $table->unsignedBigInteger('organization_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        // 3. Bảng định mức thiết bị chuẩn theo loại phòng (Room Standard Equipments)
        if (!Schema::hasTable('room_standard_equipments')) {
            Schema::create('room_standard_equipments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('room_type_id')->comment('Khóa ngoại room_types.id');
                $table->foreignId('equipment_model_id')->constrained('equipment_models')->cascadeOnDelete();
                $table->integer('standard_quantity')->default(1)->comment('Số lượng bắt buộc phải có trong phòng');
                $table->unsignedBigInteger('organization_id')->nullable();
                $table->timestamps();

                $table->unique(['room_type_id', 'equipment_model_id'], 'room_type_model_unique');
            });
        }

        // 4. Bảng quản lý từng cá thể thiết bị cụ thể (Room Equipments)
        if (!Schema::hasTable('room_equipments')) {
            Schema::create('room_equipments', function (Blueprint $table) {
                $table->id();
                $table->string('serial_number')->unique()->comment('Số Serial / Mã tài sản / QR code');
                $table->foreignId('equipment_model_id')->constrained('equipment_models')->cascadeOnDelete();
                $table->unsignedBigInteger('room_id')->nullable()->comment('Phòng đang đặt (null = trong kho)');
                $table->string('status', 30)->default('good')->comment('good, broken, maintenance, lost, in_stock, disposed');
                $table->string('position_note')->nullable()->comment('Vị trí đặt: Phòng khách, Phòng ngủ 1, Phòng ngủ 2...');
                $table->text('note')->nullable()->comment('Ghi chú tình trạng hỏng hóc, sửa chữa');
                $table->dateTime('installed_at')->nullable()->comment('Ngày bắt đầu lắp vào phòng');
                $table->unsignedBigInteger('organization_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->softDeletes();
                $table->timestamps();

                $table->index(['room_id', 'status']);
            });
        }

        // 5. Bảng nhật ký bảo trì / báo hỏng thiết bị (Equipment Maintenance Logs)
        if (!Schema::hasTable('equipment_maintenance_logs')) {
            Schema::create('equipment_maintenance_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('room_equipment_id')->constrained('room_equipments')->cascadeOnDelete();
                $table->unsignedBigInteger('room_id')->nullable()->comment('Phòng khi xảy ra lỗi');
                $table->text('issue_description')->comment('Mô tả tình trạng hư hỏng');
                $table->string('status', 30)->default('pending')->comment('pending, in_progress, completed, unfixable');
                $table->decimal('cost', 12, 2)->nullable()->comment('Chi phí sửa chữa');
                $table->unsignedBigInteger('reported_by')->nullable();
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->dateTime('resolved_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_maintenance_logs');
        Schema::dropIfExists('room_equipments');
        Schema::dropIfExists('room_standard_equipments');
        Schema::dropIfExists('equipment_models');
        Schema::dropIfExists('equipment_categories');
    }
};
