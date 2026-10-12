<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\SystemSetting;
use App\Models\Unit;
use App\Models\User;
use App\Services\MultiBranch\Install;
use App\Services\MultiBranch\LocationService;
use App\Services\StockService;
use App\Services\Sync\Inbox;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Acceptance tests that need real, committed data or a second database:
 *   7  ten tills at once for the last three units
 *   16 restoring yesterday's backup – the outbox sends again, head office ignores what it has
 *   17 a migration that throws half-way through an upgrade – vitemart:upgrade puts everything back
 *
 * Tests 16 and 17 run against a scratch copy of the test database (VITEMART_OPS_DATABASE, default vm_ops) through the
 * real artisan commands, the way an administrator would run them.
 */
class AcceptanceOperationsTest extends TestCase
{
    protected string $ops;
    protected array $cleanupFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Requires MySQL.');
        }
        $this->ops = env('VITEMART_OPS_DATABASE', 'vm_ops');
        Cache::flush();
        Install::refresh();
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanupFiles as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        if (is_dir(storage_path('app/vitemart-backups/tmp'))) {
            foreach (File::files(storage_path('app/vitemart-backups/tmp')) as $f) {
                @unlink($f->getPathname());
            }
        }
        parent::tearDown();
    }

    protected function artisan_(string $database, array $args, int $timeout = 300)
    {
        return Process::path(base_path())->timeout($timeout)
            ->env(['DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database, 'APP_ENV' => 'testing', 'CACHE_STORE' => 'array'])
            ->run(array_merge([PHP_BINARY, 'artisan'], $args, ['--no-interaction']));
    }

    /** A fresh copy of the test database to break things in. */
    protected function opsDb(): \Illuminate\Database\Connection
    {
        $c = config('database.connections.mysql');
        $auth = ['-u' . $c['username']];
        $auth[] = !empty($c['unix_socket']) ? '--socket=' . $c['unix_socket'] : '-h' . ($c['host'] ?? '127.0.0.1');
        DB::statement("DROP DATABASE IF EXISTS `{$this->ops}`");
        DB::statement("CREATE DATABASE `{$this->ops}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $dump = Process::timeout(300)->env(['MYSQL_PWD' => (string) $c['password']])
            ->run(array_merge(['mysqldump', '--single-transaction', '--routines', '--default-character-set=utf8mb4'], $auth, [$c['database']]));
        $this->assertTrue($dump->successful(), $dump->errorOutput());
        $load = Process::timeout(300)->env(['MYSQL_PWD' => (string) $c['password']])->input($dump->output())
            ->run(array_merge(['mysql', '--default-character-set=utf8mb4'], $auth, [$this->ops]));
        $this->assertTrue($load->successful(), $load->errorOutput());

        config(['database.connections.ops' => array_merge($c, ['database' => $this->ops])]);
        DB::purge('ops');
        return DB::connection('ops');
    }

    protected function backupsOn(\Illuminate\Database\Connection $db): array
    {
        return $db->table('backup_runs')->whereNotNull('path')->pluck('path')->all();
    }

    // ------------------------------------------------------------------ 7

    /** 7. Ten tills sell the last three units at the same moment: three sales, seven "not enough stock", never negative. */
    public function test_07_concurrent_checkout(): void
    {
        $unit = Unit::firstOrCreate(['name' => 'Piece'], ['short_name' => 'pc', 'is_active' => true]);
        $home = Install::branch();
        app(LocationService::class)->ensureFor($home);
        $store = app(LocationService::class)->storeFor($home->id);
        $p = Product::create(['title' => 'Acc7 last units ' . uniqid(), 'price' => 500, 'cost_price' => 300, 'product_type' => 'simple', 'is_active' => true, 'track_stock' => true]);
        ProductUnit::create(['product_id' => $p->id, 'unit_id' => $unit->id, 'quantity_per_unit' => 1, 'is_base' => true, 'is_sellable' => true]);
        $user = User::create(['first_name' => 'Acc7', 'last_name' => 'Till', 'username' => 'acc7-' . uniqid(), 'email' => 'acc7-' . uniqid() . '@test.com', 'password' => Hash::make('x')]);
        $user->assignRole(\Spatie\Permission\Models\Role::findOrCreate('Super Admin', 'web'));
        $prev = ['allow_negative_stock' => SystemSetting::get('allow_negative_stock'), 'require_open_shift' => SystemSetting::get('require_open_shift')];
        SystemSetting::setMany(['allow_negative_stock' => false, 'require_open_shift' => false]);
        app(StockService::class)->record(['product_id' => $p->id, 'location_id' => $store->id, 'direction' => 'in', 'quantity' => 3]);
        $orders = [];

        try {
            $start = microtime(true) + 2.5;
            $pool = Process::pool(function ($pool) use ($user, $p, $start) {
                for ($i = 0; $i < 10; $i++) {
                    $pool->path(base_path())->timeout(60)
                        ->env(['DB_CONNECTION' => 'mysql', 'DB_DATABASE' => config('database.connections.mysql.database'), 'APP_ENV' => 'testing', 'CACHE_STORE' => 'array'])
                        ->command([PHP_BINARY, 'tests/Support/checkout_worker.php', (string) $user->id, (string) $p->id, sprintf('%.4f', $start)]);
                }
            })->start()->wait();

            $results = $pool->collect()->map(fn ($r) => json_decode(trim($r->output()), true) ?: ['ok' => false, 'error' => $r->errorOutput()]);
            $orders = $results->where('ok', true)->pluck('order')->all();
            $this->assertCount(3, $orders, $results->toJson());
            $this->assertSame(7, $results->where('error', 'insufficient_stock')->count(), $results->toJson());
            $this->assertCount(3, array_unique($orders));                      // three different receipt numbers
            $this->assertSame(0.0, app(StockService::class)->balance($p->id, null, $store->id));
            $this->assertSame(0.0, (float) DB::table('inventory_balances')->where('product_id', $p->id)->sum('on_hand'));
            $this->assertSame(3, DB::table('stocks')->where('product_id', $p->id)->where('type', 'out')->count());
            $this->assertSame(0.0, (float) Product::find($p->id)->stock);                 // cached figure is right too
            $this->assertSame([2.0, 1.0, 0.0], DB::table('stocks')->where('product_id', $p->id)->where('type', 'out')->orderBy('id')
                ->pluck('new_quantity')->map(fn ($v) => (float) $v)->all());           // each till saw the one before
        } finally {
            DB::table('order_payments')->whereIn('order_id', $orders)->delete();
            DB::table('order_items')->whereIn('order_id', $orders)->delete();
            DB::table('orders')->whereIn('id', $orders)->delete();
            DB::table('stock_movements')->where('product_id', $p->id)->delete();
            DB::table('stocks')->where('product_id', $p->id)->delete();
            DB::table('inventory_balances')->where('product_id', $p->id)->delete();
            DB::table('product_barcodes')->where('product_id', $p->id)->delete();
            DB::table((new ProductUnit())->getTable())->where('product_id', $p->id)->delete();
            DB::table('products')->where('id', $p->id)->delete();
            DB::table('model_has_roles')->where('model_id', $user->id)->where('model_type', User::class)->delete();
            DB::table('users')->where('id', $user->id)->delete();
            if ($unit->wasRecentlyCreated) {
                $unit->delete();
            }
            SystemSetting::setMany(array_filter($prev, fn ($v) => $v !== null));
        }
    }

    // ------------------------------------------------------------------ 16

    /** 16. Restore yesterday's backup at a branch: what the backup still had waiting is sent again, head office skips duplicates. */
    public function test_16_restore_resends_and_head_office_dedups(): void
    {
        $ops = $this->opsDb();
        $ops->table('installation')->update(['mode' => 'branch', 'central_url' => 'http://127.0.0.1:9', 'remote_installation_uid' => (string) Str::ulid(),
            'token' => Crypt::encryptString('tok'), 'hmac_secret' => Crypt::encryptString('s3cret')]);
        $branchId = (int) $ops->table('installation')->value('branch_id');
        $ops->table('sync_outbox')->delete();
        $customerUid = (string) Str::ulid();
        $eventUid = (string) Str::ulid();
        $payload = ['uid' => $customerUid, 'first_name' => 'Restore', 'last_name' => 'Test', 'phone_number' => '0809' . rand(1000000, 9999999),
            'customer_type' => 'retail', 'created_by__uid' => null, 'updated_by__uid' => null, 'updated_at' => now()->toDateTimeString()];
        $ops->table('sync_outbox')->insert(['event_uid' => $eventUid, 'entity' => 'customer', 'entity_uid' => $customerUid, 'op' => 'upsert',
            'payload' => json_encode($payload), 'status' => 'pending', 'attempts' => 0, 'created_at' => now()]);
        $ops->table('document_sequences')->updateOrInsert(['branch_id' => $branchId, 'doc_type' => 'receipt'], ['next_value' => 120, 'updated_at' => now(), 'created_at' => now()]);

        // "yesterday's" backup
        $r = $this->artisan_($this->ops, ['vitemart:backup']);
        $this->assertTrue($r->successful(), $r->output() . $r->errorOutput());
        $backup = $ops->table('backup_runs')->where('status', 'ok')->orderByDesc('id')->value('path');
        $this->cleanupFiles[] = $backup;
        $this->assertFileExists($backup);
        $this->assertStringContainsString('tables', $this->artisan_($this->ops, ['vitemart:backup', '--verify=' . $backup])->output());

        // the event reached head office, and the branch carried on trading after the backup
        $ops->table('sync_outbox')->where('event_uid', $eventUid)->update(['status' => 'sent', 'sent_at' => now()]);
        $ops->table('document_sequences')->where('branch_id', $branchId)->where('doc_type', 'receipt')->update(['next_value' => 180]);
        DB::beginTransaction();
        try {
            DB::table('installation')->update(['mode' => 'central']);
            Install::refresh();
            $ikj = \App\Models\Branch::create(['code' => 'RST', 'number' => 77, 'name' => 'Restore test', 'type' => 'store', 'status' => 'active']);
            $event = ['event_uid' => $eventUid, 'entity' => 'customer', 'entity_uid' => $customerUid, 'op' => 'upsert', 'payload' => $payload];
            $this->assertSame('applied', app(Inbox::class)->receive($ikj, null, [$event])[0]['status']);

            // restore
            $r = $this->artisan_($this->ops, ['vitemart:restore', $backup, '--force'], 600);
            $this->assertTrue($r->successful(), $r->output() . $r->errorOutput());
            $ops->reconnect();
            foreach ($this->backupsOn($ops) as $f) {
                $this->cleanupFiles[] = $f;
            }
            $this->assertStringContainsString('sent again', $r->output());

            // the event is waiting again, with the same id …
            $row = $ops->table('sync_outbox')->where('event_uid', $eventUid)->first();
            $this->assertSame('pending', $row->status);
            // … receipt numbers jump past anything head office may have seen (head office unreachable here)
            $this->assertGreaterThanOrEqual(1120, (int) $ops->table('document_sequences')->where('branch_id', $branchId)->where('doc_type', 'receipt')->value('next_value'));
            $this->assertTrue($ops->table('backup_runs')->where('kind', 'restored')->exists());

            // … and head office recognises it
            $again = app(Inbox::class)->receive($ikj, null, [['event_uid' => $row->event_uid, 'entity' => $row->entity, 'entity_uid' => $row->entity_uid,
                'op' => $row->op, 'payload' => json_decode($row->payload, true)]]);
            $this->assertSame('duplicate', $again[0]['status']);
            $this->assertSame(1, DB::table('customers')->where('uid', $customerUid)->count());
        } finally {
            DB::rollBack();
            Install::refresh();
        }
        $this->assertFalse(app()->isDownForMaintenance());
    }

    // ------------------------------------------------------------------ 17

    /** 17. A migration throws half-way through vitemart:upgrade: the database goes back as it was and the app starts. */
    public function test_17_failed_upgrade_is_rolled_back(): void
    {
        $ops = $this->opsDb();
        $ops->table('upgrade_runs')->delete();
        $productsBefore = $ops->table('products')->count();
        $dir = storage_path('framework/testing/acc17-' . uniqid());
        File::ensureDirectoryExists($dir);
        File::put($dir . '/2099_01_01_000001_acceptance_breaks_halfway.php', <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acc17_probe', fn (Blueprint $t) => $t->id());
        Schema::table('products', fn (Blueprint $t) => $t->string('acc17_column')->nullable());
        DB::table('products')->delete();
        throw new RuntimeException('Simulated failure half-way through the migration');
    }
};
PHP);

        try {
            $dry = $this->artisan_($this->ops, ['vitemart:upgrade', '--dry-run', '--path=' . $dir]);
            $this->assertTrue($dry->successful(), $dry->output() . $dry->errorOutput());
            $this->assertStringContainsString('acceptance_breaks_halfway', $dry->output());

            $r = $this->artisan_($this->ops, ['vitemart:upgrade', '--path=' . $dir, '--force'], 600);
            foreach ($this->backupsOn($ops) as $f) {
                $this->cleanupFiles[] = $f;
            }
            $this->assertFalse($r->successful(), 'the command reports the failure');
            $this->assertStringContainsString('Simulated failure', $r->output());
            $this->assertStringContainsString('Rolled back', $r->output());

            $ops->reconnect();
            $this->assertFalse($ops->getSchemaBuilder()->hasTable('acc17_probe'));
            $this->assertFalse($ops->getSchemaBuilder()->hasColumn('products', 'acc17_column'));
            $this->assertSame($productsBefore, $ops->table('products')->count());
            $this->assertFalse($ops->table('migrations')->where('migration', 'like', '%acceptance_breaks_halfway%')->exists());
            $run = $ops->table('upgrade_runs')->orderByDesc('id')->first();
            $this->assertSame('rolled_back', $run->status);
            $this->assertNotNull($run->backup_run_id);
            $this->assertFalse(app()->isDownForMaintenance(), 'maintenance mode is off again');

            $boot = $this->artisan_($this->ops, ['vitemart:status']);
            $this->assertTrue($boot->successful(), $boot->output() . $boot->errorOutput());
        } finally {
            File::deleteDirectory($dir);
        }
    }
}
