<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Backup all tenants daily at 2 AM
        $schedule->command('tenant:backup --compress --keep-days=30')
            ->dailyAt('02:00')
            ->timezone('Asia/Jakarta')
            ->emailOutputOnFailure(env('BACKUP_ALERT_EMAIL'))
            ->onSuccess(function () {
                Log::info('Scheduled tenant backup completed successfully');
            })
            ->onFailure(function () {
                Log::error('Scheduled tenant backup failed');
            });

        // Weekly backup with longer retention (every Sunday at 3 AM)
        $schedule->command('tenant:backup --compress --keep-days=90')
            ->weekly()
            ->sundays()
            ->at('03:00')
            ->timezone('Asia/Jakarta')
            ->emailOutputOnFailure(env('BACKUP_ALERT_EMAIL'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
