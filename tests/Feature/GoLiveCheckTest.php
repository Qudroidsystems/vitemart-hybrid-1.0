<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\MultiBranch\Install;
use App\Services\Operations\ReadinessService;
use App\Services\Security\TwoFactor;
use App\Services\Sync\Outbox;
use App\Services\Sync\SyncState;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The go-live check (vitemart:preflight and /sync/go-live). */
class GoLiveCheckTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Requires MySQL.');
        }
        Cache::flush();
        Install::refresh();
        foreach (['dashboard', 'View sync status', 'Manage sync'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        Role::findOrCreate('Super Admin', 'web');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->admin = User::create(['first_name' => 'Gl', 'last_name' => 'Admin', 'username' => 'gl-admin', 'email' => 'gl-admin@test.com', 'password' => Hash::make('S0me-long-passphrase')]);
        $this->admin->assignRole('Super Admin');
        DB::table('sync_outbox')->delete();
    }

    protected function setMode(string $mode): void
    {
        DB::table('installation')->update(['mode' => $mode]);
        Install::refresh();
        app()->forgetScopedInstances();
    }

    protected function check(string $key): ?array
    {
        return collect(app(ReadinessService::class)->run())->firstWhere('key', $key);
    }

    public function test_a_single_store_is_not_ready_and_says_how_to_join(): void
    {
        $this->setMode('standalone');
        $c = $this->check('mode');
        $this->assertSame('fail', $c['status']);
        $this->assertStringContainsString('vitemart:install', $c['fix']);
        $this->assertFalse(app(ReadinessService::class)->summary(app(ReadinessService::class)->run())['ready']);
    }

    public function test_scheduler_debug_and_default_passwords_are_caught(): void
    {
        config(['app.debug' => true]);
        $this->assertSame('fail', $this->check('debug')['status']);
        config(['app.debug' => false]);
        $this->assertSame('ok', $this->check('debug')['status']);

        Cache::forget('vm_scheduler_last_run');
        $this->assertSame('fail', $this->check('scheduler')['status']);
        Cache::forever('vm_scheduler_last_run', now()->toIso8601String());
        $this->assertSame('ok', $this->check('scheduler')['status']);

        $weak = User::create(['first_name' => 'Weak', 'last_name' => 'Pw', 'username' => 'gl-weak', 'email' => 'gl-weak@test.com', 'password' => Hash::make('password')]);
        $weak->assignRole('Super Admin');
        $c = $this->check('default_password');
        $this->assertSame('fail', $c['status']);
        $this->assertStringContainsString('gl-weak@test.com', $c['detail']);
        $this->assertStringNotContainsString('gl-admin@test.com', $c['detail']);
    }

    public function test_catalogue_problems_are_counted(): void
    {
        $code = '29' . str_pad((string) rand(0, 99999999999), 11, '0', STR_PAD_LEFT);
        $p = (new Product())->forceFill(['title' => 'GL dup 1', 'price' => 0, 'cost_price' => 100, 'product_type' => 'simple', 'is_active' => true, 'barcode' => $code]);
        $p->save();
        // the app refuses a second one, but older data can still have it
        $row = (array) DB::table('products')->where('id', $p->id)->first();
        unset($row['id']);
        DB::table('products')->insert(['uid' => (string) Str::ulid(), 'title' => 'GL dup 2'] + $row);
        $this->assertSame('fail', $this->check('dup_barcode')['status']);
        $this->assertStringContainsString($code, $this->check('dup_barcode')['detail']);
        $this->assertSame('warn', $this->check('price')['status']);
    }

    public function test_branch_link_problems_show(): void
    {
        $this->setMode('branch');
        SyncState::set('feed_cursor', 0);
        $this->assertSame('fail', $this->check('catalogue')['status']);
        SyncState::set('feed_cursor', 40);
        SyncState::set('feed_head', 40);
        SyncState::set('feed_failures', []);
        $this->assertSame('ok', $this->check('catalogue')['status']);

        Outbox::recordEntity('customer', (string) Str::ulid(), ['x' => 1]);
        DB::table('sync_outbox')->update(['status' => 'dead']);
        $this->assertSame('fail', $this->check('outbox')['status']);

        SyncState::set('hq_clock_skew', 300);
        $this->assertSame('fail', $this->check('clock')['status']);
        SyncState::set('hq_clock_skew', -12);
        $this->assertSame('ok', $this->check('clock')['status']);

        // managers of this branch must have two-step sign-in
        Role::findOrCreate('HQ Admin', 'web');
        $m = User::create(['first_name' => 'Mgr', 'last_name' => 'NoMfa', 'username' => 'gl-mgr', 'email' => 'gl-mgr@test.com', 'password' => Hash::make('x-long-pass'), 'branch_id' => Install::branchId()]);
        $m->assignRole('HQ Admin');
        $this->assertSame('fail', $this->check('mfa')['status']);
        $m->forceFill(['two_factor_secret' => encrypt('ABC'), 'two_factor_confirmed_at' => now()])->save();
        $this->assertTrue(TwoFactor::enabled($m->fresh()));
    }

    public function test_page_and_command(): void
    {
        $this->setMode('branch');
        $this->actingAs($this->admin)->withSession([TwoFactor::SESSION_KEY => true])->get('/sync/go-live')
            ->assertOk()->assertSee('Go-live check')->assertSee('Background jobs running');
        $this->actingAs($this->admin)->withSession([TwoFactor::SESSION_KEY => true])->get('/sync')->assertOk()->assertSee('Go-live check');

        $this->setMode('standalone');
        $this->artisan('vitemart:preflight')->assertExitCode(1);
        $this->artisan('vitemart:preflight', ['--json' => true])->assertExitCode(1);
    }
}
