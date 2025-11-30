<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // 🔄 AUTO-SYNC: Jalankan sinkronisasi status blockchain setiap 3 menit
        $schedule->command('blockchain:sync-status')
                 ->everyThreeMinutes()
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->appendOutputTo(storage_path('logs/auto-sync.log'));

        // 🚀 AUTO-COMPLETE: Otomatis complete Step 5 setiap 2 menit
        $schedule->command('certificates:auto-complete')
                 ->everyTwoMinutes()
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->appendOutputTo(storage_path('logs/auto-complete.log'));
                 
        // 🔧 AUTO-FIX: Perbaiki workflow yang incomplete setiap 10 menit
        $schedule->command('blockchain:auto-sync --fix-incomplete')
                 ->everyTenMinutes()
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->appendOutputTo(storage_path('logs/auto-fix.log'));
                 
        // 📊 CLEANUP: Bersihkan log lama setiap hari
        $schedule->command('log:clear --days=7')
                 ->daily()
                 ->at('02:00');

        // ✅ AUTO-SYNC: Sync certificates dari CouchDB setiap 1 menit
        $schedule->command('blockchain:sync-certificates')
                 ->everyMinute()
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->appendOutputTo(storage_path('logs/blockchain-sync.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}