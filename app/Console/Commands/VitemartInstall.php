<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Installation;
use App\Models\Organization;
use App\Services\AuditLogger;
use App\Services\MultiBranch\CentralClient;
use App\Services\MultiBranch\Install;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time setup: turn this server into the headquarters (central) installation
 * or enroll it as a branch of an existing headquarters.
 *
 *   php artisan vitemart:install --mode=central --org="ViteMart Ltd"
 *   php artisan vitemart:install --mode=branch --central-url=https://hq.example.com --branch-code=IKJ --code=7KQ2-M9XA-PL3D
 */
class VitemartInstall extends Command
{
    protected $signature = 'vitemart:install
        {--mode= : central or branch}
        {--org= : organisation name (central)}
        {--central-url= : headquarters URL (branch)}
        {--branch-code= : this branch\'s code as created at HQ (branch)}
        {--code= : one-time enrollment code from HQ (branch)}
        {--force : allow changing an installation that already has a role}';

    protected $description = 'Set this server up as the headquarters or as an enrolled branch';

    public function handle(): int
    {
        $current = Install::refresh();
        $mode = $this->option('mode') ?: $this->choice('Install this server as', ['central', 'branch'], 1);

        if ($current && $current->mode !== 'standalone' && !$this->option('force')) {
            $this->error("This server is already set up as '{$current->mode}'. Changing its role is a controlled migration – re-run with --force only if you are sure.");
            return self::FAILURE;
        }

        return match ($mode) {
            'central' => $this->installCentral($current),
            'branch' => $this->installBranch($current),
            default => tap(self::FAILURE, fn () => $this->error('Mode must be central or branch.')),
        };
    }

    protected function installCentral(?Installation $current): int
    {
        $orgName = $this->option('org') ?: (Install::organization()?->name ?? $this->ask('Organisation name', 'ViteMart'));

        DB::transaction(function () use ($current, $orgName) {
            $org = Install::organization() ?? Organization::create(['name' => $orgName]);
            $org->update(['name' => $orgName]);

            // HQ warehouse = branch #0. If the default store row has no trading history, reuse it.
            $hq = Branch::where('number', 0)->first();
            if (!$hq) {
                $default = $current?->branch;
                $hasHistory = $default && (DB::table('orders')->where('branch_id', $default->id)->exists()
                    || DB::table('stocks')->where('branch_id', $default->id)->exists());
                if ($default && !$hasHistory) {
                    $default->update(['code' => 'HQ', 'number' => 0, 'name' => 'Head Office Warehouse', 'type' => 'warehouse']);
                    $hq = $default;
                } else {
                    $hq = Branch::create(['code' => 'HQ', 'number' => 0, 'name' => 'Head Office Warehouse', 'type' => 'warehouse']);
                }
            }

            $inst = $current ?? new Installation();
            $inst->fill([
                'mode' => 'central', 'organization_id' => $org->id, 'branch_id' => $hq->id,
                'central_url' => null, 'token' => null, 'hmac_secret' => null, 'enrolled_at' => now(),
                'app_version' => config('vitemart.version'),
            ])->save();

            // Products on HQ are centrally owned.
            DB::table('products')->update(['owner' => 'central', 'central_status' => 'published']);
            DB::table('stock_locations')->whereNull('branch_id')->update(['branch_id' => $hq->id]);
        });

        Install::refresh();
        $this->ensureLocations(Install::branch());
        AuditLogger::log('installed', 'Installed as headquarters (central)', 'branches');
        if (\Illuminate\Support\Facades\Schema::hasTable('sync_dirty')) {
            $n = \App\Services\Sync\Feed::snapshot();
            \App\Services\Sync\Feed::build(100000);
            $this->line("Catalogue feed prepared ({$n} records).");
        }
        $this->info('This server is now the HEADQUARTERS installation.');
        $this->line('Next: create branches under Branches → Add branch, then issue an enrollment code for each.');
        return self::SUCCESS;
    }

    protected function installBranch(?Installation $current): int
    {
        $url = $this->option('central-url') ?: $this->ask('Headquarters URL (https://…)');
        $branchCode = strtoupper($this->option('branch-code') ?: $this->ask('Branch code (as created at HQ)'));
        $code = $this->option('code') ?: $this->secret('Enrollment code');

        $this->info("Contacting headquarters at {$url} …");
        try {
            $res = CentralClient::enroll($url, [
                'code' => $code, 'branch_code' => $branchCode,
                'fingerprint' => gethostname() . '|' . php_uname('m'), 'app_version' => config('vitemart.version'),
            ]);
        } catch (\Throwable $e) {
            $this->error('Could not reach headquarters: ' . $e->getMessage());
            return self::FAILURE;
        }
        if (!$res->successful()) {
            $this->error('Headquarters refused the enrollment: ' . ($res->json('error.message') ?? $res->status()));
            return self::FAILURE;
        }
        $d = $res->json();

        DB::transaction(function () use ($current, $d, $url) {
            $org = Install::organization() ?? new Organization();
            $org->forceFill(array_merge($d['organization'] ?? [], ['uid' => $d['organization']['uid'] ?? $org->uid]))->save();

            // Adopt HQ's identity for this store, keeping our local id (all existing rows point at it).
            $b = $d['branch'];
            $local = $current?->branch ?? Branch::where('uid', $b['uid'])->first() ?? new Branch();
            Branch::where('code', $b['code'])->where('id', '!=', $local->id ?? 0)->update(['code' => $b['code'] . '-OLD']);
            Branch::where('number', $b['number'])->where('id', '!=', $local->id ?? 0)->update(['number' => 99]);
            $local->forceFill($b)->save();

            if (!empty($d['hq_branch']) && !Branch::where('uid', $d['hq_branch']['uid'])->exists()) {
                Branch::where('number', $d['hq_branch']['number'])->where('id', '!=', $local->id)->delete();
                Branch::forceCreate($d['hq_branch']);
            }

            $inst = $current ?? new Installation();
            $inst->fill([
                'mode' => 'branch', 'organization_id' => $org->id, 'branch_id' => $local->id,
                'central_url' => rtrim($url, '/'), 'remote_installation_uid' => $d['installation_uid'],
                'token' => $d['token'], 'hmac_secret' => $d['hmac_secret'], 'enrolled_at' => now(),
                'app_version' => config('vitemart.version'),
            ])->save();

            // Everything created here so far is a local (branch-owned) product until HQ publishes it.
            DB::table('products')->where('owner', '!=', 'central')->update(['origin_branch_id' => $local->id]);
        });

        Install::refresh();
        $this->ensureLocations(Install::branch());
        AuditLogger::log('installed', 'Enrolled as branch ' . Install::branchCode(), 'branches');
        if (\Illuminate\Support\Facades\Schema::hasTable('sync_outbox')) {
            $n = \App\Services\Sync\Outbox::backfill();
            $this->line("{$n} existing records queued to send to headquarters.");
        }
        $this->info('Enrolled as branch ' . Install::branchCode() . ' – ' . Install::branch()->name . '.');
        $this->line('Next: php artisan vitemart:sync (first sync), then schedule `php artisan schedule:run` every minute so sync keeps running.');
        return self::SUCCESS;
    }

    /** Every branch has a store location plus virtual transit and quarantine locations. */
    protected function ensureLocations(?Branch $branch): void
    {
        if (!$branch) {
            return;
        }
        app(\App\Services\MultiBranch\LocationService::class)->ensureFor($branch);
    }
}
