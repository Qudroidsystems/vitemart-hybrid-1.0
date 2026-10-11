<?php

namespace App\Console\Commands;

use App\Services\MultiBranch\Install;
use App\Services\Sync\Feed;
use App\Services\Sync\Outbox;
use App\Services\Sync\SyncRunner;
use Illuminate\Console\Command;

class VitemartSync extends Command
{
    protected $signature = 'vitemart:sync
        {--snapshot : Head office: put the whole catalogue, staff, customers and settings into the feed}
        {--backfill : Branch: queue the history this branch already has (first connection)}
        {--since= : With --backfill, only sales / stock from this date (YYYY-MM-DD)}';

    protected $description = 'Branch: send sales & stock to head office and download catalogue changes. Head office: prepare the change feed.';

    public function handle(SyncRunner $runner): int
    {
        if (Install::isCentral()) {
            if ($this->option('snapshot')) {
                $this->info('Marked ' . Feed::snapshot() . ' records for the feed.');
            }
            $total = 0;
            do {
                $n = Feed::build();
                $total += $n;
            } while ($n >= 5000);
            $this->info("Feed: {$total} change(s) prepared.");
            // the "already applied" log only needs to outlive a branch's retries
            \Illuminate\Support\Facades\DB::table('sync_inbox')->where('status', 'applied')->where('updated_at', '<', now()->subDays(30))->limit(20000)->delete();
            return self::SUCCESS;
        }

        if (!Install::isBranch()) {
            $this->line('This is a single-store installation – nothing to sync.');
            return self::SUCCESS;
        }

        if ($this->option('backfill')) {
            $n = Outbox::backfill($this->option('since'), fn ($e, $c) => $this->line("  queued {$e} (total {$c})"));
            $this->info("{$n} records queued for head office.");
        }

        try {
            $r = $runner->run();
        } catch (\Throwable $e) {
            $this->warn('Head office unreachable: ' . $e->getMessage());
            return self::FAILURE;
        }
        if (!empty($r['busy'])) {
            $this->line('Another sync is running.');
            return self::SUCCESS;
        }
        $this->info(sprintf('Sent %d, failed %d · applied %d change(s), %d error(s) · %d command(s).',
            $r['sent'] ?? 0, $r['failed'] ?? 0, $r['applied'] ?? 0, $r['errors'] ?? 0, $r['commands'] ?? 0));
        return self::SUCCESS;
    }
}
