<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PriceChangeBatch;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockAdjustment;
use App\Models\StockLocation;
use App\Models\SystemSetting;
use App\Models\Unit;
use App\Models\User;
use App\Services\BarcodeGenerator;
use App\Services\LoginPolicy;
use App\Services\PricingService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * End-to-end checks for products, packs, pricing, POS, stock approval, login control and audit.
 *
 * Needs MySQL (the schema uses MySQL triggers):
 *   1) create an empty test database and migrate it:  DB_DATABASE=vitemart_test php artisan migrate
 *   2) DB_CONNECTION=mysql DB_DATABASE=vitemart_test ./vendor/bin/pest --filter=SupermarketUpgradeTest
 */
class SupermarketUpgradeTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $cashier;
    protected User $supervisor;
    protected Unit $piece;
    protected Unit $carton;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Requires MySQL.');
        }
        Cache::flush();

        foreach (['View pos', 'View product', 'Create product', 'Update product', 'dashboard', 'View sale'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('Super Admin', 'web');
        $cashierRole = Role::findOrCreate('Cashier', 'web');
        $cashierRole->givePermissionTo(['View pos', 'View product', 'Create stock adjustment']);
        $supRole = Role::findOrCreate('Supervisor', 'web');
        $supRole->givePermissionTo(['View pos', 'Give pos discount', 'Void pos sale', 'Process pos return', 'Sell below cost', 'Approve stock adjustment']);

        $mk = fn ($first, $email) => User::create(['first_name' => $first, 'last_name' => 'Test', 'username' => $email, 'email' => $email, 'password' => Hash::make('password')]);
        $this->admin = $mk('Ada', 'admin@test.com');
        $this->admin->assignRole('Super Admin');
        $this->cashier = $mk('Cate', 'cashier@test.com');
        $this->cashier->assignRole('Cashier');
        $this->supervisor = $mk('Sam', 'sup@test.com');
        $this->supervisor->assignRole('Supervisor');
        $this->supervisor->forceFill(['pos_pin' => Hash::make('4321')])->save();

        StockLocation::create(['name' => 'Main Store', 'code' => 'MAIN', 'is_default' => true, 'is_active' => true]);
        $this->piece = Unit::create(['name' => 'Piece', 'short_name' => 'pc', 'is_default' => true, 'is_active' => true]);
        $this->carton = Unit::create(['name' => 'Carton', 'short_name' => 'ctn', 'is_active' => true]);

        SystemSetting::setMany(['max_discount_percent' => 10, 'block_sale_below_cost' => true, 'adjustment_requires_approval' => true]);
    }

    protected function makeMilk(): Product
    {
        $this->actingAs($this->admin);
        $res = $this->postJson('/products', [
            'title' => 'Peak Milk 400g',
            'product_type' => 'simple',
            'price' => 500,
            'cost_price' => 400,
            'wholesale_price' => 450,
            'base_unit_id' => $this->piece->id,
            'packs' => [[
                'unit_id' => $this->carton->id, 'quantity_per_unit' => 24, 'price' => 11500, 'wholesale_price' => 10800, 'auto_barcode' => 1,
            ]],
            'opening_stock' => 100,
            'reorder_level' => 20,
        ]);
        $res->assertCreated()->assertJson(['success' => true]);
        return Product::where('title', 'Peak Milk 400g')->firstOrFail();
    }

    public function test_barcodes_are_valid_unique_ean13(): void
    {
        $a = BarcodeGenerator::next();
        $b = BarcodeGenerator::next();
        $this->assertTrue(BarcodeGenerator::isValidEan13($a));
        $this->assertTrue(BarcodeGenerator::isValidEan13($b));
        $this->assertNotSame($a, $b);
        $this->assertStringStartsWith('20', $a);
        $this->assertSame('4006381333931', '400638133393' . BarcodeGenerator::checkDigit('400638133393'));
    }

    public function test_product_with_carton_pack_and_opening_stock(): void
    {
        $milk = $this->makeMilk();
        $this->assertTrue(BarcodeGenerator::isValidEan13($milk->barcode));
        $this->assertNotEmpty($milk->sku);
        $this->assertEquals(100, (float) $milk->fresh()->stock); // admin → auto approved
        $carton = $milk->packs()->where('is_base', false)->first();
        $this->assertEquals(24, $carton->quantity_per_unit);
        $this->assertTrue(BarcodeGenerator::isValidEan13($carton->barcode));
        $this->assertDatabaseHas('price_histories', ['product_id' => $milk->id, 'field' => 'price']);
        $this->assertTrue(AuditLog::where('auditable_type', Product::class)->where('event', 'created')->exists());
    }

    public function test_pricing_quotes_packs_wholesale_and_promo(): void
    {
        $milk = $this->makeMilk();
        $pricing = app(PricingService::class);
        $carton = $milk->packs()->where('is_base', false)->first();

        $this->assertEquals(500, $pricing->quote($milk, null, $milk->basePack())['price']);
        $this->assertEquals(11500, $pricing->quote($milk, null, $carton)['price']);

        $wholesale = Customer::create(['first_name' => 'Big', 'last_name' => 'Buyer', 'phone_number' => '0800', 'customer_type' => 'wholesale', 'status' => 'active', 'gender' => 'male']);
        $this->assertEquals(450, $pricing->quote($milk, null, $milk->basePack(), $wholesale)['price']);
        $this->assertEquals(10800, $pricing->quote($milk, null, $carton, $wholesale)['price']);

        $milk->update(['promo_price' => 450, 'promo_starts_at' => now()->subDay(), 'promo_ends_at' => now()->addDay()]);
        $milk->refresh();
        $this->assertEquals(450, $pricing->quote($milk, null, $milk->basePack())['price']);
        $this->assertEquals(10350, $pricing->quote($milk, null, $carton)['price']); // same 10% off the carton
    }

    public function test_variable_product_keeps_variation_ids_and_stock_on_edit(): void
    {
        $this->actingAs($this->admin);
        $res = $this->postJson('/products', [
            'title' => 'Coca-Cola', 'product_type' => 'variable', 'price' => 300, 'cost_price' => 220,
            'base_unit_id' => $this->piece->id,
            'attributes_input' => [['name' => 'Size', 'values' => '35cl, 50cl']],
            'variations' => [
                ['name' => '35cl', 'attributes' => ['Size' => '35cl'], 'price' => 300, 'cost_price' => 220, 'opening_stock' => 40],
                ['name' => '50cl', 'attributes' => ['Size' => '50cl'], 'price' => 400, 'cost_price' => 300, 'opening_stock' => 30],
            ],
        ]);
        $res->assertCreated();
        $coke = Product::where('title', 'Coca-Cola')->first();
        $vars = $coke->variations()->orderBy('id')->get();
        $this->assertCount(2, $vars);
        $this->assertEquals(40, (float) $vars[0]->stock);
        $this->assertEquals(30, (float) $vars[1]->stock);
        $this->assertEquals(70, (float) $coke->fresh()->stock);
        $this->assertCount(1, $coke->attributes); // attributes are now saved

        // Edit price of one variation – ids and stock must survive
        $this->putJson("/products/{$coke->id}", [
            'title' => 'Coca-Cola', 'product_type' => 'variable', 'price' => 300, 'cost_price' => 220,
            'base_unit_id' => $this->piece->id,
            'variations' => [
                ['id' => $vars[0]->id, 'name' => '35cl', 'attributes' => ['Size' => '35cl'], 'price' => 320, 'barcode' => $vars[0]->barcode],
                ['id' => $vars[1]->id, 'name' => '50cl', 'attributes' => ['Size' => '50cl'], 'price' => 400, 'barcode' => $vars[1]->barcode],
            ],
        ])->assertOk();
        $v0 = $vars[0]->fresh();
        $this->assertNotNull($v0);
        $this->assertEquals(320, (float) $v0->price);
        $this->assertEquals(40, (float) $v0->stock);
        $this->assertDatabaseHas('price_histories', ['product_variation_id' => $v0->id, 'old_value' => 300, 'new_value' => 320]);
    }

    public function test_pos_scan_carton_and_checkout_with_change(): void
    {
        $milk = $this->makeMilk();
        $carton = $milk->packs()->where('is_base', false)->first();

        $this->actingAs($this->cashier);
        $look = $this->getJson('/pos/lookup?q=' . $carton->barcode)->assertOk()->json();
        $this->assertTrue($look['exact']);
        $this->assertEquals($carton->id, $look['items'][0]['pack_id']);
        $this->assertEquals(11500, $look['items'][0]['price']);

        $res = $this->postJson('/pos/checkout', [
            'items' => [
                ['product_id' => $milk->id, 'pack_id' => $carton->id, 'qty' => 2, 'price' => 1], // tampered price must be refused
            ],
            'payments' => [['method' => 'cash', 'amount' => 25000]],
        ]);
        $res->assertStatus(403)->assertJson(['needs_authorization' => 'price']);

        $res = $this->postJson('/pos/checkout', [
            'items' => [
                ['product_id' => $milk->id, 'pack_id' => $carton->id, 'qty' => 2],
                ['product_id' => $milk->id, 'pack_id' => $milk->basePack()->id, 'qty' => 3],
            ],
            'payments' => [['method' => 'cash', 'amount' => 25000]],
            'client_ref' => 'abc-1',
        ])->assertOk()->json();

        $this->assertEquals(24500, $res['total']);
        $this->assertEquals(500, $res['change']);
        $this->assertEquals(100 - 48 - 3, (float) $milk->fresh()->stock);

        $order = Order::find($res['order_id']);
        $this->assertEquals('pos', $order->channel);
        $this->assertEquals(51 * 400, (float) $order->cost_total);
        $this->assertEquals(24500, (float) DB::table('order_payments')->where('order_id', $order->id)->sum('amount'));

        // Same client_ref → no duplicate sale
        $again = $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $milk->id, 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 500]],
            'client_ref' => 'abc-1',
        ])->json();
        $this->assertEquals($order->id, $again['order_id']);
        $this->assertEquals(49, (float) $milk->fresh()->stock);
    }

    public function test_split_payment_and_insufficient_payment(): void
    {
        $milk = $this->makeMilk();
        $this->actingAs($this->cashier);
        $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $milk->id, 'qty' => 4]],
            'payments' => [['method' => 'cash', 'amount' => 1000]],
        ])->assertStatus(422);

        $res = $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $milk->id, 'qty' => 4]],
            'payments' => [['method' => 'card', 'amount' => 1500], ['method' => 'cash', 'amount' => 1000]],
        ])->assertOk()->json();
        $this->assertEquals('split', Order::find($res['order_id'])->payment_method);
        $this->assertEquals(500, $res['change']);
    }

    public function test_discount_limit_needs_supervisor_pin(): void
    {
        $milk = $this->makeMilk();
        $this->actingAs($this->cashier);
        $payload = [
            'items' => [['product_id' => $milk->id, 'qty' => 2]],
            'discount_type' => 'percent', 'discount_value' => 15,
            'payments' => [['method' => 'cash', 'amount' => 1000]],
        ];
        $this->postJson('/pos/checkout', $payload)->assertStatus(403)->assertJson(['needs_authorization' => 'discount']);

        $this->postJson('/pos/authorize', ['pin' => '1111', 'action' => 'discount'])->assertStatus(422);
        $token = $this->postJson('/pos/authorize', ['pin' => '4321', 'action' => 'discount'])->assertOk()->json('token');

        $res = $this->postJson('/pos/checkout', $payload + ['auth_token' => $token])->assertOk()->json();
        $this->assertEquals(850, $res['total']);
    }

    public function test_selling_below_cost_is_blocked(): void
    {
        $milk = $this->makeMilk();
        $this->actingAs($this->cashier);
        $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $milk->id, 'qty' => 1, 'discount_type' => 'fixed', 'discount_value' => 40]],
            'payments' => [['method' => 'cash', 'amount' => 500]],
        ])->assertOk(); // 8% – inside limit, still above cost

        SystemSetting::set('max_discount_percent', 50);
        $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $milk->id, 'qty' => 1, 'discount_type' => 'percent', 'discount_value' => 30]],
            'payments' => [['method' => 'cash', 'amount' => 500]],
        ])->assertStatus(403)->assertJson(['needs_authorization' => 'below_cost']);
    }

    public function test_variation_sale_reduces_variation_stock_and_out_of_stock_is_refused(): void
    {
        $this->actingAs($this->admin);
        $this->postJson('/products', [
            'title' => 'Indomie', 'product_type' => 'variable', 'price' => 200, 'cost_price' => 150,
            'base_unit_id' => $this->piece->id,
            'variations' => [
                ['name' => 'Chicken', 'attributes' => ['Flavour' => 'Chicken'], 'price' => 200, 'opening_stock' => 5],
                ['name' => 'Onion', 'attributes' => ['Flavour' => 'Onion'], 'price' => 210, 'opening_stock' => 1],
            ],
        ])->assertCreated();
        $p = Product::where('title', 'Indomie')->first();
        [$chicken, $onion] = $p->variations()->orderBy('id')->get();

        $this->actingAs($this->cashier);
        $look = $this->getJson('/pos/lookup?q=' . $onion->barcode)->json();
        $this->assertEquals($onion->id, $look['items'][0]['variation_id']);
        $this->assertEquals(210, $look['items'][0]['price']);

        $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $p->id, 'variation_id' => $chicken->id, 'qty' => 3]],
            'payments' => [['method' => 'cash', 'amount' => 600]],
        ])->assertOk();
        $this->assertEquals(2, (float) $chicken->fresh()->stock);
        $this->assertEquals(1, (float) $onion->fresh()->stock);

        $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $p->id, 'variation_id' => $onion->id, 'qty' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 420]],
        ])->assertStatus(422)->assertJsonFragment(['success' => false]);
    }

    public function test_void_and_partial_return_restore_stock(): void
    {
        $milk = $this->makeMilk();
        $this->actingAs($this->cashier);
        $o1 = $this->postJson('/pos/checkout', ['items' => [['product_id' => $milk->id, 'qty' => 10]], 'payments' => [['method' => 'cash', 'amount' => 5000]]])->json('order_id');
        $this->assertEquals(90, (float) $milk->fresh()->stock);

        $this->postJson("/pos/void-order/$o1", ['reason' => 'Customer left'])->assertStatus(403);
        $token = $this->postJson('/pos/authorize', ['pin' => '4321', 'action' => 'void'])->json('token');
        $this->postJson("/pos/void-order/$o1", ['reason' => 'Customer left', 'auth_token' => $token])->assertOk();
        $this->assertEquals(100, (float) $milk->fresh()->stock);
        $this->assertEquals('cancelled', Order::find($o1)->status);

        $o2 = $this->postJson('/pos/checkout', ['items' => [['product_id' => $milk->id, 'qty' => 4]], 'payments' => [['method' => 'cash', 'amount' => 2000]]])->json('order_id');
        $itemId = Order::find($o2)->items()->first()->id;
        $this->actingAs($this->supervisor);
        $this->postJson("/pos/return/$o2", ['lines' => [$itemId => 1], 'reason' => 'Expired', 'restock' => true])
            ->assertOk()->assertJson(['amount' => 500]);
        $this->assertEquals(97, (float) $milk->fresh()->stock);
        $this->postJson("/pos/return/$o2", ['lines' => [$itemId => 5], 'reason' => 'Too many'])->assertStatus(422);
    }

    public function test_stock_adjustment_requires_approval(): void
    {
        $milk = $this->makeMilk();
        $this->actingAs($this->cashier);
        $res = $this->postJson('/stock-adjustments', [
            'type' => 'damage', 'location_id' => StockLocation::first()->id, 'reason' => 'Damaged',
            'items' => [['product_id' => $milk->id, 'quantity' => 5]],
        ])->assertOk()->json();
        $this->assertEquals('pending', $res['status']);
        $this->assertEquals(100, (float) $milk->fresh()->stock);

        $adj = StockAdjustment::latest('id')->first();
        $this->postJson("/stock-adjustments/{$adj->id}/approve")->assertStatus(403);

        $this->actingAs($this->supervisor);
        $this->postJson("/stock-adjustments/{$adj->id}/approve")->assertOk();
        $this->assertEquals(95, (float) $milk->fresh()->stock);
        $this->assertDatabaseHas('stocks', ['reference_number' => $adj->reference, 'type' => 'damage']);

        // Stock count: counted 2 cartons + nothing else = 48; sales after counting are not lost
        $this->actingAs($this->cashier);
        $this->postJson('/stock-adjustments', [
            'type' => 'count', 'location_id' => StockLocation::first()->id, 'reason' => 'Stock count correction',
            'items' => [['product_id' => $milk->id, 'pack_id' => $milk->packs()->where('is_base', false)->first()->id, 'quantity' => 2]],
        ])->assertOk();
        $count = StockAdjustment::latest('id')->first();
        $this->assertEquals(-47, $count->items->first()->quantity_change);
        $this->postJson('/pos/checkout', ['items' => [['product_id' => $milk->id, 'qty' => 1]], 'payments' => [['method' => 'cash', 'amount' => 500]]])->assertOk();
        $this->actingAs($this->supervisor);
        $this->postJson("/stock-adjustments/{$count->id}/approve")->assertOk();
        $this->assertEquals(47, (float) $milk->fresh()->stock);

        // Reject flow
        $this->actingAs($this->cashier);
        $this->postJson('/stock-adjustments', ['type' => 'adjustment', 'location_id' => StockLocation::first()->id, 'reason' => 'Other',
            'items' => [['product_id' => $milk->id, 'mode' => 'add', 'quantity' => 1000]]])->assertOk();
        $big = StockAdjustment::latest('id')->first();
        $this->actingAs($this->supervisor);
        $this->postJson("/stock-adjustments/{$big->id}/reject", ['reason' => 'Typo'])->assertOk();
        $this->assertEquals(47, (float) $milk->fresh()->stock);
    }

    public function test_bulk_price_change_preview_apply_and_revert(): void
    {
        $milk = $this->makeMilk();
        $this->actingAs($this->admin);
        $payload = ['product_ids' => [$milk->id], 'field' => 'price', 'method' => 'percent', 'direction' => 'increase',
            'value' => 10, 'rounding' => 50, 'include_packs' => true];
        $prev = $this->postJson('/price-control/preview', $payload)->assertOk()->json();
        $this->assertEquals(2, $prev['count']); // product + carton
        $this->assertEquals(550, collect($prev['rows'])->firstWhere('kind', 'product')['new']);
        $this->assertEquals(12650, collect($prev['rows'])->firstWhere('kind', 'pack')['new']);

        $this->postJson('/price-control/apply', $payload)->assertOk();
        $this->assertEquals(550, (float) $milk->fresh()->price);
        $batch = PriceChangeBatch::latest('id')->first();
        $this->postJson("/price-control/batches/{$batch->id}/revert")->assertOk();
        $this->assertEquals(500, (float) $milk->fresh()->price);
        $this->assertEquals(11500, (float) ProductUnit::where('product_id', $milk->id)->where('is_base', false)->first()->price);
        $this->postJson("/price-control/batches/{$batch->id}/revert")->assertStatus(422);
    }

    public function test_scale_barcode_sells_by_weight(): void
    {
        $this->actingAs($this->admin);
        $kg = Unit::create(['name' => 'Kilogram', 'short_name' => 'kg', 'is_active' => true]);
        $this->postJson('/products', ['title' => 'Beef', 'product_type' => 'simple', 'price' => 6000, 'cost_price' => 4500,
            'plu_code' => '123', 'is_weighted' => 1, 'base_unit_id' => $kg->id, 'opening_stock' => 20])->assertCreated();
        $code = BarcodeGenerator::makeScaleBarcode('123', 1.25);
        $this->actingAs($this->cashier);
        $look = $this->getJson('/pos/lookup?q=' . $code)->json();
        $this->assertTrue($look['exact']);
        $this->assertEquals(1.25, $look['items'][0]['qty']);
        $res = $this->postJson('/pos/checkout', ['items' => [['product_id' => $look['items'][0]['product_id'], 'qty' => 1.25]],
            'payments' => [['method' => 'cash', 'amount' => 7500]]])->assertOk()->json();
        $this->assertEquals(7500, $res['total']);
        $this->assertEquals(18.75, (float) Product::where('title', 'Beef')->first()->stock);
    }

    public function test_login_hours_and_force_logout(): void
    {
        $policy = app(LoginPolicy::class);
        SystemSetting::setMany(['login_hours_enabled' => true, 'login_start_time' => '08:00', 'login_end_time' => '18:00', 'login_days' => [1, 2, 3, 4, 5, 6]]);

        $this->travelTo(now()->next('Monday')->setTime(9, 0));
        $this->assertNull($policy->denialReason($this->cashier));
        $this->travelTo(now()->next('Monday')->setTime(19, 0));
        $this->assertNotNull($policy->denialReason($this->cashier));
        $this->assertNull($policy->denialReason($this->admin)); // Super Admin never locked out
        $this->travelTo(now()->next('Sunday')->setTime(10, 0));
        $this->assertNotNull($policy->denialReason($this->cashier));

        // Per-user override
        $this->cashier->update(['login_rule' => 'always']);
        $this->assertNull($policy->denialReason($this->cashier->fresh()));
        $this->cashier->update(['login_rule' => 'inherit', 'is_active' => false]);
        $this->assertStringContainsString('disabled', $policy->denialReason($this->cashier->fresh()));
        $this->travelBack();

        // Login form refuses a disabled user and audits it
        $this->post('/login', ['email' => 'cashier@test.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertTrue(AuditLog::where('event', 'login_blocked')->exists());

        // Force logout: an active session is ended on the next request
        SystemSetting::set('login_hours_enabled', false);
        $this->cashier->update(['is_active' => true]);
        $this->post('/login', ['email' => 'cashier@test.com', 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($this->cashier);
        $this->travel(5)->seconds();
        app(LoginPolicy::class)->logoutUser($this->cashier->fresh());
        $this->travel(5)->seconds();
        $this->app['auth']->forgetGuards(); // next request loads the user fresh, as a real request would
        $this->get('/pos')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertTrue(AuditLog::where('event', 'force_logout')->exists());
    }

    public function test_pages_render(): void
    {
        $milk = $this->makeMilk();
        $this->actingAs($this->admin);
        $this->postJson('/pos/checkout', ['items' => [['product_id' => $milk->id, 'qty' => 2]], 'payments' => [['method' => 'cash', 'amount' => 1000]]])->assertOk();
        $order = Order::latest('created_at')->first();

        foreach (['/dashboard', '/products', '/products/create', "/products/{$milk->id}/edit", '/pos', '/price-control',
                     '/price-control/history', '/stock-adjustments', '/stock-adjustments/create?type=count', '/security',
                     '/audit-trail', '/pos/transactions', '/pos/shifts', "/pos/receipt/{$order->id}",
                     "/products/labels?ids={$milk->id}&include_packs=1"] as $url) {
            $this->get($url)->assertOk();
        }
        $adj = StockAdjustment::first();
        $this->get("/stock-adjustments/{$adj->id}")->assertOk();
    }
}
