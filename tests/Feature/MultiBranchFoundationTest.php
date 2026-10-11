<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInstallation;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Services\BarcodeGenerator;
use App\Services\LoginPolicy;
use App\Services\MultiBranch\DocumentNumber;
use App\Services\MultiBranch\EnrollmentService;
use App\Services\MultiBranch\Install;
use App\Services\MultiBranch\LocationService;
use App\Services\MultiBranch\Signature;
use App\Services\Security\Totp;
use App\Services\Security\TwoFactor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 1–2 of the multi-branch build: installation modes, branch identity, document numbers,
 * barcodes per branch, enrollment, signed server requests, branch-scoped login and two-step sign-in.
 *
 *   DB_CONNECTION=mysql DB_DATABASE=vitemart_test ./vendor/bin/pest --filter=MultiBranchFoundationTest
 */
class MultiBranchFoundationTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $home;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Requires MySQL.');
        }
        Cache::flush();
        Install::refresh();
        $this->home = Install::branch();

        foreach (['dashboard', 'View pos', 'Manage branches', 'View branches', 'Access all branches'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        Role::findOrCreate('Super Admin', 'web');
        Role::findOrCreate('Cashier', 'web')->givePermissionTo(['View pos']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->admin = $this->makeUser('Ada', 'mb-admin@test.com', 'Super Admin');
    }

    protected function makeUser(string $first, string $email, string $role, ?int $branchId = null): User
    {
        $u = User::create(['first_name' => $first, 'last_name' => 'Test', 'username' => $email, 'email' => $email, 'password' => Hash::make('password')]);
        if ($branchId !== null) {
            $u->forceFill(['branch_id' => $branchId])->saveQuietly();
        }
        $u->assignRole($role);
        return $u->fresh();
    }

    protected function setMode(string $mode): void
    {
        DB::table('installation')->update(['mode' => $mode]);
        Install::refresh();
    }

    protected function makeBranch(string $code, int $number): Branch
    {
        $b = Branch::create(['code' => $code, 'number' => $number, 'name' => "$code store", 'type' => 'store', 'status' => 'active']);
        app(LocationService::class)->ensureFor($b);
        return $b;
    }

    // ------------------------------------------------------------------

    public function test_existing_store_becomes_first_branch_with_ulids(): void
    {
        $this->assertNotNull($this->home);
        $this->assertContains(Install::mode(), ['standalone', 'central', 'branch']);
        $this->assertSame(26, strlen($this->home->uid));

        $p = Product::create(['title' => 'ULID test', 'price' => 100, 'cost_price' => 80, 'product_type' => 'simple']);
        $this->assertSame(26, strlen($p->uid));
        $this->assertTrue(Str::isUlid($p->uid));
        $this->assertSame($p->id, Product::findByUid($p->uid)->id);

        // branch-owned rows are stamped with this server's branch
        app(LocationService::class)->ensureFor($this->home);
        $loc = app(LocationService::class)->storeFor($this->home->id);
        $adj = StockAdjustment::create(['reference' => 'X-' . Str::random(6), 'type' => 'adjustment', 'status' => 'pending', 'created_by' => $this->admin->id,
            'stock_location_id' => $loc->id]);
        $this->assertSame($this->home->id, $adj->branch_id);
    }

    public function test_document_numbers_are_per_branch_and_sequential(): void
    {
        $code = $this->home->code;
        $a = DocumentNumber::next('adjustment');
        $b = DocumentNumber::next('adjustment');
        $this->assertMatchesRegularExpression("/^ADJ-{$code}-\\d{6}$/", $a);
        $this->assertSame((int) substr($a, -6) + 1, (int) substr($b, -6));

        $r = DocumentNumber::next('receipt');
        $this->assertMatchesRegularExpression("/^{$code}-\\d{6}-\\d{6}$/", $r);

        // a different branch has its own counter
        $other = $this->makeBranch('LEK', 7);
        $this->assertSame('TRF-LEK-000001', DocumentNumber::next('transfer', $other->id, 'LEK'));
        $this->assertSame('TRF-LEK-000002', DocumentNumber::next('transfer', $other->id, 'LEK'));
    }

    public function test_generated_barcodes_carry_the_branch_number(): void
    {
        $code = BarcodeGenerator::next();
        $this->assertTrue(BarcodeGenerator::isValidEan13($code));
        $this->assertSame('20' . str_pad((string) Install::branchNumber(), 2, '0', STR_PAD_LEFT), substr($code, 0, 4));
    }

    public function test_each_branch_gets_store_transit_and_quarantine_locations(): void
    {
        $b = $this->makeBranch('YAB', 8);
        $types = DB::table('stock_locations')->where('branch_id', $b->id)->pluck('type')->sort()->values()->all();
        $this->assertSame(['quarantine', 'store', 'transit'], $types);
        // idempotent
        app(LocationService::class)->ensureFor($b);
        $this->assertSame(3, DB::table('stock_locations')->where('branch_id', $b->id)->count());
    }

    public function test_enrollment_code_is_single_use_and_branch_bound(): void
    {
        $this->setMode('central');
        $ikj = $this->makeBranch('IKJ', 2);
        $code = app(EnrollmentService::class)->issueCode($ikj);

        // wrong branch
        $this->postJson('/api/v1/enrollments', ['code' => $code, 'branch_code' => 'LEK'])
            ->assertStatus(422)->assertJsonPath('error.code', 'enrollment_refused');

        $res = $this->postJson('/api/v1/enrollments', ['code' => $code, 'branch_code' => 'IKJ', 'app_version' => '2026.11.0'])
            ->assertCreated()
            ->assertJsonPath('branch.code', 'IKJ')
            ->assertJsonPath('branch.number', 2);
        $this->assertSame(48, strlen($res->json('token')));

        // reuse refused
        $this->postJson('/api/v1/enrollments', ['code' => $code, 'branch_code' => 'IKJ'])->assertStatus(422);

        // expired code refused
        $code2 = app(EnrollmentService::class)->issueCode($ikj);
        DB::table('branch_enrollment_codes')->where('code_hash', hash('sha256', strtoupper(str_replace('-', '', $code2))))
            ->orWhere('code_hash', hash('sha256', $code2))->update(['expires_at' => now()->subMinute()]);
        $this->postJson('/api/v1/enrollments', ['code' => $code2, 'branch_code' => 'IKJ'])->assertStatus(422);
    }

    public function test_enrollment_is_refused_on_a_branch_server(): void
    {
        $this->setMode('branch');
        $this->postJson('/api/v1/enrollments', ['code' => 'AAAA-BBBB-CCCC', 'branch_code' => 'IKJ'])->assertStatus(404);
    }

    public function test_branch_server_requests_must_be_signed(): void
    {
        $this->setMode('central');
        $ikj = $this->makeBranch('IKJ', 2);
        $code = app(EnrollmentService::class)->issueCode($ikj);
        $r = app(EnrollmentService::class)->enroll($code, 'IKJ', 'test', '2026.11.0');
        $inst = $r['installation'];

        $call = function (string $secret, int $ts, ?string $token = null, string $body = '{"pending_events":3}') use ($inst, $r) {
            $path = '/api/v1/branch/heartbeat';
            return $this->call('POST', $path, [], [], [], [
                'HTTP_X_INSTALLATION' => $inst->uid,
                'HTTP_AUTHORIZATION' => 'Bearer ' . ($token ?? $r['token']),
                'HTTP_X_TIMESTAMP' => (string) $ts,
                'HTTP_X_SIGNATURE' => Signature::make($secret, 'POST', $path, $body, $ts),
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ], $body);
        };

        $call($r['secret'], time())->assertOk();
        $this->assertNotNull($inst->fresh()->last_seen_at);

        $call('wrong-secret', time())->assertStatus(401)->assertJsonPath('error.code', 'bad_signature');
        $call($r['secret'], time() - 600)->assertStatus(401)->assertJsonPath('error.code', 'bad_signature');
        $call($r['secret'], time(), 'not-the-token')->assertStatus(401)->assertJsonPath('error.code', 'unauthenticated');

        app(EnrollmentService::class)->revoke($inst->fresh());
        $call($r['secret'], time())->assertStatus(401)->assertJsonPath('error.code', 'revoked');
    }

    public function test_staff_can_only_sign_in_at_their_branches(): void
    {
        $policy = app(LoginPolicy::class);
        $lek = $this->makeBranch('LEK', 7);
        $cashierHere = $this->makeUser('Cate', 'mb-cashier1@test.com', 'Cashier', $this->home->id);
        $cashierThere = $this->makeUser('Lola', 'mb-cashier2@test.com', 'Cashier', $lek->id);

        // single store: branch assignment not enforced
        $this->setMode('standalone');
        $this->assertNull($policy->denialReason($cashierThere));

        $this->setMode('branch');
        $this->assertNull($policy->denialReason($cashierHere));
        $this->assertSame('Your account is not assigned to this branch.', $policy->denialReason($cashierThere));
        $this->assertNull($policy->denialReason($this->admin));

        // extra branch assignment lets them in
        $cashierThere->branches()->attach($this->home->id);
        $this->assertNull($policy->denialReason($cashierThere->fresh()));

        // "Access all branches" works everywhere
        $roaming = $this->makeUser('Ray', 'mb-roam@test.com', 'Cashier', $lek->id);
        $roaming->givePermissionTo('Access all branches');
        $this->assertNull($policy->denialReason($roaming->fresh()));

        // and through the real login form
        $this->post('/login', ['email' => 'mb-cashier2@test.com', 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticated();
        auth()->logout();
        $outsider = $this->makeUser('Out', 'mb-out@test.com', 'Cashier', $lek->id);
        $this->post('/login', ['email' => $outsider->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_totp_matches_rfc6238_vectors(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; // "12345678901234567890"
        $this->assertSame('94287082', Totp::code($secret, 59, 8));
        $this->assertSame('07081804', Totp::code($secret, 1111111109, 8));
        $this->assertSame('14050471', Totp::code($secret, 1111111111, 8));
        $this->assertSame('12345678901234567890', Totp::base32Decode($secret));
        $this->assertNotNull(Totp::verify($secret, Totp::code($secret, time() - 30)));
        $this->assertNull(Totp::verify($secret, Totp::code($secret, time() - 120)));
    }

    public function test_two_step_sign_in_flow(): void
    {
        $this->setMode('standalone');
        $user = $this->makeUser('Mia', 'mb-mfa@test.com', 'Cashier', $this->home->id);
        $user->givePermissionTo('dashboard');

        // set up
        $this->actingAs($user);
        $this->get('/two-factor')->assertOk()->assertSee('Turn on');
        $secret = session('mfa_setup_secret');
        $this->assertNotEmpty($secret);
        $this->post('/two-factor/confirm', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/two-factor/confirm', ['code' => Totp::code($secret)])->assertRedirect(route('mfa.setup'));
        $user->refresh();
        $this->assertTrue(TwoFactor::enabled($user));
        $this->assertCount(8, session('mfa_recovery_codes'));
        $recovery = session('mfa_recovery_codes')[0];
        $this->assertNotSame($secret, $user->two_factor_secret); // stored encrypted
        $this->assertSame($secret, Crypt::decryptString($user->two_factor_secret));
        auth()->logout();
        $this->flushSession();
        Cache::flush();

        // sign in: password then code
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->assertAuthenticated();
        $this->get('/dashboard')->assertRedirect(route('mfa.challenge'));
        $this->getJson('/dashboard')->assertStatus(423);
        $this->post('/two-factor/challenge', ['code' => '123456'])->assertSessionHasErrors('code');

        $code = Totp::code($secret);
        $this->post('/two-factor/challenge', ['code' => $code])->assertRedirect();
        $this->assertTrue(session(TwoFactor::SESSION_KEY));
        $this->get('/two-factor/challenge')->assertRedirect();

        // same code cannot be replayed in another session
        auth()->logout();
        $this->flushSession();
        $this->actingAs($user->fresh());
        $this->post('/two-factor/challenge', ['code' => $code])->assertSessionHasErrors('code');

        // recovery code works once
        $this->post('/two-factor/challenge', ['code' => strtolower($recovery)])->assertRedirect();
        $this->assertTrue(session(TwoFactor::SESSION_KEY));
        $this->assertCount(7, TwoFactor::recoveryHashes($user->fresh()));
        $this->flushSession();
        $this->post('/two-factor/challenge', ['code' => $recovery])->assertSessionHasErrors('code');

        $this->assertDatabaseHas('audit_logs', ['event' => 'mfa_enabled', 'user_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'mfa_failed']);
    }

    public function test_roles_that_need_two_step_sign_in_must_set_it_up_on_multi_branch(): void
    {
        $this->setMode('standalone');
        $this->assertFalse(TwoFactor::required($this->admin));

        $this->setMode('central');
        $this->assertTrue(TwoFactor::required($this->admin));
        $this->actingAs($this->admin);
        $this->get('/dashboard')->assertRedirect(route('mfa.setup'));
        $this->get('/two-factor')->assertOk()->assertSee('required');

        // ordinary roles are not forced
        $cashier = $this->makeUser('Cate', 'mb-c3@test.com', 'Cashier', $this->home->id);
        $this->assertFalse(TwoFactor::required($cashier));

        // Super Admin can reset someone who lost their phone
        $cashier->forceFill(['two_factor_secret' => Crypt::encryptString(Totp::generateSecret()), 'two_factor_confirmed_at' => now()])->saveQuietly();
        $this->withSession([TwoFactor::SESSION_KEY => true])
            ->postJson("/two-factor/reset/{$cashier->id}")->assertOk();
        $this->assertFalse(TwoFactor::enabled($cashier->fresh()));
    }

    public function test_branch_pages_render_at_hq(): void
    {
        $this->setMode('central');
        $ikj = $this->makeBranch('IKJ', 2);
        $this->actingAs($this->admin)->withSession([TwoFactor::SESSION_KEY => true]);

        $this->get('/branches')->assertOk()->assertSee('IKJ store');
        $this->get("/branches/{$ikj->id}")->assertOk()->assertSee('IKJ');

        $res = $this->postJson("/branches/{$ikj->id}/enrollment-code")->assertOk();
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $res->json('code'));

        $this->postJson('/branches', ['code' => 'ajh', 'number' => 9, 'name' => 'Ajah', 'type' => 'store'])->assertRedirect();
        $this->assertDatabaseHas('branches', ['code' => 'AJH', 'number' => 9]);
        $this->postJson('/branches', ['code' => 'IKJ', 'number' => 10, 'name' => 'Dup', 'type' => 'store'])->assertStatus(422);
    }

    public function test_branch_identity_cannot_be_created_on_a_branch_server(): void
    {
        $this->setMode('branch');
        $this->actingAs($this->admin)->withSession([TwoFactor::SESSION_KEY => true]);
        $this->postJson('/branches', ['code' => 'XYZ', 'number' => 11, 'name' => 'Nope', 'type' => 'store'])->assertStatus(403);
        $this->get('/branches')->assertOk();
    }
}
