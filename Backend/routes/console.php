<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
Schedule::command('tasks:send-reminders')->everyMinute();
// Tự động dọn dẹp log truy cập cũ hơn 30 ngày hàng ngày vào ban đêm
Schedule::command('model:prune', ['--model' => [\App\Modules\Core\Models\LogActivity::class]])->daily();
// cron job, lịch trình
