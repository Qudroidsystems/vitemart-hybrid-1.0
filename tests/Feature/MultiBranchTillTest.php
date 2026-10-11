<?php

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockLocation;
use App\Models\SystemSetting;
use App\Models\Unit;
use App\Models\User;
use App\Services\Inventory\InventoryBalances;
use App\Services\MultiBranch\Install;
use App\Services\MultiBranch\LocationService;
use App\Services\Security\TwoFactor;
use App\Services\StockService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 4: stock balances (available = on hand − reserved), tills, payment confirmation, self-hosted till assets.
 */
class MultiBranchTillTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $cashier;
    protected Unit $piece;
    protected StockLocation $store;
    protected InventoryBalances $bal;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Requires MySQL.');
        }
        Cache::flush();
        Install::refresh();
        $home = Install::branch();
        app(LocationService::class)->ensureFor($home);
        $this->store = app(LocationService::class)->storeFor($home->id);
        DB::table('stock_locations')->where('branch_id', '!=', $home->id)->update(['is_default' => false]);
        $this->store->update(['is_default' => true, 'is_active' => true]);

        foreach (['dashboard', 'View pos', 'Confirm payments', 'Manage cash registers', 'Manage pos shifts'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        Role::findOrCreate('Super Admin', 'web');
        Role::findOrCreate('Cashier', 'web')->syncPermissions(['View pos']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $mk = fn ($n, $e, $r) => tap(User::create(['first_name' => $n, 'last_name' => 'T', 'username' => $e, 'email' => $e, 'password' => Hash::make('password')]))->assignRole($r);
        $this->admin = $mk('Ada', 'till-admin@test.com', 'Super Admin');
        $this->cashier = $mk('Cate', 'till-cashier@test.com', 'Cashier');
        $this->piece = Unit::firstOrCreate(['name' => 'Piece'], ['short_name' => 'pc', 'is_active' => true]);
        SystemSetting::setMany(['max_discount_percent' => 10, 'block_sale_below_cost' => true, 'transfer_needs_confirmation' => true,
            'card_needs_terminal_ref' => false, 'require_open_shift' => false, 'allow_negative_stock' => false]);
        CashRegister::here()->delete();
        $this->bal = app(InventoryBalances::class);
    }

    protected function product(float $stock = 100, float $price = 500): Product
    {
        $p = Product::create(['title' => 'Till item ' . uniqid(), 'price' => $price, 'cost_price' => $price * 0.7, 'product_type' => 'simple', 'is_active' => true, 'track_stock' => true]);
        ProductUnit::create(['product_id' => $p->id, 'unit_id' => $this->piece->id, 'quantity_per_unit' => 1, 'is_base' => true, 'is_sellable' => true]);
        if ($stock > 0) {
            app(StockService::class)->record(['product_id' => $p->id, 'location_id' => $this->store->id, 'direction' => 'in', 'type' => 'in', 'quantity' => $stock]);
        }
        return $p->fresh();
    }

    protected function sell(User $user, Product $p, float $qty, array $payments = [], array $extra = [])
    {
        return $this->actingAs($user)->withSession([TwoFactor::SESSION_KEY => true])->postJson('/pos/checkout', [
            'items' => [['product_id' => $p->id, 'qty' => $qty]],
            'payments' => $payments ?: [['method' => 'cash', 'amount' => $qty * (float) $p->price]],
        ] + $extra);
    }

    // ------------------------------------------------------------------

    public function test_balances_follow_the_ledger_by_location_type(): void
    {
        $p = $this->product(100);
        $this->assertSame(100.0, $this->bal->onHand($p->id));
        $this->assertSame(100.0, (float) $p->stock);

        $this->sell($this->admin, $p, 3)->assertOk();
        $this->assertSame(97.0, $this->bal->onHand($p->id));
        $this->assertSame(97.0, (float) $p->fresh()->stock);

        // move 5 to quarantine: no longer sellable
        $q = app(LocationService::class)->typed(Install::branchId(), 'quarantine');
        app(StockService::class)->transfer($p->id, null, $this->store->id, $q->id, 5);
        $row = $this->bal->row($p->id);
        $this->assertSame(92.0, $row->on_hand);
        $this->assertSame(5.0, $row->quarantined);
        $this->assertSame(92.0, (float) $p->fresh()->stock);

        // rebuild from the ledger gives the same numbers
        DB::table('inventory_balances')->where('product_id', $p->id)->update(['on_hand' => 0, 'quarantined' => 0]);
        $this->bal->rebuild($p->id);
        $row = $this->bal->row($p->id);
        $this->assertSame(92.0, $row->on_hand);
        $this->assertSame(5.0, $row->quarantined);
    }

    public function test_reserved_stock_cannot_be_sold(): void
    {
        $p = $this->product(100);
        $this->bal->reserve($p->id, null, 90, 'transfer', 'TRF-TEST-1');
        $this->assertSame(10.0, $this->bal->available($p->id));

        $this->sell($this->admin, $p, 20)->assertStatus(422)->assertJsonFragment(['success' => false]);
        $this->sell($this->admin, $p, 10)->assertOk();
        $this->assertSame(0.0, $this->bal->available($p->id));

        // can't promise more than is free
        $this->expectException(\App\Exceptions\InsufficientStockException::class);
        try {
            $this->bal->reserve($p->id, null, 1, 'transfer', 'TRF-TEST-2');
        } finally {
            $this->assertSame(1, $this->bal->release('transfer', 'TRF-TEST-1'));
            $this->assertSame(90.0, $this->bal->available($p->id));
            $this->assertSame(0.0, $this->bal->row($p->id)->reserved);
        }
    }

    public function test_tills_one_shift_each_and_sales_record_the_till(): void
    {
        $back = StockLocation::create(['name' => 'Front shop', 'code' => 'FRONT-' . uniqid(), 'type' => 'store', 'is_active' => true]);
        $t1 = CashRegister::create(['code' => 'T1', 'name' => 'Till 1', 'status' => 'active']);
        $t2 = CashRegister::create(['code' => 'T2', 'name' => 'Till 2', 'status' => 'active', 'stock_location_id' => $back->id]);
        $this->assertSame(Install::branchId(), $t2->branch_id);

        $as = fn ($u) => $this->actingAs($u)->withSession([TwoFactor::SESSION_KEY => true]);
        $as($this->cashier)->postJson('/pos/shifts/open', ['opening_float' => 5000])->assertStatus(422)->assertJson(['choose_register' => true]);
        $as($this->cashier)->postJson('/pos/shifts/open', ['opening_float' => 5000, 'cash_register_id' => $t2->id])->assertOk();
        $as($this->admin)->postJson('/pos/shifts/open', ['opening_float' => 1000, 'cash_register_id' => $t2->id])
            ->assertStatus(422)->assertJsonFragment(['success' => false]);

        // T2 sells from its own location
        $p = $this->product(0);
        app(StockService::class)->record(['product_id' => $p->id, 'location_id' => $back->id, 'direction' => 'in', 'quantity' => 10]);
        $res = $this->sell($this->cashier, $p, 2)->assertOk();
        $order = Order::find($res->json('order_id'));
        $this->assertSame($t2->id, $order->cash_register_id);
        $this->assertSame($t2->id, OrderPayment::where('order_id', $order->id)->value('cash_register_id'));
        $this->assertSame(8.0, app(StockService::class)->balance($p->id, null, $back->id));

        // the till PC remembers its till
        $as($this->admin)->postJson('/pos/register', ['register_id' => $t1->id])->assertOk()->assertCookie(CashRegister::COOKIE);
        $as($this->admin)->get('/pos')->assertOk()->assertSee('Choose till');
        $as($this->admin)->withCookie(CashRegister::COOKIE, (string) $t1->id)->get('/pos')->assertOk()->assertSee('Till T1');
    }

    public function test_transfers_wait_for_confirmation(): void
    {
        $p = $this->product(50, 1000);
        $customer = Customer::create(['first_name' => 'Bola', 'last_name' => 'A', 'phone_number' => '0803' . rand(1000000, 9999999), 'customer_type' => 'retail']);

        // cashier ticks "I saw it" but has no permission → still pending
        $res = $this->sell($this->cashier, $p, 2, [['method' => 'transfer', 'amount' => 2000, 'reference' => 'NIP123', 'confirmed' => true]], ['customer_id' => $customer->id])->assertOk();
        $this->assertEquals(2000, $res->json('pending_payments'));
        $order = Order::find($res->json('order_id'));
        $this->assertSame('pending', $order->payment_status);
        $pay = OrderPayment::where('order_id', $order->id)->first();
        $this->assertSame('pending', $pay->status);

        // supervisor with permission confirming at the till → confirmed straight away
        $res2 = $this->sell($this->admin, $p, 1, [['method' => 'transfer', 'amount' => 1000, 'confirmed' => true]])->assertOk();
        $this->assertSame('paid', Order::find($res2->json('order_id'))->payment_status);

        // confirmation page
        $as = $this->actingAs($this->admin)->withSession([TwoFactor::SESSION_KEY => true]);
        $as->get('/pos/payments')->assertOk()->assertSee($order->id);
        $this->actingAs($this->cashier)->withSession([TwoFactor::SESSION_KEY => true])->get('/pos/payments')->assertForbidden();
        $as = $this->actingAs($this->admin)->withSession([TwoFactor::SESSION_KEY => true]);

        // not received → order unpaid, customer owes it
        $as->postJson("/pos/payments/{$pay->id}/fail", ['reason' => 'fake alert'])->assertOk();
        $this->assertSame('failed', $pay->fresh()->status);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertEquals(2000, (float) $customer->fresh()->outstanding_balance);
        $as->postJson("/pos/payments/{$pay->id}/confirm")->assertStatus(422);

        // a later one confirmed
        $res3 = $this->sell($this->cashier, $p, 1, [['method' => 'transfer', 'amount' => 1000]])->assertOk();
        $pay3 = OrderPayment::where('order_id', $res3->json('order_id'))->first();
        $this->actingAs($this->admin)->postJson("/pos/payments/{$pay3->id}/confirm", ['reference' => 'NIP999'])->assertOk();
        $this->assertSame('paid', Order::find($res3->json('order_id'))->payment_status);
        $this->assertSame('NIP999', $pay3->fresh()->reference);
        $this->assertDatabaseHas('audit_logs', ['event' => 'payment_failed']);
    }

    public function test_card_reference_can_be_required(): void
    {
        $p = $this->product(10, 1000);
        SystemSetting::set('card_needs_terminal_ref', true);
        $this->sell($this->admin, $p, 1, [['method' => 'card', 'amount' => 1000]])->assertStatus(422);
        $this->sell($this->admin, $p, 1, [['method' => 'card', 'amount' => 1000, 'reference' => 'RRN 001122']])->assertOk();
    }

    public function test_till_and_back_office_pages_use_no_internet_assets(): void
    {
        CashRegister::create(['code' => 'T1', 'name' => 'Till 1', 'status' => 'active']);
        $as = $this->actingAs($this->admin)->withSession([TwoFactor::SESSION_KEY => true]);
        $html = $as->get('/pos')->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('#(src|href)="https?://(?!127\.0\.0\.1|localhost)[^"]+\.(js|css)#', $html);
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringContainsString('pos-sw.js', $html);

        foreach (['vendor/fonts/fonts.css', 'vendor/sweetalert2/sweetalert2.all.min.js', 'vendor/chartjs/chart.umd.min.js', 'vendor/axios/axios.min.js', 'pos-sw.js'] as $f) {
            $this->assertFileExists(public_path($f));
        }
        $as->get('/pos/registers')->assertOk()->assertSee('Till 1');
        $as->post('/pos/registers', ['code' => 't9', 'name' => 'Till 9', 'status' => 'active'])->assertRedirect();
        $this->assertDatabaseHas('cash_registers', ['code' => 'T9', 'branch_id' => Install::branchId()]);
        $as->get('/login');
    }
}
