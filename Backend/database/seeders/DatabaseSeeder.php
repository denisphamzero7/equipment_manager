<?php

namespace Database\Seeders;

use App\Modules\Core\Models\User;
use App\Modules\Post\Models\Post;
use App\Modules\Post\Models\PostCategory;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Thứ tự: User → PostCategory (cây) → Post → Permission/Role/Team (phân quyền).
     */
    public function run(): void
    {
        $this->seedUsers();
        $this->seedPostCategories();
        $this->seedPosts();
        $this->call(PermissionSeeder::class);
        $this->call(SettingSeeder::class);

        // Seed dữ liệu Quản lý Khách sạn & Thiết bị
        $this->call([
            RoomTypeSeeder::class,
            EquipmentMasterSeeder::class,
            RoomStandardEquipmentSeeder::class,
            HotelRoomSeeder::class,
            RoomEquipmentSeeder::class,
            EquipmentMaintenanceLogSeeder::class,
        ]);
    }

    /**
     * Tạo user. User đầu tiên (id=1) dùng làm người tạo/sửa cho dữ liệu mẫu.
     */
    protected function seedUsers(): void
    {
        // Tạo tài khoản cố định để bạn dễ test đăng nhập
        $admin = User::firstOrCreate(
            ['email' => 'pnhau2001@gmail.com'],
            [
                'name' => 'Administrator',
                'user_name' => 'pnhau2001',
                'password' => \Illuminate\Support\Facades\Hash::make('12345'),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        $admin->update(['created_by' => $admin->id, 'updated_by' => $admin->id]);

        if (function_exists('fake')) {
            User::factory(5)->create();
            User::where('id', '>', 1)->update(['created_by' => $admin->id, 'updated_by' => $admin->id]);
        }
    }

    /**
     * Tạo danh mục tin tức dạng cây (parent_id): vài danh mục gốc, mỗi gốc có vài danh mục con.
     */
    protected function seedPostCategories(): void
    {
        if (! function_exists('fake')) {
            return;
        }

        $user = User::first();
        if (! $user) {
            return;
        }

        $rootNames = ['Tin công nghệ', 'Tin thể thao', 'Tin kinh tế', 'Giải trí', 'Giáo dục'];
        foreach ($rootNames as $index => $name) {
            PostCategory::factory()
                ->create([
                    'name' => $name,
                    'slug' => \Illuminate\Support\Str::slug($name),
                    'sort_order' => $index + 1,
                    'parent_id' => null,
                ]);
        }

        $roots = PostCategory::whereNull('parent_id')->orderBy('sort_order')->get();

        foreach ($roots as $root) {
            $childCount = rand(2, 3);
            for ($i = 0; $i < $childCount; $i++) {
                PostCategory::factory()->create([
                    'name' => $root->name.' - '.fake()->word(),
                    'slug' => \Illuminate\Support\Str::slug($root->name.' '.fake()->word()).'-'.uniqid(),
                    'sort_order' => $i + 1,
                    'parent_id' => $root->id,
                ]);
            }
        }

        PostCategory::whereNull('created_by')->update([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    /**
     * Tạo bài viết, gán ngẫu nhiên user và danh mục.
     */
    protected function seedPosts(): void
    {
        if (! function_exists('fake')) {
            return;
        }

        $users = User::all();
        $categories = PostCategory::all();

        if ($users->isEmpty()) {
            return;
        }

        Post::withoutEvents(function () use ($users, $categories) {
            Post::factory(20)
                ->sequence(
                    fn ($sequence) => [
                        'created_by' => $users->random()->id,
                        'updated_by' => $users->random()->id,
                    ]
                )
                ->create()
                ->each(function (Post $post) use ($categories) {
                    if ($categories->isNotEmpty()) {
                        $post->categories()->sync([$categories->random()->id]);
                    }
                });
        });
    }
}
