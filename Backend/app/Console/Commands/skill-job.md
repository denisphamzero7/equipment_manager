# Phân tích chi tiết kỹ thuật Module Jobs

**Ngày tạo:** 2026-06-27
**Mục đích:** Tài liệu hóa kiến trúc và luồng hoạt động của module `Jobs` để làm mẫu (template) cho việc phát triển các module mới, đảm bảo tính nhất quán và kế thừa các giải pháp kỹ thuật đã triển khai.

---

## 1. Tổng quan

Module `Jobs` quản lý thực thể "Công việc" (`ITJob`), là một ví dụ điển hình cho kiến trúc hướng sự kiện (Event-Driven) và xử lý nền (Queues) trong dự án.

- **Prefix API:** `/api/jobs`
- **Model chính:** `App\Modules\Jobs\Models\ITJob`
- **Service chính:** `App\Modules\Jobs\Services\JobService`
- **Công nghệ nổi bật:**
    - **Events & Listeners:** Tách rời logic nghiệp vụ khỏi các tác vụ phụ (như gửi email, thông báo realtime).
    - **Laravel Queues & Horizon:** Đưa các tác vụ tốn thời gian (gửi email) vào hàng đợi để xử lý nền, cải thiện tốc độ phản hồi cho người dùng.
    - **Laravel Notifications:** Gửi thông báo đa kênh (email, broadcast) một cách nhất quán.
    - **Laravel Scheduler:** Chạy các tác vụ định kỳ (nhắc việc).
    - **Real-time (Broadcast):** Cập nhật giao diện người dùng tức thì thông qua Laravel Reverb/Websockets.

---

## 2. Cơ sở dữ liệu

### 2.1. Bảng `itjobs`

Bảng này lưu trữ thông tin chi tiết về các công việc.

| Cột | Kiểu | Nullable | Ghi chú |
|---|---|---|---|
| `id` | bigint | No | PK, auto increment |
| `title` | varchar(255) | No | Tiêu đề công việc |
| `description` | text | Yes | Mô tả chi tiết |
| `status` | varchar(255) | No | Trạng thái công việc (sử dụng `JobStatusEnum`) |
| `organization_id` | bigint | No | FK -> `organizations.id` (lọc theo tenant) |
| `created_by` | bigint | Yes | FK -> `users.id` (người tạo) |
| `updated_by` | bigint | Yes | FK -> `users.id` (người sửa cuối) |
| `due_at` | datetime | Yes | Hạn chót công việc |
| `is_notified` | boolean | No | Default: `false`. Đánh dấu đã gửi nhắc nhở hay chưa |
| `created_at` | timestamp | Yes | |
| `updated_at` | timestamp | Yes | |

### 2.2. Migration

Đây là nội dung file migration để tạo bảng `itjobs` và các ràng buộc khóa ngoại cần thiết.

`database/migrations/YYYY_MM_DD_HHMMSS_create_itjobs_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itjobs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('due_at')->nullable();
            $table->boolean('is_notified')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itjobs');
    }
};
```

### 2.3. Quan hệ

- `creator()`: `belongsTo(User::class, 'created_by')`
- `editor()`: `belongsTo(User::class, 'updated_by')`

---

## 3. Model `ITJob`

- **Vị trí:** `App\Modules\Jobs\Models\ITJob.php`
- **Traits:** `HasFactory`, `InteractsWithMedia` (nếu cần đính kèm file).
- **Fillable:** `title`, `description`, `status`, `organization_id`, `created_by`, `updated_by`, `due_at`, `is_notified`.
- **Casts:**
    - `status`: `JobStatusEnum::class` -> Tự động ép kiểu sang Enum.
    - `due_at`: `'datetime'` -> Ép kiểu sang đối tượng Carbon.
    - `is_notified`: `'boolean'`.
- **Logic `booted()`:**
    - `creating`: Tự động gán `created_by` và `updated_by` bằng ID của người dùng đang đăng nhập.
    - `updating`: Tự động cập nhật `updated_by`.
- **Scope `filter()`:** Cung cấp bộ lọc linh hoạt cho API index, bao gồm tìm kiếm, trạng thái, ngày tháng, người tạo và sắp xếp.

---

## 4. Kiến trúc hướng sự kiện (Event-Driven)

Đây là trái tim của module, giúp hệ thống linh hoạt và dễ mở rộng.

**Luồng hoạt động:**

1.  **Kích hoạt (Dispatch):** `JobService` sau khi thực hiện một hành động (tạo, sửa, xóa) sẽ gọi `broadcast(new JobUpdatedEvent($action, $data))`.
    - `$action`: Một chuỗi định danh hành động, ví dụ: `'job-created'`, `'job-updated'`.
    - `$data`: Dữ liệu liên quan, thường là mảng của model `ITJob`.

2.  **Lắng nghe (Listen):** `App\Modules\Jobs\Listeners\JobActionHandler` được đăng ký để lắng nghe `JobUpdatedEvent` (trong `AppServiceProvider`). Listener này implement `ShouldQueue` để các hành động xử lý (gửi mail) không làm chậm request chính.

3.  **Xử lý (Handle):** Phương thức `handle()` của `JobActionHandler` nhận `JobUpdatedEvent`.
    - Sử dụng `switch ($event->action)` để thực hiện logic tương ứng với từng hành động.
    - Ví dụ: với action `job-created`, listener sẽ lấy danh sách người dùng và gửi thông báo.

4.  **Thông báo (Notify):** Listener sẽ kích hoạt `JobNotification` bằng cách gọi `Notification::send($users, new JobNotification($action, $data))`.

**Sơ đồ luồng:**

```
[Controller] -> [JobService] --(DB Transaction)--> [Database]
     ^                  |
     |                  | (Sau khi thành công)
     |                  v
     |        broadcast(new JobUpdatedEvent)
     |                  |
     |                  v
     |             [Laravel Event Bus] --(Pushes to Queue)--> [JobActionHandler]
     |                                                               |
     |                                                               v
     +-----(Response nhanh cho User)                     [Notification::send(new JobNotification)]
                                                                     |
                                                                     v
                                                         +-----------+-----------+
                                                         |                       |
                                                     [Mail Channel]        [Broadcast Channel]
                                                         |                       |
                                                         v                       v
                                                     [Email User]            [Realtime FE]
```

---

## 5. Lớp Service (`JobService`)

- **Vị trí:** `App\Modules\Jobs\Services\JobService.php`
- **Vai trò:** Đóng gói toàn bộ logic nghiệp vụ, giúp Controller tinh gọn, chỉ làm nhiệm vụ điều phối request và response.
- **Các phương thức chính:** `stats`, `index`, `show`, `store`, `update`, `destroy`, `bulkDestroy`, `bulkUpdateStatus`, `export`, `import`.
- **Pattern quan trọng:** Luôn bọc các thao tác ghi/sửa/xóa database trong `DB::transaction()`. Lệnh `broadcast()` (hoặc `event()`) phải được đặt **bên ngoài và sau khi** transaction thành công để đảm bảo chỉ thông báo khi dữ liệu đã được cam kết lưu.

---

## 6. Lớp Notification (`JobNotification`)

- **Vị trí:** `App\Modules\Jobs\Notifications\JobNotification.php`
- **Vai trò:** Định nghĩa nội dung và các kênh sẽ gửi thông báo. Thay thế cho việc phải tạo các lớp Mailable và Event riêng lẻ.
- **`via(object $notifiable): array`**: Khai báo các kênh sẽ sử dụng. Ví dụ: `['mail', 'broadcast']`.
- **`toMail(object $notifiable): MailMessage`**: Xây dựng nội dung email. Sử dụng `match ($this->action)` để tùy biến chủ đề và nội dung cho từng loại thông báo.
- **`toBroadcast(object $notifiable): BroadcastMessage`**: Định dạng dữ liệu sẽ được gửi đi qua kênh real-time (Reverb).
- **`broadcastType(): string`**: Tùy chỉnh tên sự kiện mà Frontend sẽ lắng nghe. Ví dụ: `'JobEvent'`.

---

## 7. Tác vụ nền & Lập lịch (Queues & Scheduler)

### 7.1. Xử lý bất đồng bộ (Queues)

- `JobActionHandler` implement `ShouldQueue`, do đó việc gửi email/notification sẽ được đẩy vào hàng đợi (queue) và xử lý bởi một worker của Horizon. Điều này giúp request của người dùng được phản hồi ngay lập tức.

### 7.2. Tác vụ định kỳ (Scheduler)

Module `Jobs` sử dụng Laravel Scheduler để tự động chạy các tác vụ định kỳ, cụ thể là gửi thông báo nhắc nhở các công việc sắp đến hạn.

#### Command `SendTaskDueReminders`
- **Vị trí:** `app/Console/Commands/SendTaskDueReminders.php`
- **Signature:** `tasks:send-reminders`
- **Mô tả:** Quét và gửi thông báo nhắc nhở cho các công việc sắp tới hạn trong vòng 15 phút tới.
- **Logic hoạt động:**
    1.  Lệnh được trigger bởi `Kernel.php`.
    2.  Truy vấn vào model `ITJob` để tìm các công việc thỏa mãn tất cả các điều kiện sau:
        -   Chưa được thông báo (`is_notified = false`).
        -   Hạn chót (`due_at`) nằm trong khoảng từ **bây giờ** đến **bây giờ + 15 phút**.
        -   Trạng thái (`status`) không phải là `completed` (đã hoàn thành) hoặc `archived` (lưu trữ).
    3.  Với mỗi công việc tìm thấy, command sẽ:
        -   Phát ra một sự kiện `JobUpdatedEvent` với action là `'task-reminder'`. Payload của sự kiện chứa thông tin cần thiết để hiển thị thông báo (ID, tiêu đề, tin nhắn).
        -   Cập nhật lại trạng thái `is_notified = true` cho công việc đó để đảm bảo không gửi nhắc nhở lặp lại.
    4.  `JobActionHandler` sẽ lắng nghe sự kiện này và kích hoạt `JobNotification` để gửi thông báo real-time và/hoặc email đến người dùng liên quan.

#### Đăng ký trong Kernel
Để command `tasks:send-reminders` có thể tự động chạy, nó cần được đăng ký trong file `app/Console/Kernel.php`.

```php
// app/Console/Kernel.php
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Chạy tác vụ nhắc nhở mỗi 15 phút
        $schedule->command('tasks:send-reminders')->everyFifteenMinutes();
    }
    // ...
}
```

---

## 8. API & Permissions

- **Controller:** `JobsController` (chưa có trong context nhưng theo quy ước sẽ có) sẽ gọi các phương thức từ `JobService`.
- **Requests:** Các `FormRequest` riêng cho `store`, `update`, `bulk`... để validate dữ liệu đầu vào.
- **Resources:** `JobResource` và `JobCollection` để chuẩn hóa dữ liệu đầu ra của API.
- **Permissions:** Các quyền hạn được định nghĩa trong `database/seeders/PermissionSeeder.php` cho resource `jobs`, bao gồm: `stats`, `index`, `show`, `store`, `update`, `destroy`, `bulkDestroy`, `bulkUpdateStatus`, `export`, `import`.

---

## 9. Cấu trúc file trong module

```text
app/Modules/Jobs/
├── Console/
│   └── Commands/
│       └── SendTaskDueReminders.php
├── Enums/
│   └── JobStatusEnum.php
├── Events/
│   └── JobUpdatedEvent.php
├── Exports/
│   └── JobsExport.php
├── Imports/
│   └── JobsImport.php
├── Listeners/
│   └── JobActionHandler.php
├── Models/
│   └── ITJob.php
├── Notifications/
│   └── JobNotification.php
├── Providers/
│   └── (Nếu có ServiceProvider riêng cho module)
├── Requests/
│   ├── StoreJobRequest.php
│   └── UpdateJobRequest.php
├── Resources/
│   ├── JobResource.php
│   └── JobCollection.php
├── Routes/
│   └── jobs.php
├── Services/
│   └── JobService.php
└── JobsController.php
```

---

## 10. Kết luận

Việc xây dựng module `Jobs` theo kiến trúc này mang lại nhiều lợi ích:

- **Tách bạch trách nhiệm (Separation of Concerns):** Controller gọn nhẹ, Service xử lý nghiệp vụ, Listener xử lý tác vụ phụ, Notification định nghĩa thông báo.
- **Khả năng mở rộng:** Dễ dàng thêm các hành động mới vào `switch-case` trong Listener hoặc thêm các kênh thông báo mới (SMS, Slack) trong Notification mà không ảnh hưởng đến logic chính.
- **Hiệu năng cao:** Các tác vụ chậm được đưa vào hàng đợi, giúp cải thiện trải nghiệm người dùng.
- **Dễ bảo trì:** Code được tổ chức rõ ràng theo từng module và từng lớp chức năng, giúp việc tìm kiếm, sửa lỗi và nâng cấp trở nên đơn giản hơn.

Đây là một mô hình vững chắc để áp dụng cho các module có nghiệp vụ phức tạp và cần tương tác với người dùng qua nhiều kênh trong tương lai.
