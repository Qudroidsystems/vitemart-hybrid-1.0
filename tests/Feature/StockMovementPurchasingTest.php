<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\StockAdjustment;
use App\Models\StockCount;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\SystemSetting;
use App\Models\Unit;
use App\Models\User;
use App\Services\Inventory\CountService;
use App\Services\Inventory\DocumentPayload;
use App\Services\Inventory\InventoryBalances;
use App\Services\Inventory\PurchasingService;
use App\Services\Inventory\TransferService;
use App\Services\MultiBranch\Install;
use App\Services\MultiBranch\LocationService;
use App\Services\Security\TwoFactor;
use App\Services\StockService;
use App\Services\Sync\FeedApplier;
use App\Services\Sync\Inbox;
use App\Services\Sync\SyncContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 6: transfers (with in-transit stock, reservations and differences), blind stock counts, purchasing.
 */
class StockMovementPurchasingTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $home;
    protected Branch $other;
    protected User $admin;
    protected User $clerk;
    protected Unit $piece;
    protected Unit $carton;
    protected LocationService $loc;
    protected StockService $stock;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Requires MySQL.');
        }
        Cache::flush();
        Install::refresh();
        $this->home = Install::branch();
        $this->loc = app(LocationService::class);
        $this->stock = app(StockService::class);
        $this->loc->ensureFor($this->home);
        $this->other = Branch::create(['code' => 'OTH', 'number' => 9, 'name' => 'Other branch', 'type' => 'store', 'status' => 'active']);
        $this->loc->ensureFor($this->other);

        $perms = ['dashboard', 'View product', 'Request stock transfer', 'Approve stock transfer', 'Dispatch stock transfer', 'Receive stock transfer',
            'Allocate stock', 'Resolve transfer discrepancy', 'View purchasing', 'Create purchase order', 'Approve purchase order', 'Manage suppliers',
            'Post goods receipt', 'Return to supplier', 'Count stock', 'Create stock adjustment', 'Approve stock adjustment'];
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }
        Role::findOrCreate('Super Admin', 'web');
        Role::findOrCreate('Store clerk', 'web')->syncPermissions(['View product', 'Count stock', 'Create purchase order', 'View purchasing', 'Post goods receipt', 'Request stock transfer']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $mk = fn ($n, $r) => tap(User::create(['first_name' => $n, 'last_name' => 'T', 'username' => strtolower($n) . '-p6', 'email' => strtolower($n) . '-p6@test.com', 'password' => Hash::make('x')]))->assignRole($r);
        $this->admin = $mk('Admin', 'Super Admin');
        $this->clerk = $mk('Clerk', 'Store clerk');
        $this->piece = Unit::firstOrCreate(['name' => 'Piece'], ['short_name' => 'pc', 'is_active' => true]);
        $this->carton = Unit::firstOrCreate(['name' => 'Carton'], ['short_name' => 'ctn', 'is_active' => true]);
        SystemSetting::setMany(['allow_negative_stock' => false, 'adjustment_requires_approval' => true, 'po_approval_limit' => 0]);
        $this->actingAs($this->admin);
    }

    protected function product(float $stock = 0, ?int $branchId = null, string $title = 'P6 Tomato paste'): Product
    {
        $p = (new Product())->forceFill(['title' => $title . ' ' . uniqid(), 'price' => 500, 'cost_price' => 300, 'product_type' => 'simple',
            'is_active' => true, 'track_stock' => true, 'owner' => 'central', 'stock' => 0]);
        $p->save();
        ProductUnit::create(['product_id' => $p->id, 'unit_id' => $this->piece->id, 'quantity_per_unit' => 1, 'is_base' => true, 'is_sellable' => true]);
        ProductUnit::create(['product_id' => $p->id, 'unit_id' => $this->carton->id, 'quantity_per_unit' => 12, 'is_sellable' => true]);
        if ($stock > 0) {
            $this->stock->record(['product_id' => $p->id, 'location_id' => $this->loc->storeFor($branchId ?? $this->home->id)->id, 'direction' => 'in', 'quantity' => $stock]);
        }
        return $p->fresh('packs');
    }

    protected function carton(Product $p): ProductUnit
    {
        return $p->packs->firstWhere('quantity_per_unit', 12);
    }

    // ------------------------------------------------------------------ transfers

    public function test_transfer_moves_stock_through_transit_with_reservation_and_differences(): void
    {
        $p = $this->product(100);
        $svc = app(TransferService::class);
        $bal = app(InventoryBalances::class);

        // send 3 cartons (36) from here → approved straight away → 36 reserved
        $t = $svc->create($this->home->id, $this->other->id, [['product_id' => $p->id, 'pack_id' => $this->carton($p)->id, 'qty' => 3]], 'weekend promo', true);
        $this->assertSame('approved', $t->status);
        $this->assertSame(36.0, (float) $t->lines[0]->approved_qty);
        $this->assertSame(64.0, $bal->available($p->id));
        $this->assertMatchesRegularExpression('/^TRF-[A-Z]+-\d{6}$/', $t->number);

        // reserved stock can't be sold away
        $this->actingAs($this->admin)->withSession([TwoFactor::SESSION_KEY => true])
            ->postJson('/pos/checkout', ['items' => [['product_id' => $p->id, 'qty' => 70]], 'payments' => [['method' => 'cash', 'amount' => 35000]]])->assertStatus(422);

        // dispatch only 2 cartons
        $t = $svc->dispatch($t, [$t->lines[0]->uid => 2], 'Van 3 – Musa');
        $transit = $this->loc->typed($this->home->id, 'transit');
        $this->assertSame('dispatched', $t->status);
        $this->assertSame(76.0, $bal->onHand($p->id));
        $this->assertSame(24.0, $this->stock->balance($p->id, null, $transit->id));
        $this->assertSame(0.0, $bal->reservedFor($p->id));
        $this->assertSame(76.0, $bal->available($p->id));

        // receive 23 (one piece missing) → difference to resolve
        $t = $svc->receive($t, [$t->lines[0]->uid => 23 / 12], 'one tin dented and kept');
        $this->assertSame('received', $t->status);
        $this->assertTrue($t->has_discrepancy);
        $this->assertSame(-1.0, round((float) $t->lines[0]->discrepancy_qty, 3));
        $this->assertSame(23.0, $bal->onHand($p->id, null, $this->other->id));
        // what arrived left transit; the missing piece stays in transit until the difference is settled
        $this->assertSame(1.0, $this->stock->balance($p->id, null, $transit->id));

        $t = $svc->resolve($t, $t->lines[0], 'written_off', 'dented');
        $this->assertSame('closed', $t->status);
        $this->assertSame('written_off', $t->lines[0]->discrepancy_status);
        $this->assertSame(0.0, $this->stock->balance($p->id, null, $transit->id));
        $this->assertDatabaseHas('stocks', ['product_id' => $p->id, 'stock_location_id' => $transit->id, 'type' => 'damage', 'quantity' => 1]);

        // effects are posted once only
        $svc->syncEffects($t);
        $this->assertSame(0.0, $this->stock->balance($p->id, null, $transit->id));
        $this->assertSame(76.0, $bal->onHand($p->id));
    }

    public function test_a_request_is_approved_by_the_sender_and_can_be_cancelled(): void
    {
        $p = $this->product(50, $this->other->id);
        $svc = app(TransferService::class);
        $t = $svc->create($this->other->id, $this->home->id, [['product_id' => $p->id, 'qty' => 10]], null, false);
        $this->assertSame('requested', $t->status);
        $t = $svc->cancel($t, 'not needed');
        $this->assertSame('cancelled', $t->status);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $svc->dispatch($t);
    }

    public function test_transfer_travels_between_servers_and_each_posts_its_own_stock(): void
    {
        // On the sending branch's server
        DB::table('installation')->update(['mode' => 'branch']);
        Install::refresh();
        DB::table('sync_outbox')->delete();
        $p = $this->product(60);
        $svc = app(TransferService::class);
        $t = $svc->dispatch($svc->create($this->home->id, $this->other->id, [['product_id' => $p->id, 'qty' => 12]], null, true));
        $this->assertGreaterThanOrEqual(2, DB::table('sync_outbox')->where('entity', 'transfer')->count() + DB::table('sync_outbox')->where('entity', 'stock')->count());
        $payload = DocumentPayload::transfer($t);
        $this->assertSame($this->other->uid, $payload['to_branch_uid']);

        // The receiving server receives 12 → its copy comes back through the feed
        $received = $payload;
        $received['status'] = 'closed';
        $received['version'] = $payload['version'] + 2;
        $received['lines'][0]['received_qty'] = 12;
        $received['received_by_name'] = 'Other storekeeper';
        SyncContext::run(fn () => app(FeedApplier::class)->apply(['entity' => 'transfer', 'uid' => $t->uid, 'op' => 'upsert', 'payload' => $received]));
        $t->refresh();
        $this->assertSame('closed', $t->status);
        $this->assertSame(0.0, $this->stock->balance($p->id, null, $this->loc->typed($this->home->id, 'transit')->id)); // cleared here
        $this->assertTrue(DB::table('sync_outbox')->where('entity', 'stock')->where('payload', 'like', '%Received by OTH%')->exists()); // and sent up

        // an older copy arriving late changes nothing
        SyncContext::run(fn () => app(FeedApplier::class)->apply(['entity' => 'transfer', 'uid' => $t->uid, 'op' => 'upsert', 'payload' => $payload]));
        $this->assertSame('closed', $t->fresh()->status);
    }

    public function test_head_office_passes_a_transfer_on_to_both_branches(): void
    {
        DB::table('installation')->update(['mode' => 'central']);
        Install::refresh();
        $p = $this->product();
        $uid = (string) Str::ulid();
        $payload = ['uid' => $uid, 'number' => 'TRF-OTH-000001', 'status' => 'requested', 'version' => 1, 'from_branch_uid' => $this->home->uid,
            'to_branch_uid' => $this->other->uid, 'requested_by_name' => 'x', 'has_discrepancy' => false,
            'lines' => [['uid' => (string) Str::ulid(), 'product_uid' => $p->uid, 'variation_uid' => null, 'pack_uid' => null, 'qty_per_unit' => 1, 'requested_qty' => 5]]];
        $r = app(Inbox::class)->receive($this->other, null, [['event_uid' => (string) Str::ulid(), 'entity' => 'transfer', 'entity_uid' => $uid, 'op' => 'upsert', 'payload' => $payload]]);
        $this->assertSame('applied', $r[0]['status'], json_encode($r));
        \App\Services\Sync\Feed::build();
        $targets = DB::table('sync_feed')->where('entity', 'transfer')->where('entity_uid', $uid)->pluck('branch_id')->sort()->values()->all();
        $this->assertSame(collect([$this->home->id, $this->other->id])->sort()->values()->all(), $targets);
    }

    // ------------------------------------------------------------------ counts

    public function test_blind_count_asks_for_a_recount_then_becomes_an_adjustment(): void
    {
        $cat = Category::create(['name' => 'P6 Count cat ' . uniqid()]);
        $a = $this->product(40);
        $b = $this->product(10);
        $a->update(['category_id' => $cat->id]);
        $b->update(['category_id' => $cat->id]);
        $this->actingAs($this->clerk);
        $svc = app(CountService::class);
        $count = $svc->create(['location_id' => $this->loc->storeFor($this->home->id)->id, 'scope' => 'category', 'category_id' => $cat->id, 'blind' => true, 'recount_threshold' => 10]);
        $this->assertSame(2, $count->lines()->count());

        // counter can't see the expected quantity
        $this->withSession([TwoFactor::SESSION_KEY => true])->get("/stock-counts/{$count->id}")->assertOk()->assertDontSee('Expected');

        $la = $count->lines()->where('product_id', $a->id)->first();
        $lb = $count->lines()->where('product_id', $b->id)->first();
        $svc->record($count, [['line_id' => $la->id, 'counted_qty' => 39], ['line_id' => $lb->id, 'counted_qty' => 2]]);
        $r = $svc->submit($count);
        $this->assertSame('recount', $r['status']);
        $this->assertSame(1, $r['recount']);                            // 2 vs 10 is way off; 39 vs 40 is fine
        $this->assertNull($lb->fresh()->counted_qty);

        $svc->record($count, [['line_id' => $lb->id, 'counted_qty' => 3]]);
        $r = $svc->submit($count);
        $this->assertSame('submitted', $r['status']);
        $adj = StockAdjustment::find($r['count']->stock_adjustment_id);
        $this->assertSame('pending', $adj->status);                     // clerk can't approve
        $this->assertSame(-7.0, (float) $lb->fresh()->variance);

        $this->actingAs($this->admin);
        app(\App\Services\StockAdjustmentService::class)->approve($adj);
        $this->assertSame(39.0, (float) $a->fresh()->stock);
        $this->assertSame(3.0, (float) $b->fresh()->stock);
    }

    // ------------------------------------------------------------------ purchasing

    public function test_purchase_order_to_delivery_with_free_goods_and_return(): void
    {
        $p = $this->product(0);
        $sup = Supplier::create(['name' => 'P6 Foods Ltd ' . uniqid(), 'payment_terms_days' => 30]);
        $svc = app(PurchasingService::class);

        // clerk orders 10 cartons → needs approval
        $this->actingAs($this->clerk);
        $po = $svc->createOrder(['supplier_id' => $sup->id, 'action' => 'submit',
            'lines' => [['product_id' => $p->id, 'pack_id' => $this->carton($p)->id, 'qty' => 10, 'unit_cost' => 3000]]]);
        $this->assertSame('submitted', $po->status);
        $this->assertEquals(30000, $po->total);

        $this->actingAs($this->admin);
        $po = $svc->markSent($svc->approveOrder($po));
        $this->assertSame('sent', $po->status);

        // first delivery: 6 cartons + 1 free carton
        $grn = $svc->receive(['purchase_order_id' => $po->id, 'supplier_invoice' => 'INV-77', 'lines' => [[
            'product_id' => $p->id, 'pack_id' => $this->carton($p)->id, 'qty' => 6, 'free_qty' => 1, 'unit_cost' => 3000,
            'expiry_date' => now()->addYear()->toDateString(), 'batch_number' => 'B1', 'po_line_id' => $po->lines[0]->id]]]);
        $this->assertEquals(18000, $grn->total);
        $this->assertSame(84.0, (float) $p->fresh()->stock);                     // 7 cartons × 12
        $this->assertEquals(round(18000 / 84, 2), (float) $p->fresh()->cost_price); // free goods lower the unit cost
        $po->refresh();
        $this->assertSame('partial', $po->status);
        $this->assertEquals(7, $po->lines[0]->received_qty);

        // the rest
        $svc->receive(['purchase_order_id' => $po->id, 'lines' => [['product_id' => $p->id, 'pack_id' => $this->carton($p)->id, 'qty' => 3, 'unit_cost' => 3000, 'po_line_id' => $po->lines[0]->id]]]);
        $this->assertSame('received', $po->fresh()->status);

        // can't receive on a finished order
        try {
            $svc->receive(['purchase_order_id' => $po->id, 'lines' => [['product_id' => $p->id, 'qty' => 1]]]);
            $this->fail('expected refusal');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('not open', collect($e->errors())->flatten()->first());
        }

        // return a carton
        $ret = $svc->returnToSupplier(['supplier_id' => $sup->id, 'goods_receipt_id' => $grn->id, 'reason' => 'dented cans',
            'lines' => [['product_id' => $p->id, 'pack_id' => $this->carton($p)->id, 'qty' => 1, 'unit_cost' => 3000]]]);
        $this->assertSame(108.0, (float) $p->fresh()->stock); // 84 + 36 − 12
        $this->assertMatchesRegularExpression('/^SRN-/', $ret->number);

        // delivery without an order
        $g2 = $svc->receive(['supplier_id' => $sup->id, 'lines' => [['product_id' => $p->id, 'qty' => 5, 'unit_cost' => 260]]]);
        $this->assertNull($g2->purchase_order_id);
        $this->assertSame(113.0, (float) $p->fresh()->stock);
    }

    public function test_pages_render(): void
    {
        $p = $this->product(20);
        $sup = Supplier::create(['name' => 'P6 Pages Supplier ' . uniqid()]);
        $t = app(TransferService::class)->create($this->home->id, $this->other->id, [['product_id' => $p->id, 'qty' => 2]], null, true);
        $po = app(PurchasingService::class)->createOrder(['supplier_id' => $sup->id, 'lines' => [['product_id' => $p->id, 'qty' => 1, 'unit_cost' => 10]]]);
        $grn = app(PurchasingService::class)->receive(['supplier_id' => $sup->id, 'lines' => [['product_id' => $p->id, 'qty' => 1, 'unit_cost' => 10]]]);
        $count = app(CountService::class)->create(['location_id' => $this->loc->storeFor($this->home->id)->id, 'scope' => 'selected', 'product_ids' => [$p->id], 'blind' => false]);

        $as = $this->actingAs($this->admin)->withSession([TwoFactor::SESSION_KEY => true]);
        foreach (['/transfers', '/transfers/create', "/transfers/{$t->id}", "/transfers/{$t->id}/print", '/stock-counts', "/stock-counts/{$count->id}",
                     '/suppliers', '/purchase-orders', '/purchase-orders/create', "/purchase-orders/{$po->id}", "/purchase-orders/{$po->id}/print",
                     '/goods-received', '/goods-received/create', "/goods-received/create?po={$po->id}", "/goods-received/{$grn->id}",
                     '/supplier-returns', '/supplier-returns/create', "/supplier-returns/create?grn={$grn->id}"] as $url) {
            $as->get($url)->assertOk();
        }
        $as->get("/stock-counts/{$count->id}")->assertSee('Expected'); // not blind
        $as->postJson('/suppliers', ['name' => 'P6 New supplier ' . uniqid(), 'code' => 'p6x' . rand(10, 99)])->assertOk();
        $as->postJson('/transfers', ['from_branch_id' => $this->home->id, 'to_branch_id' => $this->other->id, 'lines' => [['product_id' => $p->id, 'qty' => 1]]])
            ->assertOk()->assertJsonPath('success', true);
        $as->postJson("/transfers/{$t->id}/dispatch", ['carrier' => 'Bike'])->assertOk();
        $as->postJson("/transfers/{$t->id}/receive", ['qty' => [$t->lines[0]->uid => 2]])->assertOk();
        $this->assertSame('closed', $t->fresh()->status);
    }
}
