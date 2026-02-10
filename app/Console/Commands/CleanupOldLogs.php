<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class CleanupOldLogs extends Command
{
    protected $signature = 'app:cleanup-old-logs';
    protected $description = 'Delete log files older than 2 days, keeping only today and yesterday';

    public function handle()
    {
        $logPath = storage_path('logs');
        $cutoff = Carbon::today()->subDays(2);
        $deleted = 0;

        foreach (File::glob($logPath . '/laravel-*.log') as $file) {
            // Extract date from filename: laravel-YYYY-MM-DD.log
            if (preg_match('/laravel-(\d{4}-\d{2}-\d{2})\.log$/', $file, $matches)) {
                $fileDate = Carbon::parse($matches[1]);

                if ($fileDate->lt($cutoff)) {
                    File::delete($file);
                    $deleted++;
                }
            }
        }

        if ($deleted > 0) {
            Log::info("Log cleanup: deleted {$deleted} old log files.");
            $this->info("Deleted {$deleted} old log files.");
        } else {
            $this->info("No old log files to delete.");
        }
    }
}
