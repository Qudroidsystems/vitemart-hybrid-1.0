<?php

namespace App\Console\Commands;

use App\Models\BranchInstallation;
use App\Services\MultiBranch\CentralClient;
use App\Services\MultiBranch\Install;
use Illuminate\Console\Command;

class VitemartStatus extends Command
{
    protected $signature = 'vitemart:status';
    protected $description = 'Show this installation\'s role, branch and connection to headquarters';

    public function handle(): int
    {
        $i = Install::refresh();
        $this->table(['Setting', 'Value'], [
            ['Mode', Install::mode()],
            ['Organisation', Install::organization()?->name ?? '—'],
            ['Branch', Install::branch() ? Install::branchCode() . ' – ' . Install::branch()->name . ' (#' . Install::branchNumber() . ')' : '—'],
            ['HQ URL', $i?->central_url ?? '—'],
            ['Enrolled', $i?->enrolled_at?->toDateTimeString() ?? '—'],
            ['Version', config('vitemart.version')],
        ]);

        if (Install::isBranch()) {
            try {
                $res = app(CentralClient::class)->post('/api/v1/branch/heartbeat', ['app_version' => config('vitemart.version')]);
                $res->successful()
                    ? $this->info('Headquarters reachable – server time ' . $res->json('server_time'))
                    : $this->error('Headquarters answered ' . $res->status() . ': ' . ($res->json('error.message') ?? ''));
            } catch (\Throwable $e) {
                $this->warn('Headquarters unreachable: ' . $e->getMessage() . ' (sales continue locally).');
            }
        }
        if (Install::isCentral()) {
            $rows = BranchInstallation::with('branch')->where('status', 'active')->get()->map(fn ($b) => [
                $b->branch->code, $b->branch->name, $b->last_seen_at?->diffForHumans() ?? 'never', $b->app_version,
            ]);
            $this->table(['Branch', 'Name', 'Last seen', 'Version'], $rows);
        }
        return self::SUCCESS;
    }
}
