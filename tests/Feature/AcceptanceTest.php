<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInstallation;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockAdjustment;
use App\Models\StockCount;
use App\Models\SystemSetting;
use App\Models\Unit;
use App\Models\User;
use App\Services\Catalogue\CatalogueRequests;
use App\Services\Catalogue\ProductPayload;
use App\Services\Inventory\CountService;
use App\Services\Inventory\InventoryBalances;
use App\Services\Inventory\TransferService;
use App\Services\MultiBranch\EnrollmentService;
use App\Services\MultiBranch\Install;
use App\Services\MultiBranch\LocationService;
use App\Services\MultiBranch\Signature;
use App\Services\PricingService;
use App\Services\Security\TwoFactor;
use App\Services\StockAdjustmentService;
use App\Services\StockService;
use App\Services\Sync\Feed;
use App\Services\Sync\FeedApplier;
use App\Services\Sync\Inbox;
use App\Services\Sync\Outbox;
use App\Services\Sync\SyncContext;
use App\Services\Sync\SyncRunner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The acceptance tests from the multi-branch design (§ "Acceptance tests"), one method per test, numbered as in the design.
 * Tests 7 (parallel checkout), 16 (restore) and 17 (failed upgrade) need committed data or a second database and live in
 * AcceptanceOperationsTest.
 *
 * Head office and branch run in one database here: the installation mode (and the branch it belongs to) is switched
 * between steps, exactly as the two kinds of server differ.
 */
class AcceptanceTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $home;
    protected User $admin;
    protected User $cashier;
    protected Unit $piece;
    protected LocationService $loc;
    protected StockService $stock;
    protected InventoryBalances $bal;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Requires MySQL.');
        }
        config(['vitemart.sync.feed_settle_seconds' => 0]);
        Cache::flush();
        Install::refresh();
        $this->home = Install::branch();
        $this->loc = app(LocationService::class);
        $this->stock = app(StockService::class);
        $this->bal = app(InventoryBalances::class);
        $this->loc->ensureFor($this->home);
        $store = $this->loc->storeFor($this->home->id);
        DB::table('stock_locations')->where('branch_id', '!=', $this->home->id)->update(['is_default' => false]);
        $store->update(['is_default' => true, 'is_active' => true]);

        $perms = ['dashboard', 'View pos', 'Confirm payments', 'View sync status', 'Manage sync', 'Distribute products', 'View branches',
            'Request stock transfer', 'Approve stock transfer', 'Dispatch stock transfer', 'Receive stock transfer', 'Allocate stock',
            'Resolve transfer discrepancy', 'Count stock', 'Create stock adjustment', 'Approve stock adjustment', 'Edit product'];
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }
        Role::findOrCreate('Super Admin', 'web');
        Role::findOrCreate('Cashier', 'web')->syncPermissions(['View pos']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $mk = fn ($n, $r) => tap(User::create(['first_name' => $n, 'last_name' => 'Acc', 'username' => strtolower($n) . '-acc',
            'email' => strtolower($n) . '-acc@test.com', 'password' => Hash::make('password')]))->assignRole($r);
        $this->admin = $mk('Ada', 'Super Admin');
        $this->cashier = $mk('Cate', 'Cashier');
        $this->piece = Unit::firstOrCreate(['name' => 'Piece'], ['short_name' => 'pc', 'is_active' => true]);
        SystemSetting::setMany(['allow_negative_stock' => false, 'transfer_needs_confirmation' => true, 'card_needs_terminal_ref' => false,
            'require_open_shift' => false, 'max_discount_percent' => 10, 'block_sale_below_cost' => true, 'adjustment_requires_approval' => true]);
        \App\Models\CashRegister::here()->delete();
        DB::table('sync_outbox')->delete();
        DB::table('sync_dirty')->delete();
    }

    // ------------------------------------------------------------------ helpers

    protected function setMode(string $mode, array $extra = []): void
    {
        DB::table('installation')->update(['mode' => $mode] + $extra);
        Install::refresh();
        app()->forgetScopedInstances();
    }

    /** A branch server that thinks head office is at hq.test. */
    protected function branchServer(?int $branchId = null): void
    {
        $this->setMode('branch', ['central_url' => 'https://hq.test', 'remote_installation_uid' => (string) Str::ulid(),
            'token' => Crypt::encryptString('tok'), 'hmac_secret' => Crypt::encryptString('s3cret')] + ($branchId ? ['branch_id' => $branchId] : []));
    }

    protected function branch(string $code, int $number): Branch
    {
        $b = Branch::create(['code' => $code, 'number' => $number, 'name' => $code . ' store', 'type' => 'store', 'status' => 'active']);
        $this->loc->ensureFor($b);
        return $b;
    }

    protected function product(float $stock = 0, float $price = 500, string $owner = 'central', ?int $branchId = null): Product
    {
        $p = (new Product())->forceFill(['title' => 'Acc item ' . uniqid(), 'price' => $price, 'cost_price' => $price * 0.7, 'product_type' => 'simple',
            'is_active' => true, 'track_stock' => true, 'owner' => $owner, 'stock' => 0]);
        $p->save();
        ProductUnit::create(['product_id' => $p->id, 'unit_id' => $this->piece->id, 'quantity_per_unit' => 1, 'is_base' => true, 'is_sellable' => true]);
        if ($stock > 0) {
            $this->stock->record(['product_id' => $p->id, 'location_id' => $this->loc->storeFor($branchId ?? $this->home->id)->id, 'direction' => 'in', 'quantity' => $stock]);
        }
        return $p->fresh();
    }

    protected function sell(Product $p, float $qty, array $payments = [], array $extra = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin)->withSession([TwoFactor::SESSION_KEY => true])->postJson('/pos/checkout', [
            'items' => [['product_id' => $p->id, 'qty' => $qty]],
            'payments' => $payments ?: [['method' => 'cash', 'amount' => $qty * (float) $p->price]],
        ] + $extra);
    }

    protected function event(string $entity, array $payload): array
    {
        return ['event_uid' => (string) Str::ulid(), 'entity' => $entity, 'entity_uid' => $payload['uid'] ?? (string) Str::ulid(), 'op' => 'upsert', 'payload' => $payload];
    }

    /** A signed request from an enrolled branch server. */
    protected function signedAs(array $enrolment): \Closure
    {
        return function (string $method, string $path, array $query = [], array $data = []) use ($enrolment) {
            $body = $method === 'GET' ? '' : json_encode($data);
            $ts = time();
            return $this->call($method, $path . ($query ? '?' . http_build_query($query) : ''), [], [], [], [
                'HTTP_X_INSTALLATION' => $enrolment['installation']->uid, 'HTTP_AUTHORIZATION' => 'Bearer ' . $enrolment['token'],
                'HTTP_X_TIMESTAMP' => (string) $ts, 'HTTP_X_SIGNATURE' => Signature::make($enrolment['secret'], $method, $path, $body, $ts),
                'HTTP_X_APP_VERSION' => config('vitemart.version'), 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            ], $body);
        };
    }

    protected function enrol(Branch $b): array
    {
        return app(EnrollmentService::class)->enroll(app(EnrollmentService::class)->issueCode($b), $b->code, 'test', config('vitemart.version'));
    }

    /** Stock of a product over every location of every branch (shop floors, transit, quarantine). */
    protected function everywhere(Product $p): float
    {
        return round((float) DB::table('inventory_balances')->where('product_id', $p->id)->sum(DB::raw('on_hand + quarantined + in_transit')), 3);
    }

    // ------------------------------------------------------------------ 1–3

    /** 1. A branch keeps selling with head office unreachable; every sale waits in the outbox. */
    public function test_01_branch_sells_while_disconnected(): void
    {
        $this->branchServer();
        $p = $this->product(200, 300);
        Http::fake(fn () => throw new ConnectionException('Could not resolve host: hq.test'));

        for ($i = 0; $i < 50; $i++) {
            $this->sell($p, 1, [], ['client_ref' => 'acc1-' . $i])->assertOk();
        }
        $this->assertSame(150.0, $this->bal->onHand($p->id));
        $this->assertGreaterThanOrEqual(50, DB::table('sync_outbox')->where('entity', 'order')->where('status', 'pending')->count());

        // a sync attempt fails quietly and loses nothing
        try {
            Outbox::push(1, true);
        } catch (\Throwable $e) {
            $this->assertInstanceOf(ConnectionException::class, $e);
        }
        $this->assertSame(50, DB::table('sync_outbox')->where('entity', 'order')->where('status', 'pending')->count());
        $this->assertSame(0, DB::table('sync_outbox')->where('status', 'dead')->count());
    }

    /** 2. A product created at the branch while offline can be sold at once and is queued for head office. */
    public function test_02_offline_local_product_creation(): void
    {
        $this->branchServer();
        Http::fake(fn () => throw new ConnectionException('offline'));
        $p = Product::create(['title' => 'Local zobo 50cl ' . uniqid(), 'price' => 400, 'cost_price' => 250, 'product_type' => 'simple', 'is_active' => true, 'track_stock' => true]);
        ProductUnit::create(['product_id' => $p->id, 'unit_id' => $this->piece->id, 'quantity_per_unit' => 1, 'is_base' => true, 'is_sellable' => true]);
        $this->assertSame('branch', $p->fresh()->owner);
        $this->stock->record(['product_id' => $p->id, 'location_id' => $this->loc->storeFor($this->home->id)->id, 'direction' => 'in', 'quantity' => 12]);

        $this->sell($p->fresh(), 2)->assertOk();
        $this->assertTrue(DB::table('sync_outbox')->where('entity', 'product')->where('entity_uid', $p->uid)->where('status', 'pending')->exists());
        $order = DB::table('sync_outbox')->where('entity', 'order_item')->latest('id')->value('payload');
        $this->assertSame($p->uid, json_decode($order, true)['product_id__uid']);
    }

    /** 3. A cashier gets 403 on back-office and sync endpoints, and cannot use the server API at all. */
    public function test_03_cashier_is_refused(): void
    {
        $as = fn () => $this->actingAs($this->cashier)->withSession([TwoFactor::SESSION_KEY => true]);
        $as()->get('/sync')->assertForbidden();
        $as()->postJson('/sync/run')->assertForbidden();
        $as()->postJson('/sync/backup')->assertForbidden();
        $as()->get('/transfers')->assertForbidden();
        $as()->get('/hq-dashboard')->assertForbidden();
        $as()->get('/pos/payments')->assertForbidden();
        // the server-to-server API wants a branch server's token and signature, not a staff login
        $this->setMode('central');
        $as()->getJson('/api/v1/sync/pull?cursor=0')->assertStatus(401);
        $as()->postJson('/api/v1/sync/push', ['events' => []])->assertStatus(401);
        // and the till still works for them
        $p = $this->product(5);
        $this->setMode('standalone');
        $this->sell($p, 1, [], [], $this->cashier)->assertOk();
    }

    // ------------------------------------------------------------------ 4–6 distribution and sync

    /** 4. Head office distributes a product to selected branches, then to all (three branches). */
    public function test_04_distribute_to_selected_then_all(): void
    {
        $this->setMode('central');
        $ikj = $this->branch('IKJ', 2);
        $lek = $this->branch('LEK', 3);
        $abj = $this->branch('ABJ', 4);
        $p = $this->product(0, 1200);
        $as = fn () => $this->actingAs($this->admin)->withSession([TwoFactor::SESSION_KEY => true]);

        $soldAt = function () use ($p, $ikj, $lek, $abj) {
            Feed::build();
            $out = [];
            foreach ([$ikj, $lek, $abj] as $b) {
                $change = collect(Feed::read($b, 0, 5000)['changes'])->firstWhere('uid', $p->uid);
                $this->assertNotNull($change, "{$b->code} gets the product");
                $this->setMode('branch', ['branch_id' => $b->id]);
                SyncContext::run(fn () => app(FeedApplier::class)->apply($change));
                $out[$b->code] = (bool) Product::where('uid', $p->uid)->value('is_active');
                $this->setMode('central', ['branch_id' => $this->home->id]);
            }
            return $out;
        };

        $as()->postJson("/products/{$p->id}/distribution", ['mode' => 'selected', 'branches' => [$ikj->id, $lek->id]])->assertOk();
        $this->assertSame(['IKJ' => true, 'LEK' => true, 'ABJ' => false], $soldAt());

        Product::whereKey($p->id)->update(['is_active' => true]);
        $as()->postJson("/products/{$p->id}/distribution", ['mode' => 'all'])->assertOk();
        $this->assertSame(['IKJ' => true, 'LEK' => true, 'ABJ' => true], $soldAt());
    }

    /** 5. A branch that was offline catches up: the changes it missed arrive in order and it ends where head office is. */
    public function test_05_offline_branch_catches_up(): void
    {
        $this->setMode('central');
        $ikj = $this->branch('IKJ', 2);
        $lek = $this->branch('LEK', 3);
        DB::table('sync_feed')->delete();
        $as = fn () => $this->actingAs($this->admin)->withSession([TwoFactor::SESSION_KEY => true]);
        Feed::build();
        $cursor = Feed::read($ikj, 0, 5000)['cursor']; // where IKJ was when it went offline

        // three distributions while IKJ is offline (the first product changes twice)
        [$a, $b, $c] = [$this->product(0, 100), $this->product(0, 200), $this->product(0, 300)];
        $as()->postJson("/products/{$a->id}/distribution", ['mode' => 'selected', 'branches' => [$ikj->id]])->assertOk();
        Feed::build();
        $as()->postJson("/products/{$b->id}/distribution", ['mode' => 'selected', 'branches' => [$lek->id]])->assertOk();
        Feed::build();
        $as()->postJson("/products/{$c->id}/distribution", ['mode' => 'all'])->assertOk();
        $as()->postJson("/products/{$a->id}/distribution", ['mode' => 'selected', 'branches' => [$lek->id]])->assertOk();
        Feed::build();
        $head = (int) DB::table('sync_feed')->max('id');

        // back online: page through in small pages
        $seen = [];
        do {
            $page = Feed::read($ikj, $cursor, 2);
            foreach ($page['changes'] as $ch) {
                $seen[] = $ch;
            }
            $cursor = $page['cursor'];
        } while ($page['has_more']);
        $ids = array_column($seen, 'id');
        $sorted = $ids;
        sort($sorted);
        $this->assertSame($sorted, $ids, 'changes arrive in feed order');
        $this->assertSame($head, $cursor);

        $this->setMode('branch', ['branch_id' => $ikj->id]);
        SyncContext::run(function () use ($seen) {
            foreach ($seen as $ch) {
                app(FeedApplier::class)->apply($ch);
            }
        });
        // final state = head office's latest decision, not an intermediate one
        $this->assertFalse((bool) $a->fresh()->is_active);   // moved to LEK only
        $this->assertFalse((bool) $b->fresh()->is_active);
        $this->assertTrue((bool) $c->fresh()->is_active);
        $this->assertCount(1, array_filter($seen, fn ($ch) => $ch['uid'] === $a->uid)); // compacted: one row per record
    }

    /** 6. The same batch sent three times, and a batch whose answer got lost, never doubles anything. */
    public function test_06_retries_never_duplicate(): void
    {
        $this->setMode('central');
        $ikj = $this->branch('IKJ', 2);
        $p = $this->product(0, 1000);
        $base = ProductUnit::where('product_id', $p->id)->where('is_base', true)->first();
        $locUid = (string) Str::ulid();
        $orderId = 'IKJ-261012-' . rand(100000, 999999);
        $now = now()->toDateTimeString();
        $events = [
            $this->event('stock_location', ['uid' => $locUid, 'name' => 'Ikeja floor', 'code' => 'MAIN', 'type' => 'store', 'is_default' => 1, 'is_active' => 1]),
            $this->event('stock', ['uid' => (string) Str::ulid(), 'product_id__uid' => $p->uid, 'stock_location_id__uid' => $locUid, 'user_id__uid' => null,
                'product_variation_id__uid' => null, 'destination_location_id__uid' => null, 'type' => 'in', 'quantity' => 30, 'transaction_date' => $now]),
            $this->event('order', ['id' => $orderId, 'uid' => (string) Str::ulid(), 'customer_id__uid' => null, 'user_id__uid' => $this->admin->uid,
                'pos_shift_id__uid' => null, 'cash_register_id__uid' => null, 'voided_by__uid' => null, 'status' => 'completed', 'total_amount' => 3000,
                'discount_amount' => 0, 'shipping_cost' => 0, 'tax_cost' => 0, 'order_date' => $now, 'payment_method' => 'cash', 'payment_status' => 'paid',
                'billing_address_same_as_shipping' => 1, 'channel' => 'pos']),
            $this->event('order_item', ['uid' => (string) Str::ulid(), 'order_id' => $orderId, 'product_id__uid' => $p->uid, 'product_unit_id__uid' => $base->uid,
                'unit_id__uid' => $this->piece->uid, 'variation_id__uid' => null, 'unit_price' => 1000, 'quantity' => 3, 'quantity_per_unit' => 1, 'base_quantity' => 3,
                'is_unit_mode' => 0, 'total_price' => 3000, 'discount_amount' => 0, 'returned_quantity' => 0, 'price_overridden' => 0]),
            $this->event('stock', ['uid' => (string) Str::ulid(), 'product_id__uid' => $p->uid, 'stock_location_id__uid' => $locUid, 'user_id__uid' => null,
                'product_variation_id__uid' => null, 'destination_location_id__uid' => null, 'type' => 'out', 'quantity' => 3, 'reference_number' => $orderId,
                'reference_type' => 'sale', 'transaction_date' => $now]),
        ];
        $inbox = app(Inbox::class);

        // the connection drops after head office saved the first two: the branch never heard back, so it sends everything again
        $inbox->receive($ikj, null, array_slice($events, 0, 2));
        for ($i = 0; $i < 3; $i++) {
            $res = $inbox->receive($ikj, null, $events);
            $this->assertSame([], array_values(array_diff(array_unique(array_column($res, 'status')), ['applied', 'duplicate'])));
        }
        $this->assertSame(1, Order::where('id', $orderId)->count());
        $this->assertSame(1, DB::table('order_items')->where('order_id', $orderId)->count());
        $this->assertSame(2, DB::table('stocks')->where('product_id', $p->id)->count());
        $this->assertSame(27.0, $this->bal->onHand($p->id, null, $ikj->id));
        $this->assertSame(5, DB::table('sync_inbox')->where('branch_id', $ikj->id)->where('status', 'applied')->count());

        // branch side: a push that failed keeps the same event ids, so the resend is recognised
        $this->branchServer();
        DB::table('sync_outbox')->delete();
        Outbox::recordEntity('customer', (string) Str::ulid(), ['first_name' => 'Retry']);
        $uids = [];
        Http::fake(['hq.test/*' => Http::sequence()
            ->push('gateway timeout', 504)
            ->push(['results' => []], 200)]); // answered, but nothing for our event (lost)
        Outbox::push(1, true);
        DB::table('sync_outbox')->update(['next_attempt_at' => null]);
        Outbox::push(1, true);
        Http::assertSent(function ($req) use (&$uids) {
            $uids[] = $req->data()['events'][0]['event_uid'] ?? null;
            return true;
        });
        $this->assertCount(1, array_unique($uids));
        $this->assertSame('pending', DB::table('sync_outbox')->value('status'));
    }

    // ------------------------------------------------------------------ 7 is in AcceptanceOperationsTest

    // ------------------------------------------------------------------ 8–10 transfers

    /** 8. Allocating stock to a branch doesn't put it on that branch's shelf: it shows as incoming until received. */
    public function test_08_allocation_is_not_destination_stock(): void
    {
        $hq = $this->branch('WHS', 8);
        $p = $this->product(100, 500, 'central', $hq->id);
        $this->product(0); // noise
        $this->stock->record(['product_id' => $p->id, 'location_id' => $this->loc->storeFor($this->home->id)->id, 'direction' => 'in', 'quantity' => 10]);
        $svc = app(TransferService::class);
        $before = $this->bal->available($p->id, null, $this->home->id);

        $t = $svc->create($hq->id, $this->home->id, [['product_id' => $p->id, 'qty' => 40]], 'allocation', true);
        $this->assertSame($before, $this->bal->available($p->id, null, $this->home->id));
        $t = $svc->dispatch($t);
        $this->assertSame($before, $this->bal->available($p->id, null, $this->home->id));   // still not sellable here
        $this->assertSame(40.0, $this->bal->row($p->id, null, $this->home->id)->incoming);
        $this->assertSame(60.0, $this->bal->onHand($p->id, null, $hq->id));

        $svc->receive($t);
        $this->assertSame($before + 40, $this->bal->available($p->id, null, $this->home->id));
        $this->assertSame(0.0, $this->bal->row($p->id, null, $this->home->id)->incoming);
    }

    /** 9. Partial delivery: 100 asked, 98 approved and sent, 96 arrive, 2 short, 2 written off. */
    public function test_09_partial_delivery(): void
    {
        $other = $this->branch('OTH', 9);
        $p = $this->product(98);
        $svc = app(TransferService::class);
        $t = $svc->create($this->home->id, $other->id, [['product_id' => $p->id, 'qty' => 100]]);
        $l = $t->lines[0];
        $t = $svc->approve($t, [$l->uid => 98]);                    // only 98 on hand
        $t = $svc->dispatch($t);
        $t = $svc->receive($t, [$l->uid => 96]);
        $l = $t->lines[0];
        $this->assertSame([100.0, 98.0, 98.0, 96.0, -2.0], [(float) $l->requested_qty, (float) $l->approved_qty, (float) $l->dispatched_qty,
            (float) $l->received_qty, (float) $l->discrepancy_qty]);
        $this->assertSame('received', $t->status);                   // open until the 2 are settled
        $this->assertSame(96.0, $this->bal->onHand($p->id, null, $other->id));

        $t = $svc->resolve($t, $l, 'written_off', 'broken in the van');
        $this->assertSame('closed', $t->status);
        $this->assertSame(2.0, (float) DB::table('stocks')->where('product_id', $p->id)->where('type', 'damage')->sum('quantity'));
    }

    /** 10. Stock over all locations is unchanged by a transfer – until a loss is posted. */
    public function test_10_transfer_keeps_the_total_until_a_loss_is_posted(): void
    {
        $other = $this->branch('OTH', 9);
        $p = $this->product(50);
        $svc = app(TransferService::class);
        $total = $this->everywhere($p);
        $this->assertSame(50.0, $total);

        $t = $svc->create($this->home->id, $other->id, [['product_id' => $p->id, 'qty' => 20]], null, true);
        $this->assertSame($total, $this->everywhere($p));
        $t = $svc->dispatch($t);
        $this->assertSame($total, $this->everywhere($p));             // 20 in transit
        $t = $svc->receive($t, [$t->lines[0]->uid => 17], null, [$t->lines[0]->uid => 1]);
        $this->assertSame($total, $this->everywhere($p));             // 17 shelf + 1 quarantine + 2 still in transit
        $this->assertSame(1.0, (float) $this->bal->row($p->id, null, $other->id)->quarantined);
        $this->assertSame(2.0, (float) $this->bal->row($p->id, null, $this->home->id)->in_transit);

        $svc->resolve($t, $t->lines[0], 'charged', 'driver');
        $this->assertSame($total - 2, $this->everywhere($p));         // the loss
        $svc->syncEffects($t->fresh('lines'));
        $this->assertSame($total - 2, $this->everywhere($p));         // posted once
    }

    // ------------------------------------------------------------------ 11–12

    /** 11. A branch cannot change a head-office price (it becomes a request); a local edit is put back on the next pull. */
    public function test_11_branch_cannot_change_central_price(): void
    {
        $p = $this->product(0, 150);
        $hqPayload = ProductPayload::export($p->fresh());
        $hqPayload['distribution'] = null;
        $this->branchServer();
        $this->assertTrue(CatalogueRequests::governed($p));

        $this->actingAs($this->admin)->withSession([TwoFactor::SESSION_KEY => true])
            ->postJson('/price-control/quick', ['kind' => 'product', 'id' => $p->id, 'field' => 'price', 'value' => 170])
            ->assertOk()->assertJson(['requested' => true]);
        $this->assertEquals(150, (float) $p->fresh()->price);
        $this->expectExceptionMessageMatches('/head-office product/');
        try {
            app(PricingService::class)->setPrice($p, 'price', 99);
        } finally {
            // someone edits the database by hand – head office's price comes back with the next pull
            DB::table('products')->where('id', $p->id)->update(['price' => 120]);
            SyncContext::run(fn () => app(FeedApplier::class)->apply(['entity' => 'product', 'uid' => $p->uid, 'op' => 'upsert', 'payload' => $hqPayload]));
            $this->assertEquals(150, (float) $p->fresh()->price);
        }
    }

    /** 12. A command for a newer version is refused; one this version understands runs and is acknowledged. */
    public function test_12_commands_check_the_version(): void
    {
        $this->branchServer();
        $old = (string) Str::ulid();
        $ok = (string) Str::ulid();
        Http::fake([
            'hq.test/api/v1/sync/push' => Http::response(['results' => []]),
            'hq.test/api/v1/sync/pull*' => Http::sequence()
                ->push(['changes' => [], 'cursor' => 0, 'has_more' => false, 'head' => 0, 'commands' => [
                    ['uid' => $old, 'command' => 'diagnostics', 'payload' => null, 'min_version' => '2099.1.0'],
                    ['uid' => $ok, 'command' => 'diagnostics', 'payload' => null, 'min_version' => config('vitemart.version')],
                ]])
                ->push(['changes' => [], 'cursor' => 0, 'has_more' => false, 'head' => 0, 'commands' => []]),
            'hq.test/api/v1/sync/commands/*' => Http::response(['updated' => 1]),
        ]);
        app(SyncRunner::class)->run();
        $this->assertSame('refused', DB::table('sync_command_runs')->where('uid', $old)->value('status'));
        $this->assertSame('done', DB::table('sync_command_runs')->where('uid', $ok)->value('status'));
        foreach ([$old => 'refused', $ok => 'done'] as $uid => $status) {
            Http::assertSent(fn ($r) => str_ends_with($r->url(), "/sync/commands/{$uid}") && $r->data()['status'] === $status);
        }
        // head office side: the acknowledgement closes the command
        $this->setMode('central');
        $ikj = $this->branch('IKJ', 2);
        $e = $this->enrol($ikj);
        $cmd = (string) Str::ulid();
        DB::table('sync_commands')->insert(['uid' => $cmd, 'branch_id' => $ikj->id, 'command' => 'diagnostics', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        ($this->signedAs($e))('POST', '/api/v1/sync/commands/' . $cmd, [], ['status' => 'done', 'result' => 'ok'])->assertOk()->assertJson(['updated' => 1]);
        $this->assertSame('done', DB::table('sync_commands')->where('uid', $cmd)->value('status'));
    }

    // ------------------------------------------------------------------ 13–15

    /** 13. Head-office reports say how fresh each branch's figures are. */
    public function test_13_stale_reports_are_flagged(): void
    {
        $this->setMode('central');
        $ikj = $this->branch('IKJ', 2);
        $lek = $this->branch('LEK', 3);
        $this->enrol($ikj);
        $this->enrol($lek);
        BranchInstallation::where('branch_id', $ikj->id)->update(['last_push_at' => now()->subHours(3), 'last_seen_at' => now()->subHours(3)]);
        BranchInstallation::where('branch_id', $lek->id)->update(['last_push_at' => now()->subMinutes(2), 'last_seen_at' => now()->subMinutes(2)]);

        $svc = app(\App\Services\Reports\HqDashboardService::class);
        $this->assertSame('stale', $svc->freshness($ikj->fresh('activeInstallation'))['state']);
        $this->assertSame('live', $svc->freshness($lek->fresh('activeInstallation'))['state']);

        $this->actingAs($this->admin)->withSession([TwoFactor::SESSION_KEY => true])->get('/hq-dashboard')->assertOk()
            ->assertSee('Out of date')->assertSee('3 hours ago')->assertSee('is behind', false);
    }

    /** 14. A stock count ends as a count adjustment; balances are only ever moved through the ledger. */
    public function test_14_stocktake_goes_through_an_adjustment(): void
    {
        $p = $this->product(40);
        $loc = $this->loc->storeFor($this->home->id);
        Role::findOrCreate('Counter', 'web')->syncPermissions(['Count stock', 'Create stock adjustment']);
        $counter = tap(User::create(['first_name' => 'Cnt', 'last_name' => 'Acc', 'username' => 'cnt-acc', 'email' => 'cnt-acc@test.com', 'password' => Hash::make('x')]))->assignRole('Counter');
        $this->actingAs($counter);
        $svc = app(CountService::class);
        $count = $svc->create(['location_id' => $loc->id, 'scope' => 'products', 'product_ids' => [$p->id], 'blind' => true, 'recount_threshold' => 50]);
        $line = $count->lines()->where('product_id', $p->id)->first();
        $this->assertNotNull($line);
        $svc->record($count, [['line_id' => $line->id, 'counted_qty' => 37]]);
        $r = $svc->submit($count);
        $this->assertSame('submitted', $r['status']);
        $adj = StockAdjustment::find($r['count']->stock_adjustment_id);
        $this->assertNotNull($adj);
        $this->assertSame(40.0, $this->bal->onHand($p->id));          // nothing moves before approval
        $ledger = DB::table('stocks')->where('product_id', $p->id)->count();

        $this->actingAs($this->admin);
        app(StockAdjustmentService::class)->approve($adj);
        $this->assertSame(37.0, $this->bal->onHand($p->id));
        $this->assertSame($ledger + 1, DB::table('stocks')->where('product_id', $p->id)->count());
        $this->assertSame('count', $adj->type);
        $last = DB::table('stocks')->where('product_id', $p->id)->latest('id')->first();
        $this->assertSame([$adj->reference, 'adjustment', 'out', 3.0], [$last->reference_number, $last->reference_type, $last->type, (float) $last->quantity]);
        // the balance equals the ledger
        app(\Illuminate\Contracts\Console\Kernel::class)->call('vitemart:rebuild-stock');
        $this->assertSame(37.0, $this->bal->onHand($p->id));
    }

    /** 15. A card payment whose terminal timed out stays pending; the till's retry doesn't make a second sale; it's settled once. */
    public function test_15_timed_out_card_payment_stays_pending(): void
    {
        $p = $this->product(10, 2500);
        $pay = [['method' => 'card', 'amount' => 5000, 'pending' => true]];
        $first = $this->sell($p, 2, $pay, ['client_ref' => 'acc15-' . uniqid('', true)])->assertOk();
        $ref = $first->json('order_id');
        $this->assertSame(5000.0, (float) $first->json('pending_payments'));
        $this->assertSame('pending', Order::find($ref)->payment_status);

        // the till didn't hear back and sends the same sale again
        $client = 'acc15b-' . uniqid('', true);
        $a = $this->sell($p, 1, [['method' => 'card', 'amount' => 2500, 'pending' => true]], ['client_ref' => $client])->assertOk();
        $b = $this->sell($p, 1, [['method' => 'card', 'amount' => 2500, 'pending' => true]], ['client_ref' => $client])->assertOk();
        $this->assertSame($a->json('order_id'), $b->json('order_id'));
        $this->assertSame(7.0, $this->bal->onHand($p->id));

        $payment = OrderPayment::where('order_id', $ref)->firstOrFail();
        $as = fn () => $this->actingAs($this->admin)->withSession([TwoFactor::SESSION_KEY => true]);
        $as()->postJson("/pos/payments/{$payment->id}/confirm", ['reference' => 'TERM-0042'])->assertOk();
        $as()->postJson("/pos/payments/{$payment->id}/confirm", ['reference' => 'TERM-0042'])->assertStatus(422);
        $this->assertSame('paid', Order::find($ref)->payment_status);
        $this->assertSame(1, OrderPayment::where('order_id', $ref)->count());
    }

    // ------------------------------------------------------------------ 16, 17 are in AcceptanceOperationsTest

    /** 18. Branch B's server credentials can't read or write branch A's data. */
    public function test_18_branch_cannot_touch_another_branchs_data(): void
    {
        $this->setMode('central');
        $a = $this->branch('IKJ', 2);
        $b = $this->branch('LEK', 3);
        $asB = $this->signedAs($this->enrol($b));
        $p = $this->product(0, 800);

        // A's price list is not in B's feed
        $list = \App\Models\PriceList::forBranch($a);
        app(\App\Services\Catalogue\PriceListResolver::class)->set($list, $p, null, null, ['price' => 750]);
        Feed::build();
        $pull = $asB('GET', '/api/v1/sync/pull', ['cursor' => 0, 'limit' => 1000])->assertOk();
        $this->assertNotContains('price_list_item', array_column($pull->json('changes'), 'entity'));

        // A's sale and A's stock location: B can't overwrite them
        $orderId = 'IKJ-261012-' . rand(100000, 999999);
        $now = now()->toDateTimeString();
        $order = ['id' => $orderId, 'uid' => (string) Str::ulid(), 'customer_id__uid' => null, 'user_id__uid' => null, 'pos_shift_id__uid' => null,
            'cash_register_id__uid' => null, 'voided_by__uid' => null, 'status' => 'completed', 'total_amount' => 800, 'discount_amount' => 0,
            'shipping_cost' => 0, 'tax_cost' => 0, 'order_date' => $now, 'payment_method' => 'cash', 'payment_status' => 'paid',
            'billing_address_same_as_shipping' => 1, 'channel' => 'pos'];
        app(Inbox::class)->receive($a, null, [$this->event('order', $order)]);
        $this->assertSame($a->id, Order::find($orderId)->branch_id);

        $res = $asB('POST', '/api/v1/sync/push', [], ['events' => [
            $this->event('order', ['total_amount' => 1] + $order),
            $this->event('stock', ['uid' => (string) Str::ulid(), 'product_id__uid' => $p->uid, 'stock_location_id__uid' => $this->loc->storeFor($a->id)->uid,
                'user_id__uid' => null, 'product_variation_id__uid' => null, 'destination_location_id__uid' => null, 'type' => 'out', 'quantity' => 5, 'transaction_date' => $now]),
        ]])->assertOk();
        $this->assertSame(['rejected', 'rejected'], array_column($res->json('results'), 'status'));
        $this->assertEquals(800, (float) Order::find($orderId)->total_amount);
        $this->assertSame($a->id, Order::find($orderId)->branch_id);
        $this->assertSame(0, DB::table('stocks')->where('product_id', $p->id)->count());

        // a transfer between A and head office is none of B's business
        $t = app(TransferService::class)->create($this->home->id, $a->id, [['product_id' => $p->id, 'qty' => 1]]);
        $doc = \App\Services\Inventory\DocumentPayload::transfer($t);
        $doc['status'] = 'cancelled';
        $doc['version'] = 99;
        $res = $asB('POST', '/api/v1/sync/push', [], ['events' => [$this->event('transfer', $doc)]])->assertOk();
        $this->assertSame('rejected', $res->json('results.0.status'));
        $this->assertSame('requested', $t->fresh()->status);

        // B can't close A's commands
        $cmd = (string) Str::ulid();
        DB::table('sync_commands')->insert(['uid' => $cmd, 'branch_id' => $a->id, 'command' => 'diagnostics', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $asB('POST', '/api/v1/sync/commands/' . $cmd, [], ['status' => 'done'])->assertOk()->assertJson(['updated' => 0]);
        $this->assertSame('pending', DB::table('sync_commands')->where('uid', $cmd)->value('status'));
    }
}
