<?php

namespace App\Providers;

use App\Modules\Jobs\Events\JobUpdatedEvent;
use App\Modules\Jobs\Listeners\JobActionHandler;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super Admin có toàn quyền trên toàn hệ thống (bỏ qua mọi kiểm tra permission)
        Gate::before(function ($user, $ability) {
            if ($user && (
                $user->hasRole('Super Admin') ||
                ($user->is_super_admin ?? false) ||
                (is_callable([$user, 'hasPermissionTo']) && $user->hasRole('Admin'))
            )) {
                return true;
            }
            return null;
        });

        // Đăng ký Event và Listener tại đây
        Event::listen(
            JobUpdatedEvent::class,
            JobActionHandler::class,
        );
    }
}
