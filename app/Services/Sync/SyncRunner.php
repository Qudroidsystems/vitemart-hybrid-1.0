<?php

namespace App\Services\Sync;

use App\Services\MultiBranch\CentralClient;
use App\Services\MultiBranch\Install;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Branch side: one sync round = push the outbox, pull the feed, run head office's commands.
 */
class SyncRunner
{
    public function __construct(protected FeedApplier $applier)
    {
    }

    public function run(bool $quick = false): array
    {
        if (!Install::isBranch()) {
            return ['skipped' => true];
        }
        $lock = cache()->lock('vitemart-sync', 300);
        if (!$lock->get()) {
            return ['busy' => true];
        }
        try {
            [$sent, $failed] = Outbox::push(20, $quick);
            $pulled = $this->pull($quick);
            SyncState::set('last_sync_at', now()->toIso8601String());
            SyncState::set('last_error', null);
            return ['sent' => $sent, 'failed' => $failed] + $pulled;
        } catch (\Throwable $e) {
            SyncState::set('last_error', ['at' => now()->toIso8601String(), 'message' => mb_substr($e->getMessage(), 0, 300)]);
            throw $e;
        } finally {
            $lock->release();
        }
    }

    public function pull(bool $quick = false, int $maxPages = 50): array
    {
        $client = app(CentralClient::class)->quick($quick);
        $applied = 0;
        $errors = 0;
        $commands = 0;
        for ($i = 0; $i < $maxPages; $i++) {
            $cursor = (int) SyncState::get('feed_cursor', 0);
            $res = $client->get('/api/v1/sync/pull', ['cursor' => $cursor, 'limit' => (int) config('vitemart.sync.pull_limit', 500)])->throw()->json();

            $stopAt = null;
            SyncContext::run(function () use ($res, &$applied, &$errors, &$stopAt) {
                RowCodec::flush();
                foreach ($res['changes'] ?? [] as $c) {
                    try {
                        DB::transaction(fn () => $this->applier->apply($c));
                        $applied++;
                    } catch (\Throwable $t) {
                        // Recorded and skipped – one bad record must never stop the branch getting the rest.
                        // (e.g. a branch price for an item not sold here).  "Download catalogue again" repairs gaps.
                        if (!$t instanceof MissingReference) {
                            report($t);
                        }
                        $errors++;
                        $failed = SyncState::get('feed_failures', []);
                        $failed[] = ['at' => now()->toIso8601String(), 'id' => $c['id'], 'entity' => $c['entity'], 'uid' => $c['uid'],
                            'message' => mb_substr($t->getMessage(), 0, 300)];
                        SyncState::set('feed_failures', array_slice($failed, -50));
                    }
                }
            });

            if ($stopAt !== null) {
                SyncState::set('feed_cursor', max($cursor, $stopAt - 1));
                $commands += $this->runCommands($res['commands'] ?? []);
                break;
            }
            SyncState::set('feed_cursor', (int) ($res['cursor'] ?? $cursor));
            SyncState::set('feed_head', (int) ($res['head'] ?? 0));
            SyncState::set('last_pull_at', now()->toIso8601String());
            $commands += $this->runCommands($res['commands'] ?? []);
            if (empty($res['has_more'])) {
                break;
            }
        }
        $this->reportCommands($client);
        return ['applied' => $applied, 'errors' => $errors, 'commands' => $commands];
    }

    // ------------------------------------------------------------------
    // Remote commands
    // ------------------------------------------------------------------

    public const COMMANDS = [
        'resync' => 'Download the whole catalogue again',
        'rebuild_stock' => 'Recalculate stock balances from the stock ledger',
        'resend_history' => 'Send sales and stock history again',
        'logout_all' => 'Sign everyone out at this branch',
        'retry_failed' => 'Retry events that failed to send',
        'diagnostics' => 'Report versions, queue sizes and disk space',
    ];

    protected function runCommands(array $commands): int
    {
        $n = 0;
        foreach ($commands as $c) {
            if (DB::table('sync_command_runs')->where('uid', $c['uid'])->exists()) {
                continue;
            }
            [$status, $result] = $this->execute($c);
            DB::table('sync_command_runs')->insert(['uid' => $c['uid'], 'command' => $c['command'], 'status' => $status,
                'result' => is_string($result) ? $result : json_encode($result), 'reported' => false, 'created_at' => now(), 'updated_at' => now()]);
            \App\Services\AuditLogger::log('sync_command', "Ran head-office command {$c['command']}: {$status}", 'sync');
            $n++;
        }
        return $n;
    }

    protected function execute(array $c): array
    {
        if (!empty($c['min_version']) && version_compare(config('vitemart.version'), $c['min_version'], '<')) {
            return ['refused', 'This branch runs ' . config('vitemart.version') . '; the command needs ' . $c['min_version'] . ' or later. Upgrade first.'];
        }
        try {
            switch ($c['command']) {
                case 'resync':
                    SyncState::set('feed_cursor', 0);
                    return ['done', 'Catalogue will be downloaded again on the next sync.'];
                case 'rebuild_stock':
                    Artisan::call('vitemart:rebuild-stock');
                    return ['done', trim(Artisan::output())];
                case 'resend_history':
                    $since = $c['payload']['since'] ?? null;
                    $n = Outbox::backfill($since);
                    return ['done', "{$n} records queued" . ($since ? " since {$since}" : '')];
                case 'logout_all':
                    $n = app(\App\Services\LoginPolicy::class)->logoutEveryone(null);
                    return ['done', "Everyone was signed out ({$n} sessions)."];
                case 'retry_failed':
                    $n = DB::table('sync_outbox')->where('status', 'dead')->update(['status' => 'pending', 'attempts' => 0, 'next_attempt_at' => null]);
                    return ['done', "{$n} events will be sent again."];
                case 'diagnostics':
                    return ['done', [
                        'version' => config('vitemart.version'), 'php' => PHP_VERSION, 'laravel' => app()->version(),
                        'outbox' => Outbox::counts(), 'cursor' => SyncState::get('feed_cursor'), 'disk_free_mb' => (int) (@disk_free_space(base_path()) / 1048576),
                        'time' => now()->toIso8601String(), 'last_errors' => array_slice(SyncState::get('feed_failures', []), -5),
                    ]];
                default:
                    return ['refused', 'Unknown command ' . $c['command']];
            }
        } catch (\Throwable $e) {
            return ['failed', mb_substr($e->getMessage(), 0, 500)];
        }
    }

    protected function reportCommands(CentralClient $client): void
    {
        foreach (DB::table('sync_command_runs')->where('reported', false)->limit(20)->get() as $r) {
            try {
                $res = $client->post('/api/v1/sync/commands/' . $r->uid, ['status' => $r->status, 'result' => $r->result]);
                if ($res->successful()) {
                    DB::table('sync_command_runs')->where('uid', $r->uid)->update(['reported' => true, 'updated_at' => now()]);
                }
            } catch (\Throwable $e) {
                return;
            }
        }
    }
}
