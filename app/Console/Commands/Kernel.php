<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        // Add your custom commands here if they're not auto-discovered
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // ============================================
        // STOCK MANAGEMENT COMMANDS
        // ============================================

        // Test command - runs every 10 minutes
       $schedule->command('test:schedule')
        ->everyTenMinutes()
        ->appendOutputTo(storage_path('logs/scheduler-test.log'));

        // Run stock sync every 30 minutes during business hours (8 AM to 8 PM)
        $schedule->command('products:sync-stock')
            ->everyThirtyMinutes()
            ->weekdays() // Monday to Friday only
            ->between('8:00', '20:00') // 8 AM to 8 PM
            ->withoutOverlapping() // Prevent multiple instances
            ->appendOutputTo(storage_path('logs/stock-sync.log'))
            ->description('Sync product stocks from inventory transactions')
            ->runInBackground(); // Run in background

        // Run once daily for safety (overnight)
        $schedule->command('products:sync-stock')
            ->dailyAt('02:00') // 2 AM daily
            ->appendOutputTo(storage_path('logs/stock-sync-daily.log'))
            ->description('Daily overnight stock sync');

        // ============================================
        // DATABASE MAINTENANCE COMMANDS
        // ============================================

        // Backup database daily
        $schedule->command('backup:clean')->daily()->at('01:00');
        $schedule->command('backup:run')->daily()->at('01:30');

        // Clear application cache weekly
        $schedule->command('cache:clear')->weekly()->sundays()->at('03:00');

        // Clear expired password reset tokens daily
        $schedule->command('auth:clear-resets')->daily();

        // ============================================
        // APPLICATION SPECIFIC COMMANDS
        // ============================================

        // Send low stock notifications to admins (daily at 9 AM)
        $schedule->command('notify:low-stock')
            ->weekdays()
            ->at('09:00')
            ->appendOutputTo(storage_path('logs/low-stock-notifications.log'))
            ->description('Send low stock alert notifications');

        // Clean up old stock history (keep last 6 months)
        $schedule->command('inventory:cleanup --months=6')
            ->monthlyOn(1, '04:00') // 1st of month at 4 AM
            ->appendOutputTo(storage_path('logs/inventory-cleanup.log'))
            ->description('Clean up old inventory records');

        // ============================================
        // QUEUE MANAGEMENT
        // ============================================

        // Restart queue workers (if using queue)
        $schedule->command('queue:restart')
            ->hourly()
            ->withoutOverlapping();

        // Retry failed jobs
        $schedule->command('queue:retry all')
            ->dailyAt('05:00');

        // ============================================
        // MONITORING & HEALTH CHECKS
        // ============================================

        // Health check - runs every 15 minutes
        $schedule->command('health:check')
            ->everyFifteenMinutes()
            ->appendOutputTo(storage_path('logs/health-check.log'));

        // Test scheduler - runs every hour (for debugging)
        if (config('app.debug')) {
            $schedule->command('test:schedule')
                ->hourly()
                ->appendOutputTo(storage_path('logs/scheduler-test.log'))
                ->description('Test scheduler is working');
        }

        // ============================================
        // EMAIL MANAGEMENT
        // ============================================

        // Send queued emails
        $schedule->command('emails:send')
            ->everyMinute()
            ->withoutOverlapping();

        // Clean up sent emails
        $schedule->command('emails:cleanup --days=30')
            ->daily()
            ->at('00:30');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        // Load all commands from the Commands directory
        $this->load(__DIR__.'/Commands');

        // Load console routes
        require base_path('routes/console.php');
    }
}
