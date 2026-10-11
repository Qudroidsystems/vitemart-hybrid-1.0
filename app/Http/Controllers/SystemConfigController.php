<?php

namespace App\Http\Controllers;

use App\Models\Installation;
use App\Services\AuditLogger;
use App\Services\MultiBranch\CentralClient;
use App\Services\MultiBranch\Install;
use App\Support\EnvEditor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * System Configuration: everything an administrator needs to set this server up
 * without a terminal – network address, head office link, database, email,
 * app identity, performance options and maintenance tools.
 *
 * Values are written to .env (a backup is kept for every change), then the
 * config cache is cleared so they take effect on the next request.
 */
class SystemConfigController extends Controller
{
    public const TOOLS = [
        'clear_cache' => ['Clear all caches', 'optimize:clear', []],
        'cache_views' => ['Pre-compile pages', 'view:cache', []],
        'migrate' => ['Update database (migrations)', 'migrate', ['--force' => true]],
        'storage_link' => ['Fix image links (storage link)', 'storage:link', []],
        'rebuild_stock' => ['Recalculate stock balances', 'vitemart:rebuild-stock', []],
        'sync_now' => ['Sync with head office now', 'vitemart:sync', []],
        'hq_status' => ['Check head office connection', 'vitemart:status', []],
        'queue_work' => ['Process waiting background jobs', 'queue:work', ['--stop-when-empty' => true, '--max-time' => 60, '--tries' => 3]],
        'queue_retry' => ['Retry failed background jobs', 'queue:retry', ['id' => ['all']]],
        'backup_db' => ['Back up the database now', 'backup:run', ['--only-db' => true, '--disable-notifications' => true]],
    ];

    public function __construct()
    {
        $this->middleware('permission:Manage system configuration');
        $this->middleware('password.confirm');
    }

    // ------------------------------------------------------------------ page

    public function index(Request $request)
    {
        $pagetitle = 'System Configuration';
        $env = EnvEditor::all();
        $install = Install::current();

        $access = $request->getSchemeAndHttpHost() . $request->getBaseUrl();
        $basePath = $request->getBaseUrl();
        $port = $request->getPort();
        $scheme = $request->getScheme();
        $ips = $this->lanAddresses();
        $suggest = collect($ips)->map(fn ($ip) => $scheme . '://' . $ip . (in_array($port, [80, 443]) ? '' : ':' . $port) . $basePath)->values()->all();
        $appUrl = $env['APP_URL'] ?? config('app.url');
        $tillUrl = rtrim($appUrl, '/') . '/pos';

        $data = [
            'app' => [
                'APP_NAME' => $env['APP_NAME'] ?? config('app.name'),
                'APP_ENV' => $env['APP_ENV'] ?? config('app.env'),
                'APP_DEBUG' => filter_var($env['APP_DEBUG'] ?? config('app.debug'), FILTER_VALIDATE_BOOLEAN),
                'APP_TIMEZONE' => $env['APP_TIMEZONE'] ?? config('app.timezone'),
                'SESSION_LIFETIME' => (int) ($env['SESSION_LIFETIME'] ?? config('session.lifetime')),
            ],
            'server' => [
                'APP_URL' => $appUrl,
                'SESSION_SECURE_COOKIE' => filter_var($env['SESSION_SECURE_COOKIE'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ],
            'db' => [
                'DB_CONNECTION' => $env['DB_CONNECTION'] ?? config('database.default'),
                'DB_HOST' => $env['DB_HOST'] ?? '127.0.0.1',
                'DB_PORT' => $env['DB_PORT'] ?? '3306',
                'DB_DATABASE' => $env['DB_DATABASE'] ?? '',
                'DB_USERNAME' => $env['DB_USERNAME'] ?? '',
                'has_password' => ($env['DB_PASSWORD'] ?? '') !== '',
                'DB_DUMP_PATH' => $env['DB_DUMP_PATH'] ?? '',
            ],
            'mail' => [
                'MAIL_MAILER' => $env['MAIL_MAILER'] ?? config('mail.default'),
                'MAIL_HOST' => $env['MAIL_HOST'] ?? '',
                'MAIL_PORT' => $env['MAIL_PORT'] ?? '587',
                'MAIL_ENCRYPTION' => $this->mailSecurity($env),
                'MAIL_USERNAME' => ($env['MAIL_USERNAME'] ?? '') === 'null' ? '' : ($env['MAIL_USERNAME'] ?? ''),
                'has_password' => !in_array($env['MAIL_PASSWORD'] ?? '', ['', 'null'], true),
                'MAIL_FROM_ADDRESS' => $env['MAIL_FROM_ADDRESS'] ?? '',
                'MAIL_FROM_NAME' => $env['MAIL_FROM_NAME'] ?? '${APP_NAME}',
            ],
            'adv' => [
                'QUEUE_CONNECTION' => $env['QUEUE_CONNECTION'] ?? config('queue.default'),
                'CACHE_STORE' => $env['CACHE_STORE'] ?? config('cache.default'),
                'SESSION_DRIVER' => $env['SESSION_DRIVER'] ?? config('session.driver'),
                'LOG_LEVEL' => $env['LOG_LEVEL'] ?? 'debug',
                'REDIS_HOST' => $env['REDIS_HOST'] ?? '127.0.0.1',
                'REDIS_PORT' => $env['REDIS_PORT'] ?? '6379',
                'has_redis_password' => !in_array($env['REDIS_PASSWORD'] ?? '', ['', 'null'], true),
                'VITEMART_MFA_ENFORCE' => $env['VITEMART_MFA_ENFORCE'] ?? '',
            ],
        ];

        $hq = [
            'mode' => Install::mode(),
            'central_url' => $install?->central_url,
            'branch' => Install::branch() ? Install::branchCode() . ' – ' . Install::branch()->name : null,
            'organization' => Install::organization()?->name,
            'enrolled_at' => $install?->enrolled_at,
        ];

        $health = $this->health($env);
        $tools = collect(self::TOOLS)->map(fn ($t, $k) => ['key' => $k, 'label' => $t[0], 'command' => $t[1]])
            ->reject(fn ($t) => in_array($t['key'], ['sync_now', 'hq_status'], true) && Install::isStandalone())
            ->values()->all();
        $backups = EnvEditor::backups();
        $dbBackups = $this->dbBackups();
        $timezones = \DateTimeZone::listIdentifiers();
        $qr = $this->qrSvg($tillUrl);
        $dumpHints = $this->dumpPathHints();
        $envWritable = EnvEditor::writable();
        $envPath = EnvEditor::path();

        return view('system-config.index', compact(
            'pagetitle', 'data', 'hq', 'health', 'tools', 'backups', 'dbBackups', 'timezones', 'qr', 'ips', 'suggest',
            'access', 'tillUrl', 'dumpHints', 'envWritable', 'envPath', 'port'
        ));
    }

    // ------------------------------------------------------------------ saves

    public function saveApp(Request $request)
    {
        $d = $request->validate([
            'APP_NAME' => 'required|string|max:80',
            'APP_ENV' => ['required', Rule::in(['production', 'local', 'staging'])],
            'APP_DEBUG' => 'nullable|boolean',
            'APP_TIMEZONE' => ['required', Rule::in(\DateTimeZone::listIdentifiers())],
            'SESSION_LIFETIME' => 'required|integer|min:5|max:1440',
        ]);
        return $this->write([
            'APP_NAME' => $d['APP_NAME'],
            'APP_ENV' => $d['APP_ENV'],
            'APP_DEBUG' => $request->boolean('APP_DEBUG') ? 'true' : 'false',
            'APP_TIMEZONE' => $d['APP_TIMEZONE'],
            'SESSION_LIFETIME' => (string) $d['SESSION_LIFETIME'],
        ], 'Store & app settings');
    }

    public function saveServer(Request $request)
    {
        $d = $request->validate([
            'APP_URL' => ['required', 'string', 'max:255', 'regex:#^https?://[^\s/]+(/[^\s]*)?$#i'],
            'SESSION_SECURE_COOKIE' => 'nullable|boolean',
        ], ['APP_URL.regex' => 'Enter the address like http://192.168.1.10/vitemart/public or https://shop.example.com']);
        $url = rtrim($d['APP_URL'], '/');
        $https = str_starts_with(strtolower($url), 'https://');
        return $this->write([
            'APP_URL' => $url,
            'SESSION_SECURE_COOKIE' => $https && $request->boolean('SESSION_SECURE_COOKIE') ? 'true' : 'false',
        ], 'Server address', ['note' => $https ? null : 'Plain http: the till works, but its offline mode only runs on https or on this computer (localhost).']);
    }

    public function testDatabase(Request $request)
    {
        $d = $this->dbInput($request);
        return response()->json($this->tryDatabase($d));
    }

    public function saveDatabase(Request $request)
    {
        $d = $this->dbInput($request);
        $r = $this->tryDatabase($d);
        if (!$r['ok'] && !$request->boolean('force')) {
            return response()->json(['success' => false, 'message' => 'Not saved – ' . $r['message'], 'test' => $r], 422);
        }
        $values = [
            'DB_CONNECTION' => $d['connection'], 'DB_HOST' => $d['host'], 'DB_PORT' => (string) $d['port'],
            'DB_DATABASE' => $d['database'], 'DB_USERNAME' => $d['username'],
            'DB_DUMP_PATH' => (string) ($request->input('DB_DUMP_PATH') ?? ''),
        ];
        if ($request->filled('DB_PASSWORD') || $request->boolean('clear_password')) {
            $values['DB_PASSWORD'] = (string) $request->input('DB_PASSWORD', '');
        }
        $note = $r['ok'] && empty($r['has_tables'])
            ? 'The new database is empty – open Maintenance and run "Update database (migrations)", then seed or restore your data.'
            : 'Everyone will need to sign in again if the sessions live in the database.';
        return $this->write($values, 'Database connection', ['note' => $note, 'test' => $r]);
    }

    public function testMail(Request $request)
    {
        $d = $this->mailInput($request);
        $to = $request->validate(['test_to' => 'required|email'])['test_to'];
        try {
            $this->sendTestMail($d, $to);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Could not send: ' . $this->clean($e->getMessage())], 422);
        }
        return response()->json(['success' => true, 'message' => $d['mailer'] === 'log'
            ? 'Mail is set to "log only" – the test was written to storage/logs/laravel.log, not sent.'
            : "Test email sent to {$to}. Check the inbox (and spam folder)."]);
    }

    public function saveMail(Request $request)
    {
        $d = $this->mailInput($request);
        $values = [
            'MAIL_MAILER' => $d['mailer'],
            'MAIL_HOST' => $d['host'] ?? '',
            'MAIL_PORT' => (string) ($d['port'] ?? ''),
            'MAIL_SCHEME' => $d['security'] === 'ssl' ? 'smtps' : 'smtp',
            'MAIL_ENCRYPTION' => $d['security'] === 'none' ? '' : $d['security'],
            'MAIL_USERNAME' => $d['username'] ?? '',
            'MAIL_FROM_ADDRESS' => $d['from_address'] ?? '',
            'MAIL_FROM_NAME' => $d['from_name'] ?: '${APP_NAME}',
        ];
        if ($request->filled('MAIL_PASSWORD') || $request->boolean('clear_password')) {
            $values['MAIL_PASSWORD'] = (string) $request->input('MAIL_PASSWORD', '');
        }
        return $this->write($values, 'Email (SMTP)');
    }

    public function saveAdvanced(Request $request)
    {
        $d = $request->validate([
            'QUEUE_CONNECTION' => ['required', Rule::in(['sync', 'database', 'redis'])],
            'CACHE_STORE' => ['required', Rule::in(['database', 'file', 'redis'])],
            'SESSION_DRIVER' => ['required', Rule::in(['database', 'file', 'redis'])],
            'LOG_LEVEL' => ['required', Rule::in(['debug', 'info', 'notice', 'warning', 'error', 'critical'])],
            'REDIS_HOST' => 'nullable|string|max:255',
            'REDIS_PORT' => 'nullable|integer|between:1,65535',
            'REDIS_PASSWORD' => 'nullable|string|max:255',
            'VITEMART_MFA_ENFORCE' => ['nullable', Rule::in(['', 'true', 'false'])],
        ]);
        $usesRedis = in_array('redis', [$d['QUEUE_CONNECTION'], $d['CACHE_STORE'], $d['SESSION_DRIVER']], true);
        if ($usesRedis && !extension_loaded('redis') && !class_exists(\Predis\Client::class)) {
            return response()->json(['success' => false, 'message' => 'Redis is not installed for PHP on this server, so it cannot be selected.'], 422);
        }
        $values = [
            'QUEUE_CONNECTION' => $d['QUEUE_CONNECTION'], 'CACHE_STORE' => $d['CACHE_STORE'],
            'SESSION_DRIVER' => $d['SESSION_DRIVER'], 'LOG_LEVEL' => $d['LOG_LEVEL'],
            'REDIS_HOST' => $d['REDIS_HOST'] ?? '127.0.0.1', 'REDIS_PORT' => (string) ($d['REDIS_PORT'] ?? 6379),
            'VITEMART_MFA_ENFORCE' => $d['VITEMART_MFA_ENFORCE'] ?? '',
        ];
        if ($request->filled('REDIS_PASSWORD')) {
            $values['REDIS_PASSWORD'] = $d['REDIS_PASSWORD'];
        }
        $note = $d['SESSION_DRIVER'] !== config('session.driver') ? 'Session storage changed – everyone will need to sign in again.' : null;
        return $this->write($values, 'Performance & background settings', ['note' => $note]);
    }

    // ------------------------------------------------------------------ head office

    public function testHq(Request $request)
    {
        $url = $this->hqUrl($request);
        return response()->json($this->probeHq($url));
    }

    /** Standalone → enrolled branch (same as `vitemart:install --mode=branch`). */
    public function connectBranch(Request $request)
    {
        abort_if(Install::isMultiBranch(), 422, 'This server is already set up as ' . Install::mode() . '.');
        $url = $this->hqUrl($request);
        $d = $request->validate([
            'branch_code' => 'required|string|max:20',
            'code' => 'required|string|max:40',
        ]);
        return $this->runInstall([
            '--mode' => 'branch', '--central-url' => $url,
            '--branch-code' => strtoupper(trim($d['branch_code'])), '--code' => trim($d['code']),
        ], 'Connected to head office at ' . $url);
    }

    /** Standalone → head office (same as `vitemart:install --mode=central`). */
    public function makeCentral(Request $request)
    {
        abort_if(Install::isMultiBranch(), 422, 'This server is already set up as ' . Install::mode() . '.');
        $d = $request->validate(['org' => 'required|string|max:120', 'confirm' => 'accepted']);
        return $this->runInstall(['--mode' => 'central', '--org' => $d['org']], 'Set up as head office');
    }

    /** Enrolled branch: head office moved to a new IP / domain. */
    public function changeHqUrl(Request $request)
    {
        abort_unless(Install::isBranch(), 422, 'Only a branch server has a head office address.');
        $url = $this->hqUrl($request);
        $inst = Install::current();
        $probe = clone $inst;
        $probe->central_url = $url;
        try {
            $res = (new CentralClient($probe))->quick()->post('/api/v1/branch/heartbeat', ['app_version' => config('vitemart.version')]);
        } catch (\Throwable $e) {
            if (!$request->boolean('force')) {
                return response()->json(['success' => false, 'message' => 'Head office not reachable at ' . $url . ': ' . $this->clean($e->getMessage())], 422);
            }
            $res = null;
        }
        if ($res && !$res->successful() && !$request->boolean('force')) {
            $msg = $res->json('error.message') ?? ('HTTP ' . $res->status());
            return response()->json(['success' => false, 'message' => 'Head office at ' . $url . ' did not accept this branch: ' . $msg], 422);
        }
        $old = $inst->central_url;
        Installation::whereKey($inst->id)->update(['central_url' => $url]);
        Install::refresh();
        AuditLogger::log('updated', "Head office address changed from {$old} to {$url}", 'system');
        return response()->json(['success' => true, 'message' => $res ? 'Head office answered – address saved.' : 'Address saved (head office was not checked).']);
    }

    // ------------------------------------------------------------------ tools

    public function tool(Request $request, string $tool)
    {
        abort_unless(isset(self::TOOLS[$tool]), 404);
        [$label, $command, $args] = self::TOOLS[$tool];
        if (in_array($tool, ['sync_now', 'hq_status'], true) && Install::isStandalone()) {
            return response()->json(['success' => false, 'message' => 'This server is a single store – there is no head office.'], 422);
        }
        @set_time_limit(600);
        $started = microtime(true);
        try {
            $code = Artisan::call($command, $args);
            $output = Artisan::output();
        } catch (\Throwable $e) {
            $code = 1;
            $output = $this->clean($e->getMessage());
        }
        if ($tool === 'clear_cache') {
            Cache::forget('pos_search_fulltext');
        }
        AuditLogger::log('maintenance', "Ran: {$label}" . ($code ? ' (failed)' : ''), 'system');
        return response()->json([
            'success' => $code === 0,
            'message' => $label . ($code === 0 ? ' – done' : ' – failed') . sprintf(' (%.1fs)', microtime(true) - $started),
            'output' => trim($this->clean($output)) ?: '(no output)',
        ], $code === 0 ? 200 : 422);
    }

    public function log(Request $request)
    {
        $file = storage_path('logs/laravel.log');
        if (!is_file($file)) {
            return response()->json(['output' => 'No log file yet.']);
        }
        $lines = (int) min(500, max(20, (int) $request->get('lines', 150)));
        return response()->json(['output' => $this->tail($file, $lines), 'size' => filesize($file)]);
    }

    public function restoreEnv(Request $request)
    {
        $name = $request->validate(['name' => 'required|string'])['name'];
        try {
            EnvEditor::restore($name);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        $this->afterWrite();
        AuditLogger::log('updated', 'System configuration restored from backup ' . $name, 'system');
        return response()->json(['success' => true, 'message' => 'Settings restored from ' . $name . '. Reloading…', 'reload' => true]);
    }

    public function downloadBackup(string $file)
    {
        $path = collect($this->dbBackups())->firstWhere('name', $file)['path'] ?? null;
        abort_unless($path && is_file($path), 404);
        AuditLogger::log('exported', 'Downloaded database backup ' . $file, 'system');
        return response()->download($path);
    }

    // ------------------------------------------------------------------ helpers

    protected function write(array $values, string $what, array $extra = [])
    {
        try {
            $backup = EnvEditor::set($values);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        $this->afterWrite();
        $shown = collect($values)->map(fn ($v, $k) => in_array($k, EnvEditor::SECRET, true) ? '(changed)' : $v)
            ->map(fn ($v, $k) => "{$k}={$v}")->implode(', ');
        AuditLogger::log('updated', "System configuration – {$what}: {$shown}", 'system');
        return response()->json(array_filter([
            'success' => true,
            'message' => $what . ' saved. A copy of the previous settings was kept (' . $backup . ').',
            'note' => $extra['note'] ?? null,
            'test' => $extra['test'] ?? null,
        ], fn ($v) => $v !== null));
    }

    protected function afterWrite(): void
    {
        try {
            if (app()->configurationIsCached()) {
                Artisan::call('config:clear');
            }
        } catch (\Throwable $e) {
        }
    }

    protected function dbInput(Request $request): array
    {
        $d = $request->validate([
            'DB_CONNECTION' => ['required', Rule::in(['mysql', 'mariadb'])],
            'DB_HOST' => 'required|string|max:255',
            'DB_PORT' => 'required|integer|between:1,65535',
            'DB_DATABASE' => 'required|string|max:64',
            'DB_USERNAME' => 'required|string|max:80',
            'DB_PASSWORD' => 'nullable|string|max:255',
            'DB_DUMP_PATH' => 'nullable|string|max:255',
        ]);
        $password = $request->filled('DB_PASSWORD') ? $d['DB_PASSWORD']
            : ($request->boolean('clear_password') ? '' : (EnvEditor::get('DB_PASSWORD') ?? ''));
        return ['connection' => $d['DB_CONNECTION'], 'host' => trim($d['DB_HOST']), 'port' => (int) $d['DB_PORT'],
            'database' => trim($d['DB_DATABASE']), 'username' => trim($d['DB_USERNAME']), 'password' => $password];
    }

    protected function tryDatabase(array $d): array
    {
        $t = microtime(true);
        try {
            $pdo = new \PDO("mysql:host={$d['host']};port={$d['port']};charset=utf8mb4", $d['username'], $d['password'], [
                \PDO::ATTR_TIMEOUT => 5, \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            $version = (string) $pdo->query('select version()')->fetchColumn();
            $exists = (bool) $pdo->query('SHOW DATABASES LIKE ' . $pdo->quote($d['database']))->fetchColumn();
            if (!$exists) {
                return ['ok' => false, 'message' => "Connected to the server ({$version}) but database \"{$d['database']}\" doesn't exist. Create it first (e.g. in phpMyAdmin).", 'version' => $version];
            }
            $pdo->exec('USE `' . str_replace('`', '``', $d['database']) . '`');
            $tables = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
            $products = $tables ? (int) ($pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'products'")->fetchColumn()) : 0;
            $productCount = $products ? (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() : null;
            return [
                'ok' => true,
                'message' => sprintf('Connected in %d ms – %s, %d table(s)%s.', (microtime(true) - $t) * 1000, $version, $tables,
                    $productCount !== null ? ', ' . number_format($productCount) . ' product(s)' : ''),
                'version' => $version, 'has_tables' => $tables > 0,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $this->explainDb($e->getMessage())];
        }
    }

    protected function explainDb(string $msg): string
    {
        $m = strtolower($msg);
        return match (true) {
            str_contains($m, 'access denied') => 'Wrong username or password (or this user may not connect from this computer).',
            str_contains($m, 'connection refused') => 'Nothing is answering on that host/port – is MySQL running and is the port right?',
            str_contains($m, 'timed out') || str_contains($m, 'timeout') => 'Timed out – check the IP address and that the firewall allows port 3306.',
            str_contains($m, 'getaddrinfo') || str_contains($m, 'name or service not known') || str_contains($m, 'nodename') => 'That host name could not be found.',
            default => $this->clean($msg),
        };
    }

    protected function mailInput(Request $request): array
    {
        $d = $request->validate([
            'MAIL_MAILER' => ['required', Rule::in(['smtp', 'log', 'sendmail'])],
            'MAIL_HOST' => 'required_if:MAIL_MAILER,smtp|nullable|string|max:255',
            'MAIL_PORT' => 'required_if:MAIL_MAILER,smtp|nullable|integer|between:1,65535',
            'MAIL_ENCRYPTION' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],
            'MAIL_USERNAME' => 'nullable|string|max:255',
            'MAIL_PASSWORD' => 'nullable|string|max:255',
            'MAIL_FROM_ADDRESS' => 'required|email',
            'MAIL_FROM_NAME' => 'nullable|string|max:120',
        ]);
        $password = $request->filled('MAIL_PASSWORD') ? $d['MAIL_PASSWORD']
            : ($request->boolean('clear_password') ? '' : (EnvEditor::get('MAIL_PASSWORD') ?? ''));
        return [
            'mailer' => $d['MAIL_MAILER'], 'host' => $d['MAIL_HOST'] ?? null, 'port' => $d['MAIL_PORT'] ?? null,
            'security' => $d['MAIL_ENCRYPTION'] ?? 'tls', 'username' => $d['MAIL_USERNAME'] ?? null,
            'password' => in_array($password, ['null'], true) ? '' : $password,
            'from_address' => $d['MAIL_FROM_ADDRESS'], 'from_name' => $d['MAIL_FROM_NAME'] ?? null,
        ];
    }

    protected function sendTestMail(array $d, string $to): void
    {
        $name = $d['from_name'] && $d['from_name'] !== '${APP_NAME}' ? $d['from_name'] : config('app.name');
        config([
            'mail.mailers.vm_test' => $d['mailer'] === 'smtp' ? [
                'transport' => 'smtp',
                'scheme' => $d['security'] === 'ssl' ? 'smtps' : 'smtp',
                'host' => $d['host'], 'port' => (int) $d['port'],
                'username' => $d['username'] ?: null, 'password' => $d['password'] ?: null,
                'timeout' => 15,
            ] : ($d['mailer'] === 'sendmail' ? ['transport' => 'sendmail', 'path' => config('mail.mailers.sendmail.path')] : ['transport' => 'log', 'channel' => null]),
            'mail.from' => ['address' => $d['from_address'], 'name' => $name],
        ]);
        app('mail.manager')->purge('vm_test');
        Mail::mailer('vm_test')->raw(
            "This is a test email from {$name} (" . config('app.url') . ").\n\nIf you can read this, receipts and notifications can be emailed from the POS.\n\nSent " . now()->toDayDateTimeString() . '.',
            fn ($m) => $m->to($to)->subject('Test email from ' . $name)
        );
    }

    protected function mailSecurity(array $env): string
    {
        if (($env['MAIL_SCHEME'] ?? '') === 'smtps' || ($env['MAIL_ENCRYPTION'] ?? '') === 'ssl' || ($env['MAIL_PORT'] ?? '') === '465') {
            return 'ssl';
        }
        return ($env['MAIL_ENCRYPTION'] ?? 'tls') === '' ? 'none' : ($env['MAIL_ENCRYPTION'] ?? 'tls');
    }

    protected function hqUrl(Request $request): string
    {
        $raw = trim((string) $request->validate(['central_url' => 'required|string|max:255'])['central_url']);
        if (!preg_match('#^https?://#i', $raw)) {
            $raw = 'http://' . $raw; // a bare IP like 192.168.1.5 or 41.58.1.2:8080
        }
        $url = rtrim($raw, '/');
        abort_unless(filter_var($url, FILTER_VALIDATE_URL), 422, 'That doesn\'t look like a server address.');
        return $url;
    }

    protected function probeHq(string $url): array
    {
        $t = microtime(true);
        try {
            $res = Http::timeout(8)->acceptJson()->post($url . '/api/v1/enrollments', []);
        } catch (\Throwable $e) {
            return ['ok' => false, 'url' => $url, 'message' => 'Could not reach ' . $url . ' – ' . $this->explainNet($e->getMessage())];
        }
        $ms = (int) ((microtime(true) - $t) * 1000);
        if (in_array($res->status(), [422, 429], true) && is_array($res->json())) {
            return ['ok' => true, 'url' => $url, 'message' => "ViteMart head office found at {$url} ({$ms} ms)."];
        }
        if ($res->status() === 404) {
            return ['ok' => false, 'url' => $url, 'message' => "{$url} answered, but it is not a ViteMart head office (or it hasn't been set up as head office yet)."];
        }
        return ['ok' => false, 'url' => $url, 'message' => "{$url} answered with HTTP {$res->status()} – check the address (it should be the head office's ViteMart address, including any /public path)."];
    }

    protected function explainNet(string $msg): string
    {
        $m = strtolower($msg);
        return match (true) {
            str_contains($m, 'timed out') || str_contains($m, 'timeout') => 'no answer (timed out). Check the IP, that head office is switched on, and the firewall.',
            str_contains($m, 'refused') => 'connection refused. Is the web server running on that port?',
            str_contains($m, 'resolve') => 'that name could not be found.',
            str_contains($m, 'ssl') || str_contains($m, 'certificate') => 'secure (https) connection failed – check the certificate or try http://',
            default => $this->clean($msg),
        };
    }

    protected function runInstall(array $args, string $done)
    {
        @set_time_limit(300);
        try {
            $code = Artisan::call('vitemart:install', $args + ['--no-interaction' => true]);
            $out = Artisan::output();
        } catch (\Throwable $e) {
            $code = 1;
            $out = $e->getMessage();
        }
        Install::refresh();
        $out = trim($this->clean($out));
        if ($code !== 0) {
            return response()->json(['success' => false, 'message' => strtok($out, "\n") ?: 'Setup failed.', 'output' => $out], 422);
        }
        AuditLogger::log('installed', $done, 'system');
        return response()->json(['success' => true, 'message' => $done . '.', 'output' => $out, 'reload' => true]);
    }

    /** IPv4 addresses other PCs on the network can use to reach this server. */
    protected function lanAddresses(): array
    {
        $ips = [];
        try {
            $s = @stream_socket_client('udp://8.8.8.8:53', $en, $es, 1);
            if ($s) {
                $name = stream_socket_get_name($s, false);
                fclose($s);
                if ($name) {
                    $ips[] = preg_replace('/:\d+$/', '', $name);
                }
            }
        } catch (\Throwable $e) {
        }
        foreach ([request()->server('SERVER_ADDR'), ...(gethostbynamel((string) gethostname()) ?: [])] as $ip) {
            $ips[] = $ip;
        }
        if (function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
            $out = (string) @shell_exec(PHP_OS_FAMILY === 'Windows' ? 'ipconfig' : '(ifconfig 2>/dev/null || ip -4 addr 2>/dev/null)');
            preg_match_all('/(?:inet |IPv4[^:]*:\s*)(?:addr:)?(\d{1,3}(?:\.\d{1,3}){3})/', $out, $m);
            array_push($ips, ...$m[1]);
        }
        return collect($ips)->filter(fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && !str_starts_with($ip, '127.') && !str_starts_with($ip, '169.254.') && $ip !== '0.0.0.0')
            ->unique()->sortBy(fn ($ip) => preg_match('/^(192\.168|10\.|172\.(1[6-9]|2\d|3[01]))/', $ip) ? 0 : 1)->values()->all();
    }

    protected function qrSvg(string $text): ?string
    {
        try {
            if (class_exists(\Milon\Barcode\DNS2D::class)) {
                $svg = (new \Milon\Barcode\DNS2D())->getBarcodeSVG($text, 'QRCODE', 4, 4);
                return is_string($svg) && str_contains($svg, '<svg') ? preg_replace('/<\?xml.*?\?>\s*(<!DOCTYPE[^>]*>)?/s', '', $svg) : null;
            }
        } catch (\Throwable $e) {
        }
        return null;
    }

    protected function health(array $env): array
    {
        $h = [];
        $add = function (string $group, string $label, string $state, string $detail = '', ?string $fix = null) use (&$h) {
            $h[] = compact('group', 'label', 'state', 'detail', 'fix');
        };

        // Server
        $add('Server', 'PHP', version_compare(PHP_VERSION, '8.2', '>=') ? 'ok' : 'bad', PHP_VERSION, version_compare(PHP_VERSION, '8.2', '<') ? 'Laravel 12 needs PHP 8.2 or newer.' : null);
        $missing = array_values(array_filter(['pdo_mysql', 'mbstring', 'openssl', 'curl', 'gd', 'zip', 'bcmath', 'fileinfo'], fn ($e) => !extension_loaded($e)));
        $add('Server', 'PHP extensions', $missing ? 'warn' : 'ok', $missing ? 'Missing: ' . implode(', ', $missing) : 'All needed extensions loaded', $missing ? 'Enable them in php.ini and restart Apache.' : null);
        $free = @disk_free_space(base_path());
        $add('Server', 'Free disk space', $free !== false && $free < 2 * 1024 ** 3 ? 'warn' : 'ok', $free !== false ? $this->bytes($free) : 'unknown');

        // Database
        try {
            $v = DB::selectOne('select version() as v')->v ?? '';
            $add('Database', 'Connection', 'ok', config('database.connections.' . config('database.default') . '.host') . ' · ' . config('database.connections.' . config('database.default') . '.database') . ' · ' . $v);
        } catch (\Throwable $e) {
            $add('Database', 'Connection', 'bad', $this->explainDb($e->getMessage()), 'Fix it under Database.');
        }
        try {
            $pending = $this->pendingMigrations();
            $add('Database', 'Migrations', $pending ? 'warn' : 'ok', $pending ? $pending . ' update(s) not applied yet' : 'Up to date', $pending ? 'Run "Update database (migrations)" in Maintenance.' : null);
        } catch (\Throwable $e) {
        }

        // Files
        $add('Files', 'Settings file (.env)', EnvEditor::writable() ? 'ok' : 'warn', EnvEditor::writable() ? 'Can be changed from this page' : 'Read-only for the web server',
            EnvEditor::writable() ? null : 'Allow the web server to write ' . EnvEditor::path() . ' (e.g. chmod 664 and the right owner).');
        $add('Files', 'Storage folder', is_writable(storage_path()) ? 'ok' : 'bad', is_writable(storage_path()) ? 'Writable' : 'Not writable', is_writable(storage_path()) ? null : 'Make storage/ writable by the web server.');
        $link = public_path('storage');
        $add('Files', 'Image link', is_link($link) || is_dir($link) ? 'ok' : 'warn', is_link($link) || is_dir($link) ? 'public/storage exists' : 'Missing – product images won\'t show', is_link($link) || is_dir($link) ? null : 'Run "Fix image links" in Maintenance.');

        // App
        $debug = filter_var($env['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $prod = ($env['APP_ENV'] ?? '') === 'production';
        $add('App', 'Error details', $debug ? 'warn' : 'ok', $debug ? 'Shown to users (debug on)' : 'Hidden (debug off)', $debug ? 'Turn "Show error details" off under Store & app once the shop is live.' : null);
        $add('App', 'Environment', $prod ? 'ok' : 'warn', $env['APP_ENV'] ?? 'unknown', $prod ? null : 'Set to Production for a live shop.');
        $url = $env['APP_URL'] ?? '';
        $host = parse_url($url, PHP_URL_HOST);
        $here = request()->getHost();
        $add('App', 'Server address', in_array($host, ['localhost', '127.0.0.1'], true) && !in_array($here, ['localhost', '127.0.0.1'], true) ? 'warn' : 'ok', $url ?: 'not set',
            in_array($host, ['localhost', '127.0.0.1'], true) ? 'Set the LAN address under Server & network so other tills, emails and QR codes get a working link.' : null);
        $mailer = $env['MAIL_MAILER'] ?? 'log';
        $add('App', 'Email', $mailer === 'log' ? 'warn' : 'ok', $mailer === 'log' ? 'Log only – nothing is actually sent' : strtoupper($mailer) . ' via ' . ($env['MAIL_HOST'] ?? ''), $mailer === 'log' ? 'Set up SMTP under Email so receipts can be emailed.' : null);

        // Background work
        $last = Cache::get('vm_scheduler_last_run');
        $schedOk = $last && now()->diffInMinutes(\Carbon\Carbon::parse($last), true) <= 3;
        $add('Background', 'Scheduler', $schedOk ? 'ok' : (Install::isMultiBranch() ? 'bad' : 'warn'), $last ? 'Last ran ' . \Carbon\Carbon::parse($last)->diffForHumans() : 'Has not run yet',
            $schedOk ? null : 'Add a cron job (Mac/Linux) or Task Scheduler entry (Windows) running "php artisan schedule:run" every minute – see the command below.');
        if (Schema::hasTable('jobs')) {
            $jobs = DB::table('jobs')->count();
            $add('Background', 'Waiting jobs', $jobs > 50 ? 'warn' : 'ok', $jobs . ' waiting' . (($env['QUEUE_CONNECTION'] ?? '') === 'sync' ? ' (queue runs instantly)' : ''), $jobs > 50 ? 'Run "Process waiting background jobs", or start a queue worker.' : null);
        }
        if (Schema::hasTable('failed_jobs')) {
            $failed = DB::table('failed_jobs')->count();
            $add('Background', 'Failed jobs', $failed ? 'warn' : 'ok', $failed . ' failed', $failed ? 'Check the log, then "Retry failed background jobs".' : null);
        }
        if (Install::isBranch()) {
            $add('Background', 'Head office', 'info', Install::current()?->central_url ?? '—', null);
        }
        return $h;
    }

    protected function pendingMigrations(): int
    {
        $ran = DB::table('migrations')->pluck('migration')->flip();
        $files = collect(File::files(database_path('migrations')))->map(fn ($f) => $f->getFilenameWithoutExtension());
        return $files->reject(fn ($m) => isset($ran[$m]))->count();
    }

    protected function dbBackups(): array
    {
        $disk = config('backup.backup.destination.disks.0', 'local');
        $root = config("filesystems.disks.{$disk}.root");
        $name = config('backup.backup.name', config('app.name'));
        $dir = $root ? rtrim($root, '/') . '/' . $name : null;
        if (!$dir || !is_dir($dir)) {
            return [];
        }
        return collect(File::files($dir))->filter(fn ($f) => str_ends_with($f->getFilename(), '.zip'))
            ->sortByDesc(fn ($f) => $f->getMTime())->take(10)->values()
            ->map(fn ($f) => ['name' => $f->getFilename(), 'size' => $f->getSize(), 'time' => $f->getMTime(), 'path' => $f->getPathname()])->all();
    }

    protected function dumpPathHints(): array
    {
        $candidates = ['/Applications/XAMPP/xamppfiles/bin', 'C:\\xampp\\mysql\\bin', '/opt/lampp/bin', '/usr/bin', '/usr/local/bin', '/opt/homebrew/bin', '/usr/local/mysql/bin'];
        return array_values(array_filter($candidates, fn ($d) => @is_file($d . '/mysqldump') || @is_file($d . '/mysqldump.exe')));
    }

    protected function tail(string $file, int $lines): string
    {
        $f = new \SplFileObject($file, 'r');
        $f->seek(PHP_INT_MAX);
        $last = $f->key();
        $start = max(0, $last - $lines);
        $out = [];
        $f->seek($start);
        while (!$f->eof()) {
            $out[] = rtrim((string) $f->current(), "\r\n");
            $f->next();
        }
        return $this->clean(implode("\n", $out));
    }

    /** Never echo secrets back. */
    protected function clean(string $s): string
    {
        foreach (EnvEditor::SECRET as $k) {
            $v = EnvEditor::get($k);
            if ($v && strlen($v) >= 3) {
                $s = str_replace($v, '••••', $s);
            }
        }
        return preg_replace('/\e\[[\d;]*m/', '', $s);
    }

    protected function bytes(float $b): string
    {
        $u = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($b >= 1024 && $i < 4) {
            $b /= 1024;
            $i++;
        }
        return round($b, 1) . ' ' . $u[$i];
    }
}
