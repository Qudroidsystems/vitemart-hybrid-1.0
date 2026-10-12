<?php

namespace App\Console\Commands;

use App\Services\MultiBranch\CentralClient;
use App\Services\MultiBranch\Install;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VitemartHeartbeat extends Command
{
    protected $signature = 'vitemart:heartbeat';
    protected $description = 'Report this branch server\'s health to headquarters';

    public function handle(): int
    {
        if (!Install::isBranch()) {
            return self::SUCCESS;
        }
        $payload = [
            'app_version' => config('vitemart.version'),
            'disk_free_mb' => (int) (@disk_free_space(base_path()) / 1048576),
            'pending_events' => Schema::hasTable('sync_outbox') ? DB::table('sync_outbox')->where('status', 'pending')->count() : 0,
            'dead_letters' => Schema::hasTable('sync_outbox') ? DB::table('sync_outbox')->where('status', 'dead')->count() : 0,
            'last_backup_at' => Schema::hasTable('backup_runs') ? DB::table('backup_runs')->where('status', 'ok')->max('finished_at') : null,
        ];
        try {
            $res = app(CentralClient::class)->post('/api/v1/branch/heartbeat', $payload);
            if (!$res->successful()) {
                $this->warn('Heartbeat refused: ' . $res->status());
                return self::FAILURE;
            }
            \App\Models\SystemSetting::set('last_hq_contact_at', now()->toIso8601String());
            if ($rel = $res->json('latest_release')) {
                \App\Services\Sync\SyncState::set('hq_release', $rel); // vitemart:upgrade won't let a branch run ahead of head office
            }
        } catch (\Throwable $e) {
            $this->warn('HQ unreachable: ' . $e->getMessage());
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
