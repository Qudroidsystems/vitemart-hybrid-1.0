<?php

namespace App\Services\Operations;

use App\Services\AuditLogger;
use App\Services\MultiBranch\Install;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * vitemart:restore – put a backup back without losing what head office needs.
 *
 *   safety backup → maintenance on → load the backup → bring the schema up to date → recalculate stock
 *   → (branch) everything the backup still had waiting is sent again; head office ignores what it already has
 *   → maintenance off
 *
 * Sales made at a branch after the backup was taken are gone from the branch, but head office already has
 * every one that was synced – they stay in head-office reports.
 */
class RestoreService
{
    public function __construct(protected BackupService $backups)
    {
    }

    public function run(string $path, ?callable $out = null): array
    {
        $say = fn ($m) => $out ? $out($m) : null;
        $v = $this->backups->verify($path);
        if (!$v['ok']) {
            throw new \RuntimeException('This file does not look like a complete database backup.');
        }
        $say("Backup looks good ({$v['tables']} tables).");

        $say('Taking a safety backup of the current database first …');
        $safety = $this->backups->dump('pre-restore');
        $say('Safety backup: ' . basename($safety->path));

        $wasDown = app()->isDownForMaintenance();
        if (!$wasDown) {
            Artisan::call('down', ['--retry' => 60]);
        }
        try {
            $say('Loading the backup …');
            $this->backups->load($path);
            Artisan::call('migrate', ['--force' => true]);
            // keep a record of the safety copy in the restored database, so it can be found and restored too
            DB::table('backup_runs')->insert(['kind' => 'pre-restore', 'status' => 'ok', 'path' => $safety->path, 'size_bytes' => $safety->size_bytes,
                'app_version' => $safety->app_version, 'started_at' => $safety->started_at, 'finished_at' => $safety->finished_at,
                'created_at' => now(), 'updated_at' => now()]);
            $say('Database brought up to this version.');
            Artisan::call('optimize:clear');
            if (Schema::hasTable('inventory_balances')) {
                Artisan::call('vitemart:rebuild-stock');
                $say('Stock balances recalculated.');
            }
            $resend = 0;
            if (Install::isBranch() && Schema::hasTable('sync_outbox')) {
                // Everything still waiting in the backup goes again straight away (head office skips duplicates).
                $resend = DB::table('sync_outbox')->whereIn('status', ['pending', 'dead'])
                    ->update(['status' => 'pending', 'attempts' => 0, 'next_attempt_at' => null]);
                $say("{$resend} sync event(s) from the backup will be sent again.");
                // Never re-use a receipt / document number head office already has from after the backup.
                try {
                    $highest = app(\App\Services\MultiBranch\CentralClient::class)->quick()->get('/api/v1/sync/sequences')->throw()->json('highest');
                    $moved = \App\Services\MultiBranch\DocumentNumber::catchUp($highest);
                    $say('Document numbers checked with head office' . ($moved ? ' – ' . count($moved) . ' counter(s) moved forward.' : '.'));
                } catch (\Throwable $x) {
                    $moved = \App\Services\MultiBranch\DocumentNumber::catchUp(null);
                    $say('Head office not reachable – document numbers jumped ahead by 1,000 to be safe.');
                }
            }
            DB::table('backup_runs')->insert(['kind' => 'restored', 'status' => 'ok', 'path' => $path, 'size_bytes' => @filesize($path),
                'app_version' => config('vitemart.version'), 'started_at' => now(), 'finished_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            AuditLogger::log('restored', 'Database restored from ' . basename($path) . ' (safety copy ' . basename($safety->path) . ')', 'system');
        } catch (\Throwable $e) {
            $say('Restore failed: ' . $e->getMessage() . ' – putting the previous database back …');
            $this->backups->load($safety->path);
            if (!$wasDown) {
                Artisan::call('up');
            }
            throw $e;
        }
        if (!$wasDown) {
            Artisan::call('up');
        }
        $say('Done. The app is back up.');
        return ['safety' => $safety->path, 'resend' => $resend ?? 0];
    }
}
