<?php

namespace App\Services\Operations;

use App\Models\BranchInstallation;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\MultiBranch\Install;
use App\Services\Security\TwoFactor;
use App\Services\Sync\SyncState;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

/**
 * The go-live check: the pilot checklist's "before go-live" items that the server can verify about itself.
 * Used by `php artisan vitemart:preflight` and the Go-live check page.
 *
 * Each check: ['group', 'key', 'label', 'status' => ok|warn|fail|manual, 'detail', 'fix'].
 *   fail   – must be fixed before trading on this setup
 *   warn   – works, but look at it
 *   manual – the server can't tell; a person ticks it off
 */
class ReadinessService
{
    protected array $checks = [];

    public function run(): array
    {
        $this->checks = [];
        Install::refresh();
        $this->server();
        $this->dataSafety();
        Install::isCentral() ? $this->headOffice() : $this->branch();
        $this->catalogue();
        $this->peopleAndTills();
        return $this->checks;
    }

    public function summary(array $checks): array
    {
        $c = collect($checks)->countBy('status');
        return ['ok' => $c['ok'] ?? 0, 'warn' => $c['warn'] ?? 0, 'fail' => $c['fail'] ?? 0, 'manual' => $c['manual'] ?? 0,
            'ready' => ($c['fail'] ?? 0) === 0];
    }

    protected function add(string $group, string $key, string $label, string $status, string $detail = '', string $fix = ''): void
    {
        $this->checks[] = compact('group', 'key', 'label', 'status', 'detail', 'fix');
    }

    protected function ago(?string $iso): ?Carbon
    {
        try {
            return $iso ? Carbon::parse($iso) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    // ------------------------------------------------------------------ server

    protected function server(): void
    {
        $g = 'Server';
        $mode = Install::mode();
        $this->add($g, 'mode', 'Role of this server', in_array($mode, ['central', 'branch'], true) ? 'ok' : 'fail',
            match ($mode) {
                'central' => 'Head office', 'branch' => 'Branch ' . Install::branchCode() . ' – ' . (Install::branch()?->name ?? ''),
                default => 'Single store – not part of the multi-branch setup yet',
            },
            'Head office: php artisan vitemart:install --mode=central · Store: php artisan vitemart:install --mode=branch');

        $debug = (bool) config('app.debug');
        $this->add($g, 'debug', 'Error details hidden from users', $debug ? 'fail' : 'ok',
            $debug ? 'APP_DEBUG is on – a crash shows code and settings to whoever is at the screen' : 'APP_DEBUG is off',
            'Set APP_DEBUG=false in .env (System configuration → App).');

        $env = config('app.env');
        $this->add($g, 'env', 'Running as production', $env === 'production' ? 'ok' : 'warn', "APP_ENV={$env}", 'Set APP_ENV=production in .env.');

        $this->add($g, 'key', 'Encryption key set', config('app.key') ? 'ok' : 'fail', '', 'php artisan key:generate (once – never change it afterwards).');

        $url = (string) config('app.url');
        if (Install::isCentral()) {
            $this->add($g, 'https', 'Head office address uses HTTPS', str_starts_with($url, 'https://') ? 'ok' : 'fail', $url ?: 'APP_URL not set',
                'Put a certificate on the head-office domain (e.g. Let\'s Encrypt) and set APP_URL=https://…');
        } else {
            $this->add($g, 'url', 'Address tills use', $url ? 'ok' : 'warn', $url ?: 'APP_URL not set', 'Set APP_URL to the address the tills open (System configuration → Server).');
        }

        $last = $this->ago(Cache::get('vm_scheduler_last_run'));
        $this->add($g, 'scheduler', 'Background jobs running (scheduler)', $last && $last->gt(now()->subMinutes(3)) ? 'ok' : 'fail',
            $last ? 'Last run ' . $last->diffForHumans() : 'Never seen running',
            'Add one cron entry: * * * * * cd ' . base_path() . ' && php artisan schedule:run  (Windows: Task Scheduler every minute). Sync, heartbeat and backups depend on it.');

        $free = @disk_free_space(base_path());
        if ($free !== false) {
            $gb = $free / 1073741824;
            $this->add($g, 'disk', 'Free disk space', $gb < 1 ? 'fail' : ($gb < 5 ? 'warn' : 'ok'), number_format($gb, 1) . ' GB free', 'Free up space – backups and the database grow every day.');
        }

        $writable = is_writable(storage_path()) && is_writable(storage_path('framework')) && is_writable(storage_path('logs'));
        $this->add($g, 'storage', 'storage/ folder writable', $writable ? 'ok' : 'fail', '', 'Give the web server user write access to storage/ and bootstrap/cache/.');

        $tz = config('app.timezone');
        $this->add($g, 'timezone', 'Time zone', $tz === 'UTC' ? 'warn' : 'ok', $tz . ' · now ' . now()->format('d M H:i'),
            'Set APP_TIMEZONE (e.g. Africa/Lagos) so receipts and reports show local time.');
    }

    // ------------------------------------------------------------------ backups

    protected function dataSafety(): void
    {
        $g = 'Backups';
        $c = config('database.connections.' . config('database.default'));
        $dir = $c['dump']['dump_binary_path'] ?? env('DB_DUMP_PATH');
        $bin = $dir ? rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'mysqldump' : 'mysqldump';
        try {
            $r = Process::timeout(10)->run([$bin, '--version']);
            $ok = $r->successful();
            $detail = $ok ? trim(strtok($r->output(), "\n")) : trim($r->errorOutput());
        } catch (\Throwable $e) {
            $ok = false;
            $detail = $e->getMessage();
        }
        $this->add($g, 'mysqldump', 'Backup tool found (mysqldump)', $ok ? 'ok' : 'fail', mb_substr($detail, 0, 160),
            'Set DB_DUMP_PATH in .env to the folder holding mysqldump (XAMPP on Mac: /Applications/XAMPP/xamppfiles/bin; Windows: C:\\xampp\\mysql\\bin).');

        if (!Schema::hasTable('backup_runs')) {
            $this->add($g, 'backup', 'Recent backup', 'fail', 'Backups table missing', 'php artisan migrate');
            return;
        }
        $last = DB::table('backup_runs')->where('status', 'ok')->whereNotIn('kind', ['restored'])->max('finished_at');
        $last = $last ? Carbon::parse($last) : null;
        $this->add($g, 'backup', 'Recent backup', !$last ? 'fail' : ($last->lt(now()->subHours(26)) ? 'warn' : 'ok'),
            $last ? 'Last good backup ' . $last->diffForHumans() : 'No backup taken on this server yet',
            'Sync page → Back up now, or php artisan vitemart:backup. The nightly backup runs at ' . env('VITEMART_BACKUP_AT', '23:40') . '.');

        $failed = DB::table('backup_runs')->where('status', 'failed')->where('created_at', '>=', now()->subDays(2))->count();
        if ($failed) {
            $this->add($g, 'backup_failed', 'Backups failing', 'warn', "{$failed} failed in the last 2 days",
                'Run php artisan vitemart:backup to see the error.');
        }

        $drill = DB::table('backup_runs')->where('kind', 'restored')->exists();
        $this->add($g, 'restore_drill', 'Restore tried once', $drill ? 'ok' : 'manual',
            $drill ? 'A restore has been done on this server' : 'Not on this server – do it on a spare machine or a copy',
            'Copy a backup to a spare machine and run php artisan vitemart:restore FILE there.');

        $this->add($g, 'offsite', 'Backups copied off this machine', 'manual', 'Folder: ' . storage_path('app/vitemart-backups'),
            'Copy that folder to a USB disk or cloud folder (daily). A backup on the same disk doesn\'t survive a disk failure.');
    }

    // ------------------------------------------------------------------ head office

    protected function headOffice(): void
    {
        $g = 'Branches';
        $org = Install::organization();
        $vatOk = $org && abs((float) $org->vat_rate - 7.5) < 0.001 && $org->prices_include_tax;
        $this->add($g, 'vat', 'VAT: 7.5 %, prices include VAT', $vatOk ? 'ok' : 'warn',
            $org ? number_format((float) $org->vat_rate, 2) . ' %, prices ' . ($org->prices_include_tax ? 'include' : 'exclude') . ' VAT' : 'No organisation record',
            'Set on the organisation (Branches page). Branches receive it when they enrol.');

        $insts = BranchInstallation::with('branch')->where('status', 'active')->get();
        $this->add($g, 'enrolled', 'Branch servers enrolled', $insts->isEmpty() ? 'warn' : 'ok',
            $insts->isEmpty() ? 'None yet' : $insts->map(fn ($i) => $i->branch?->code)->filter()->implode(', '),
            'Branches → open the branch → Issue enrollment code; run vitemart:install --mode=branch on the store server.');

        foreach ($insts as $i) {
            $code = $i->branch?->code ?? '?';
            $seen = $i->last_seen_at;
            $this->add($g, "online_{$code}", "{$code}: online", $seen && $seen->gt(now()->subMinutes(10)) ? 'ok' : 'warn',
                $seen ? 'Last heard from ' . $seen->diffForHumans() . ($i->app_version ? ' · v' . $i->app_version : '') : 'Never heard from',
                'Check the store server is on, its scheduler runs, and it can reach ' . config('app.url') . '.');
            if (version_compare((string) $i->app_version, (string) config('vitemart.min_branch_version'), '<')) {
                $this->add($g, "version_{$code}", "{$code}: version", 'fail', 'v' . ($i->app_version ?: '?') . ' – head office needs ' . config('vitemart.min_branch_version'),
                    'Upgrade the branch: php artisan vitemart:upgrade');
            }
            $backup = $i->last_backup_at;
            $this->add($g, "backup_{$code}", "{$code}: recent backup", $backup && $backup->gt(now()->subDays(2)) ? 'ok' : 'warn',
                $backup ? 'Last backup ' . $backup->diffForHumans() : 'No backup reported', 'On the branch: Sync page → Back up now.');
        }

        $failed = DB::table('sync_inbox')->where('status', 'failed')->count();
        $this->add($g, 'inbox', 'Branch data all applied', $failed ? 'warn' : 'ok', $failed ? "{$failed} change(s) waiting to be applied" : 'Nothing stuck',
            'Sync page → "Could not be applied here" shows why.');

        $policies = Schema::hasTable('field_policies') ? DB::table('field_policies')->count() : 0;
        $this->add($g, 'policies', 'What branches may change is decided', $policies ? 'ok' : 'warn', "{$policies} field rule(s)",
            'Catalogue → Field policies: decide which product fields a branch may change.');
    }

    // ------------------------------------------------------------------ branch

    protected function branch(): void
    {
        $g = 'Head office link';
        if (!Install::isBranch()) {
            return;
        }
        $i = Install::current();
        $this->add($g, 'enrolled', 'Enrolled with head office', $i?->enrolled_at ? 'ok' : 'fail',
            ($i?->central_url ?? '—') . ($i?->enrolled_at ? ' · since ' . $i->enrolled_at->format('d M Y') : ''), 'php artisan vitemart:install --mode=branch …');

        $contact = $this->ago(SystemSetting::get('last_hq_contact_at'));
        $this->add($g, 'contact', 'Head office reachable', $contact && $contact->gt(now()->subMinutes(10)) ? 'ok' : 'warn',
            $contact ? 'Last contact ' . $contact->diffForHumans() : 'No contact yet',
            'Check the internet link and that ' . ($i?->central_url ?? 'the head-office address') . ' opens from this machine. Sales carry on meanwhile.');

        $skew = SyncState::get('hq_clock_skew');
        if ($skew !== null) {
            $abs = abs((int) $skew);
            $this->add($g, 'clock', 'Clock matches head office', $abs > 240 ? 'fail' : ($abs > 60 ? 'warn' : 'ok'), "{$abs} s apart",
                'Turn on automatic time (NTP) on this machine – head office refuses requests more than 5 minutes off.');
        }

        $pending = DB::table('sync_outbox')->where('status', 'pending')->count();
        $dead = DB::table('sync_outbox')->where('status', 'dead')->count();
        $oldest = DB::table('sync_outbox')->where('status', 'pending')->min('created_at');
        $stale = $oldest && Carbon::parse($oldest)->lt(now()->subMinutes(30));
        $this->add($g, 'outbox', 'Sales reaching head office', $dead ? 'fail' : ($stale ? 'warn' : 'ok'),
            "{$pending} waiting" . ($oldest ? ' (oldest ' . Carbon::parse($oldest)->diffForHumans() . ')' : '') . ($dead ? " · {$dead} refused" : ''),
            'Sync page → "Not sent yet – problems".');

        $cursor = (int) SyncState::get('feed_cursor', 0);
        $head = (int) SyncState::get('feed_head', 0);
        $failures = count((array) SyncState::get('feed_failures', []));
        $this->add($g, 'catalogue', 'Catalogue downloaded', $cursor === 0 ? 'fail' : ($cursor < $head || $failures ? 'warn' : 'ok'),
            $cursor === 0 ? 'Nothing downloaded yet' : "position {$cursor} of {$head}" . ($failures ? " · {$failures} item(s) skipped" : ''),
            'Sync page → Sync now (the first download can take a few minutes).');

        $hq = SyncState::get('hq_release');
        if ($hq) {
            $this->add($g, 'version', 'Same version as head office', version_compare(config('vitemart.version'), $hq, '==') ? 'ok' : 'warn',
                'This server ' . config('vitemart.version') . ' · head office ' . $hq, 'Upgrade the older one (head office first): php artisan vitemart:upgrade');
        }
    }

    // ------------------------------------------------------------------ catalogue

    protected function catalogue(): void
    {
        $g = 'Catalogue';
        $active = DB::table('products')->where('is_active', true);
        $n = (clone $active)->count();
        $this->add($g, 'products', 'Products for sale', $n ? 'ok' : 'fail', number_format($n) . ' active', 'Add products (or wait for the first catalogue download).');

        $noPrice = (clone $active)->where(fn ($q) => $q->whereNull('price')->orWhere('price', '<=', 0))->count();
        $this->add($g, 'price', 'Every product has a price', $noPrice ? 'warn' : 'ok', $noPrice ? "{$noPrice} without a price" : '',
            'Products → filter by price 0, or Price control.');

        $below = (clone $active)->whereColumn('price', '<', 'cost_price')->where('price', '>', 0)->count();
        $this->add($g, 'below_cost', 'No product priced below cost', $below ? 'warn' : 'ok', $below ? "{$below} below cost" : '', 'Price control → sort by margin.');

        $noCode = (clone $active)->where(fn ($q) => $q->whereNull('barcode')->orWhere('barcode', ''))->count();
        $this->add($g, 'barcode', 'Products have barcodes', $noCode ? 'warn' : 'ok', $noCode ? "{$noCode} without a barcode – cashiers must search for them" : '',
            'Products → Generate barcode, or scan the pack\'s code into the product.');

        $dups = DB::table('products')->whereNotNull('barcode')->where('barcode', '!=', '')
            ->select('barcode')->groupBy('barcode')->havingRaw('COUNT(*) > 1')->pluck('barcode');
        $this->add($g, 'dup_barcode', 'No barcode used twice', $dups->isEmpty() ? 'ok' : 'fail',
            $dups->isEmpty() ? '' : $dups->count() . ' duplicated: ' . $dups->take(5)->implode(', '), 'Scanning a duplicated code rings up the wrong item – give one of each pair a new code.');

        if (Schema::hasTable('inventory_balances') && Install::branchId()) {
            $neg = DB::table('inventory_balances')->where('branch_id', Install::branchId())->where('on_hand', '<', 0)->count();
            $this->add($g, 'negative', 'No negative stock', $neg ? 'warn' : 'ok', $neg ? "{$neg} item(s) below zero" : '',
                'Do a stock count on those items (Stock counts → New).');
        }
    }

    // ------------------------------------------------------------------ people and tills

    protected function peopleAndTills(): void
    {
        $g = 'People & tills';
        if (Install::isMultiBranch()) {
            $roles = (array) config('vitemart.mfa_required_roles', []);
            $users = $roles ? User::role(array_values(array_filter($roles, fn ($r) => \Spatie\Permission\Models\Role::where('name', $r)->exists())))
                ->where('is_active', true)
                // on a branch: its own managers (head-office staff set theirs up at head office)
                ->when(Install::isBranch(), fn ($q) => $q->where(fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', Install::branchId())))
                ->get() : collect();
            $missing = $users->reject(fn ($u) => TwoFactor::enabled($u));
            $this->add($g, 'mfa', 'Two-step sign-in set up for managers', $missing->isEmpty() ? 'ok' : 'fail',
                $missing->isEmpty() ? $users->count() . ' manager account(s), all set up' : $missing->count() . ' not set up: ' . $missing->take(6)->map(fn ($u) => trim($u->first_name . ' ' . $u->last_name))->implode(', '),
                'Each of them signs in once and follows the set-up screen (an authenticator app on their phone).');
        }

        $admins = User::role('Super Admin')->where('is_active', true)->limit(25)->get();
        $weak = $admins->filter(fn ($u) => collect(['password', '12345678', 'admin123', 'password123'])->contains(fn ($p) => Hash::check($p, $u->password)));
        $this->add($g, 'default_password', 'No admin with a default password', $weak->isEmpty() ? 'ok' : 'fail',
            $weak->isEmpty() ? '' : $weak->map(fn ($u) => $u->email)->implode(', '), 'Sign in as them and change the password (top-right menu → Profile).');

        if (!Install::isCentral() && Schema::hasTable('cash_registers')) {
            $tills = \App\Models\CashRegister::here()->where('status', 'active')->count();
            $this->add($g, 'tills', 'Tills registered', $tills ? 'ok' : 'warn', "{$tills} till(s)",
                'POS → Tills: add one per counter so each shift and sale records its till.');

            $ref = (bool) SystemSetting::get('card_needs_terminal_ref', false);
            $this->add($g, 'card_ref', 'Card payments need the terminal reference', $ref ? 'ok' : 'manual',
                $ref ? 'Required' : 'Not required – decide whether cashiers must type the slip number',
                'Settings → POS → "Card needs terminal reference".');

            $pend = DB::table('order_payments')->where('status', 'pending')->where('created_at', '<', now()->subDay())->count();
            $this->add($g, 'pending_payments', 'Payments confirmed within a day', $pend ? 'warn' : 'ok', $pend ? "{$pend} older than a day" : '',
                'POS → Payments to confirm.');
        }

        if (Schema::hasTable('stock_transfers')) {
            $open = DB::table('stock_transfers')->where('status', 'received')->where('received_at', '<', now()->subDays(3))->count();
            $this->add($g, 'transfer_diffs', 'Transfer differences settled', $open ? 'warn' : 'ok', $open ? "{$open} transfer(s) with differences open over 3 days" : '',
                'Transfers → filter "Received" → settle each difference.');
        }
    }
}
