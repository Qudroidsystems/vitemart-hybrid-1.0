<?php

namespace App\Services\Operations;

use App\Services\AuditLogger;
use App\Services\MultiBranch\Install;
use App\Services\Sync\SyncState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

/**
 * vitemart:upgrade – apply a new release's database changes safely.
 *
 *   1. check versions (a branch must not run ahead of head office)
 *   2. back up the database
 *   3. maintenance mode on
 *   4. run the new migrations
 *   5. check the app still starts and the main tables answer
 *   6. maintenance mode off
 *
 * Anything fails after step 2 → the backup is loaded back, the app comes back up on the old database,
 * and the run is recorded as rolled back with the reason.
 */
class UpgradeService
{
    protected array $log = [];

    public function __construct(protected BackupService $backups)
    {
    }

    protected function say(string $line, ?callable $out = null): void
    {
        $this->log[] = now()->format('H:i:s') . '  ' . $line;
        if ($out) {
            $out($line);
        }
    }

    /** Migrations not run yet. */
    public function pending(?string $path = null): array
    {
        $ran = DB::table('migrations')->pluck('migration')->flip();
        $dirs = $path ? [$path] : [database_path('migrations')];
        $files = [];
        foreach ($dirs as $d) {
            foreach (glob(rtrim($d, '/') . '/*.php') as $f) {
                $files[] = basename($f, '.php');
            }
        }
        sort($files);
        return array_values(array_filter($files, fn ($m) => !isset($ran[$m])));
    }

    /** Can this server be upgraded now?  Returns a reason when not. */
    public function blocker(bool $force = false): ?string
    {
        if (!Install::isBranch() || $force) {
            return null;
        }
        $hq = SyncState::get('hq_release');
        if ($hq && version_compare(config('vitemart.version'), $hq, '>')) {
            return "Head office runs {$hq}; this branch would run " . config('vitemart.version')
                . '. Upgrade head office first (or use --force).';
        }
        if (\Illuminate\Support\Facades\DB::table('sync_outbox')->where('status', 'pending')->count() > 5000) {
            return 'More than 5,000 sync events are waiting to be sent. Let sync catch up first (or use --force).';
        }
        return null;
    }

    /**
     * @param array{path?:string, skip_backup?:bool, force?:bool, check_boot?:bool} $opt
     */
    public function run(array $opt = [], ?callable $out = null): array
    {
        $this->log = [];
        $from = DB::table('upgrade_runs')->where('status', 'ok')->orderByDesc('id')->value('to_version');
        $to = config('vitemart.version');
        $runId = DB::table('upgrade_runs')->insertGetId(['from_version' => $from, 'to_version' => $to, 'status' => 'running',
            'started_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        if ($why = $this->blocker($opt['force'] ?? false)) {
            $this->finish($runId, 'failed', $why, $out);
            return ['status' => 'failed', 'message' => $why, 'log' => $this->log];
        }

        $pending = $this->pending($opt['path'] ?? null);
        $this->say(count($pending) . ' database change(s) to apply' . ($pending ? ': ' . implode(', ', $pending) : '.'), $out);

        $backup = null;
        if (empty($opt['skip_backup'])) {
            $this->say('Backing up the database …', $out);
            $backup = $this->backups->dump('pre-upgrade');
            $this->say('Backup saved: ' . basename($backup->path) . ' (' . number_format($backup->size_bytes / 1048576, 1) . ' MB)', $out);
            DB::table('upgrade_runs')->where('id', $runId)->update(['backup_run_id' => $backup->id]);
        }

        $wasDown = app()->isDownForMaintenance();
        try {
            if (!$wasDown) {
                Artisan::call('down', ['--retry' => 60, '--refresh' => 15]);
                $this->say('Maintenance mode on – tills show "back in a minute".', $out);
            }

            $args = ['--force' => true];
            if (!empty($opt['path'])) {
                $args['--path'] = $opt['path'];
                $args['--realpath'] = true;
            }
            $code = Artisan::call('migrate', $args);
            $this->say(trim(Artisan::output()) ?: 'Migrations run.', $out);
            if ($code !== 0) {
                throw new \RuntimeException('migrate returned ' . $code);
            }
            Artisan::call('optimize:clear');

            // Health checks
            foreach (['users', 'products', 'orders', 'stocks', 'installation'] as $t) {
                if (!Schema::hasTable($t)) {
                    throw new \RuntimeException("Table {$t} is missing after the upgrade.");
                }
                DB::table($t)->limit(1)->get();
            }
            if ($opt['check_boot'] ?? true) {
                $boot = Process::path(base_path())->timeout(120)->run([PHP_BINARY, 'artisan', 'vitemart:status', '--no-interaction']);
                if (!$boot->successful() && !str_contains($boot->output(), 'Mode')) {
                    throw new \RuntimeException('The app does not start after the upgrade: ' . mb_substr($boot->errorOutput() ?: $boot->output(), 0, 800));
                }
            }
            $this->say('Checks passed – the app starts and the main tables answer.', $out);

            if (!$wasDown) {
                Artisan::call('up');
            }
            $this->finish($runId, 'ok', "Upgraded to {$to}.", $out);
            AuditLogger::log('upgraded', "Upgraded to {$to}" . ($pending ? ' (' . count($pending) . ' database changes)' : ''), 'system');
            return ['status' => 'ok', 'message' => "Upgraded to {$to}.", 'log' => $this->log];
        } catch (\Throwable $e) {
            $this->say('FAILED: ' . $e->getMessage(), $out);
            $status = 'failed';
            if ($backup) {
                $this->say('Putting the database back as it was before the upgrade …', $out);
                try {
                    $log = $this->log;
                    $this->backups->load($backup->path);
                    $this->log = $log;
                    // the dump was taken while its own row still said "running"
                    DB::table('backup_runs')->updateOrInsert(['id' => $backup->id], ['kind' => $backup->kind, 'status' => 'ok', 'path' => $backup->path,
                        'size_bytes' => $backup->size_bytes, 'app_version' => $backup->app_version, 'started_at' => $backup->started_at,
                        'finished_at' => $backup->finished_at, 'created_at' => $backup->created_at, 'updated_at' => now()]);
                    Artisan::call('optimize:clear');
                    $status = 'rolled_back';
                    $this->say('Database restored from ' . basename($backup->path) . '.', $out);
                } catch (\Throwable $r) {
                    $this->say('Could not restore automatically: ' . $r->getMessage() . ' – restore ' . basename($backup->path) . ' with `php artisan vitemart:restore`.', $out);
                }
            }
            if (!$wasDown) {
                Artisan::call('up');
            }
            // the restore replaced upgrade_runs: record this run again
            $runId = $this->ensureRun($runId, $from, $to, $backup?->id);
            $this->finish($runId, $status, $e->getMessage(), $out);
            return ['status' => $status, 'message' => $e->getMessage(), 'log' => $this->log];
        }
    }

    protected function ensureRun(int $id, ?string $from, ?string $to, ?int $backupId): int
    {
        if (!Schema::hasTable('upgrade_runs')) {
            return 0;
        }
        if (DB::table('upgrade_runs')->where('id', $id)->exists()) {
            DB::table('upgrade_runs')->where('id', $id)->update(['backup_run_id' => $backupId]);
            return $id;
        }
        return DB::table('upgrade_runs')->insertGetId(['from_version' => $from, 'to_version' => $to, 'status' => 'running',
            'backup_run_id' => $backupId, 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function finish(int $id, string $status, string $message, ?callable $out): void
    {
        $this->say(match ($status) {
            'ok' => '✔ ' . $message,
            'rolled_back' => '↩ Rolled back: ' . $message,
            default => '✖ ' . $message,
        }, $out);
        if ($id && Schema::hasTable('upgrade_runs')) {
            DB::table('upgrade_runs')->where('id', $id)->update(['status' => $status, 'log' => implode("\n", $this->log),
                'finished_at' => now(), 'updated_at' => now()]);
        }
    }
}
