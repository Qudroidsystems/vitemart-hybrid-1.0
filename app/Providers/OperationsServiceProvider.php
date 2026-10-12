<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

/** Records the nightly backup:run results in backup_runs (shown on the Sync page and reported to head office). */
class OperationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $record = function (string $status, ?string $path, ?string $error) {
            if (!Schema::hasTable('backup_runs')) {
                return;
            }
            DB::table('backup_runs')->insert([
                'kind' => 'scheduled', 'status' => $status, 'path' => $path, 'size_bytes' => $path && is_file($path) ? filesize($path) : null,
                'app_version' => config('vitemart.version'), 'error' => $error ? mb_substr($error, 0, 2000) : null,
                'started_at' => now(), 'finished_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        };
        if (class_exists(\Spatie\Backup\Events\BackupWasSuccessful::class)) {
            Event::listen(\Spatie\Backup\Events\BackupWasSuccessful::class, function ($e) use ($record) {
                $path = null;
                try {
                    $dest = $e->backupDestination ?? null;
                    $newest = $dest?->newestBackup();
                    $path = $newest ? $dest->disk()->path($newest->path()) : null;
                } catch (\Throwable $x) {
                }
                $record('ok', $path, null);
            });
            Event::listen(\Spatie\Backup\Events\BackupHasFailed::class, fn ($e) => $record('failed', null, $e->exception?->getMessage()));
        }
    }
}
