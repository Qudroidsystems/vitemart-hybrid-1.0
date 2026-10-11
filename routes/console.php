<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Multi-branch background work. Add ONE cron entry on every server:
|   * * * * * cd /path/to/vitemart && php artisan schedule:run >> /dev/null 2>&1
*/
Schedule::command('vitemart:heartbeat')->everyMinute()->withoutOverlapping();
