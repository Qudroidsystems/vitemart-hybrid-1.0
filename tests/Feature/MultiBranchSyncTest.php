<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\FieldPolicy;
use App\Models\Order;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockLocation;
use App\Models\SystemSetting;
use App\Models\Unit;
use App\Models\User;
use App\Services\Catalogue\PriceListResolver;
use App\Services\Catalogue\ProductPayload;
use App\Services\Inventory\InventoryBalances;
use App\Services\MultiBranch\EnrollmentService;
use App\Services\MultiBranch\Install;
use App\Services\MultiBranch\LocationService;
use App\Services\MultiBranch\Signature;
use App\Services\Security\TwoFactor;
use App\Services\StockService;
use App\Services\Sync\Feed;
use App\Services\Sync\FeedApplier;
use App\Services\Sync\Inbox;
use App\Services\Sync\Outbox;
use App\Services\Sync\SyncContext;
use App\Services\Sync\SyncRunner;
use App\Services\Sync\SyncState;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 5: outbox (branch → HQ), inbox (HQ applies), feed (HQ → branches), feed applier, commands, sync pages.
 * Both sides are exercised in one database by switching the installation mode.
 */
class MultiBranchSyncTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $home;
    protected User $admin;
    protected Unit $piece;

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
        app(LocationService::class)->ensureFor($this->home);
        foreach (['dashboard', 'View pos', 'View sync status', 'Manage sync', 'Distribute products'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        Role::findOrCreate('Super Admin', 'web');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->admin = User::create(['first_name' => 'Ada', 'last_name' => 'Sync', 'username' => 'sync-admin', 'email' => 'sync-admin@test.com', 'password' => Hash::make('password')]);
        $this->admin->assignRole('Super Admin');
        $this->piece = Unit::firstOrCreate(['name' => 'Piece'], ['short_name' => 'pc', 'is_active' => true]);
        SystemSetting::setMany(['allow_negative_stock' => false, 'transfer_needs_confirmation' => false]);
        DB::table('sync_outbox')->delete();
        DB::table('sync_dirty')->delete();
    }

    protected function setMode(string $mode, array $extra = []): void
    {
        DB::table('installation')->update(['mode' => $mode] + $extra);
        Install::refresh();
        app()->forgetScopedInstances();
    }

    protected function product(string $title = 'Sync Rice 5kg', float $price = 9000, string $owner = 'central'): Product
    {
        $p = (new Product())->forceFill(['title' => $title, 'price' => $price, 'cost_price' => $price * 0.8, 'product_type' => 'simple',
            'is_active' => true, 'reorder_level' => 5, 'owner' => $owner, 'stock' => 0]);
        $p->save();
        ProductUnit::create(['product_id' => $p->id, 'unit_id' => $this->piece->id, 'quantity_per_unit' => 1, 'is_base' => true, 'is_sellable' => true]);
        return $p->fresh();
    }

    // ------------------------------------------------------------------ branch: outbox

    public function test_branch_records_sales_and_stock_in_the_outbox_in_the_same_transaction(): void
    {
        $this->setMode('branch');
        $p = $this->product();
        $loc = app(LocationService::class)->storeFor($this->home->id);
        app(StockService::class)->record(['product_id' => $p->id, 'location_id' => $loc->id, 'direction' => 'in', 'quantity' => 20]);
        $this->assertSame(1, DB::table('sync_outbox')->where('entity', 'stock')->count());
        $this->assertSame(0, DB::table('sync_outbox')->where('entity', 'product')->count()); // head-office product: not sent up

        $this->actingAs($this->admin)->withSession([TwoFactor::SESSION_KEY => true])
            ->postJson('/pos/checkout', ['items' => [['product_id' => $p->id, 'qty' => 2]], 'payments' => [['method' => 'cash', 'amount' => 18000]]])->assertOk();
        foreach (['order', 'order_item', 'order_payment'] as $e) {
            $this->assertSame(1, DB::table('sync_outbox')->where('entity', $e)->where('status', 'pending')->count(), $e);
        }
        $order = json_decode(DB::table('sync_outbox')->where('entity', 'order')->value('payload'), true);
        $this->assertArrayNotHasKey('branch_id', $order);
        $this->assertArrayHasKey('user_id__uid', $order);
        $this->assertSame($this->admin->uid, $order['user_id__uid']);
        $this->assertMatchesRegularExpression('/^[A-Z]+-\d{6}-\d{6}$/', $order['id']); // receipt number travels as-is

        // a failed sale leaves nothing behind
        $before = DB::table('sync_outbox')->count();
        $this->actingAs($this->admin)->postJson('/pos/checkout', ['items' => [['product_id' => $p->id, 'qty' => 999]]])->assertStatus(422);
        $this->assertSame($before, DB::table('sync_outbox')->count());

        // nothing is recorded while sync data is being applied, nor on a single-store install
        SyncContext::run(fn () => Customer::create(['first_name' => 'X', 'last_name' => 'Y', 'phone_number' => '0809' . rand(1000000, 9999999), 'customer_type' => 'retail']));
        $this->assertSame(0, DB::table('sync_outbox')->where('entity', 'customer')->count());
        $this->setMode('standalone');
        Customer::create(['first_name' => 'Z', 'last_name' => 'Y', 'phone_number' => '0807' . rand(1000000, 9999999), 'customer_type' => 'retail']);
        $this->assertSame(0, DB::table('sync_outbox')->where('entity', 'customer')->count());
    }

    public function test_repeated_saves_send_only_the_latest_version(): void
    {
        $this->setMode('branch');
        $c = Customer::create(['first_name' => 'Ada', 'last_name' => 'One', 'phone_number' => '0801' . rand(1000000, 9999999), 'customer_type' => 'retail']);
        $c->update(['last_name' => 'Two']);
        $c->update(['last_name' => 'Three']);
        $rows = DB::table('sync_outbox')->where('entity', 'customer')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('Three', json_decode($rows[0]->payload, true)['last_name']);
    }

    // ------------------------------------------------------------------ head office: inbox

    protected function ikj(): Branch
    {
        $b = Branch::create(['code' => 'IKJ', 'number' => 2, 'name' => 'Ikeja', 'type' => 'store', 'status' => 'active']);
        app(LocationService::class)->ensureFor($b);
        return $b;
    }

    protected function event(string $entity, array $payload, ?string $uid = null): array
    {
        return ['event_uid' => (string) Str::ulid(), 'entity' => $entity, 'entity_uid' => $uid ?? ($payload['uid'] ?? (string) Str::ulid()), 'op' => 'upsert', 'payload' => $payload];
    }

    public function test_head_office_applies_a_branch_sale_once_and_tracks_branch_stock(): void
    {
        $this->setMode('central');
        $ikj = $this->ikj();
        $p = $this->product();
        $base = ProductUnit::where('product_id', $p->id)->where('is_base', true)->first();
        $locUid = (string) Str::ulid();
        $orderId = 'IKJ-261011-' . rand(100000, 999999);
        $now = now()->toDateTimeString();

        $events = [
            $this->event('stock_location', ['uid' => $locUid, 'name' => 'Ikeja shop floor', 'code' => 'MAIN', 'type' => 'store', 'is_default' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]),
            $this->event('stock', ['uid' => (string) Str::ulid(), 'product_id__uid' => $p->uid, 'stock_location_id__uid' => $locUid, 'user_id__uid' => null,
                'product_variation_id__uid' => null, 'destination_location_id__uid' => null, 'type' => 'in', 'quantity' => 50, 'transaction_date' => $now, 'created_at' => $now, 'updated_at' => $now]),
            $this->event('order', ['id' => $orderId, 'uid' => (string) Str::ulid(), 'customer_id__uid' => null, 'user_id__uid' => $this->admin->uid,
                'pos_shift_id__uid' => null, 'cash_register_id__uid' => null, 'voided_by__uid' => null, 'status' => 'completed', 'total_amount' => 18000,
                'discount_amount' => 0, 'shipping_cost' => 0, 'tax_cost' => 0, 'order_date' => $now, 'payment_method' => 'cash', 'payment_status' => 'paid',
                'billing_address_same_as_shipping' => 1, 'channel' => 'pos', 'created_at' => $now, 'updated_at' => $now]),
            $this->event('order_item', ['uid' => (string) Str::ulid(), 'order_id' => $orderId, 'product_id__uid' => $p->uid, 'product_unit_id__uid' => $base->uid,
                'unit_id__uid' => $this->piece->uid, 'variation_id__uid' => null, 'unit_price' => 9000, 'quantity' => 2, 'quantity_per_unit' => 1, 'base_quantity' => 2,
                'is_unit_mode' => 0, 'total_price' => 18000, 'discount_amount' => 0, 'returned_quantity' => 0, 'price_overridden' => 0, 'created_at' => $now, 'updated_at' => $now]),
            $this->event('stock', ['uid' => (string) Str::ulid(), 'product_id__uid' => $p->uid, 'stock_location_id__uid' => $locUid, 'user_id__uid' => $this->admin->uid,
                'product_variation_id__uid' => null, 'destination_location_id__uid' => null, 'type' => 'out', 'quantity' => 2, 'reference_number' => $orderId,
                'reference_type' => 'sale', 'transaction_date' => $now, 'created_at' => $now, 'updated_at' => $now]),
        ];
        $res = app(Inbox::class)->receive($ikj, null, $events);
        $this->assertSame(['applied'], array_values(array_unique(array_column($res, 'status'))), json_encode($res));

        $o = Order::find($orderId);
        $this->assertSame($ikj->id, $o->branch_id);
        $this->assertSame($this->admin->id, $o->user_id);
        $this->assertSame(1, DB::table('order_items')->where('order_id', $orderId)->count());
        $this->assertSame('IKJ-MAIN', DB::table('stock_locations')->where('uid', $locUid)->value('code'));
        $this->assertSame(48.0, app(InventoryBalances::class)->onHand($p->id, null, $ikj->id));
        $this->assertSame(0.0, (float) $p->fresh()->stock); // head office's own stock is unchanged

        // the same events again: duplicates, nothing doubled
        $again = app(Inbox::class)->receive($ikj, null, $events);
        $this->assertSame(['duplicate'], array_values(array_unique(array_column($again, 'status'))));
        $this->assertSame(48.0, app(InventoryBalances::class)->onHand($p->id, null, $ikj->id));

        // something it doesn't know yet → retry, not lost
        $orphan = $this->event('order_item', ['uid' => (string) Str::ulid(), 'order_id' => $orderId, 'product_id__uid' => (string) Str::ulid(),
            'unit_price' => 1, 'quantity' => 1, 'quantity_per_unit' => 1, 'is_unit_mode' => 0, 'total_price' => 1, 'discount_amount' => 0, 'returned_quantity' => 0, 'price_overridden' => 0]);
        $r = app(Inbox::class)->receive($ikj, null, [$orphan]);
        $this->assertSame('retry', $r[0]['status']);
        $this->assertStringContainsString('Waiting for Product', $r[0]['error']);
        $this->assertDatabaseHas('sync_inbox', ['event_uid' => $orphan['event_uid'], 'status' => 'failed']);
    }

    public function test_same_customer_from_two_branches_is_one_customer_with_one_balance(): void
    {
        $this->setMode('central');
        $ikj = $this->ikj();
        $lek = Branch::create(['code' => 'LEK', 'number' => 3, 'name' => 'Lekki', 'type' => 'store', 'status' => 'active']);
        $phone = '0803' . rand(1000000, 9999999);
        $a = (string) Str::ulid();
        $b = (string) Str::ulid();
        $inbox = app(Inbox::class);
        $inbox->receive($ikj, null, [$this->event('customer', ['uid' => $a, 'first_name' => 'Kemi', 'last_name' => 'A', 'phone_number' => $phone, 'customer_type' => 'retail', '__points' => 200,
            'created_by__uid' => null, 'updated_by__uid' => null, 'updated_at' => now()->toDateTimeString()])]);
        $inbox->receive($lek, null, [$this->event('customer', ['uid' => $b, 'first_name' => 'Kemi', 'last_name' => 'A', 'phone_number' => $phone, 'customer_type' => 'retail', '__points' => 50,
            'created_by__uid' => null, 'updated_by__uid' => null, 'updated_at' => now()->toDateTimeString()])]);

        $this->assertSame(1, DB::table('customers')->where('phone_number', $phone)->count());
        $this->assertDatabaseHas('sync_uid_aliases', ['entity' => 'customers', 'alias_uid' => $b, 'target_uid' => $a]);
        $cid = DB::table('customers')->where('uid', $a)->value('id');
        $this->assertSame(200, (int) DB::table('customer_points')->where('customer_id', $cid)->value('points'));

        // points earned at Lekki against Lekki's uid land on the same customer
        $inbox->receive($lek, null, [$this->event('customer_point_tx', ['uid' => (string) Str::ulid(), 'customer_id__uid' => $b, 'created_by__uid' => null,
            'points_earned' => 30, 'points_redeemed' => 0, 'amount_spent' => 3000, 'discount_applied' => 0, 'description' => 'sale'])]);
        $this->assertSame(230, (int) DB::table('customer_points')->where('customer_id', $cid)->value('points'));
        $this->assertTrue(DB::table('sync_dirty')->where('entity', 'customer')->where('entity_uid', $a)->exists()); // goes back to every branch
    }

    // ------------------------------------------------------------------ head office: feed

    public function test_head_office_changes_go_into_the_feed_once_per_record(): void
    {
        $this->setMode('central');
        $ikj = $this->ikj();
        $lek = Branch::create(['code' => 'LEK', 'number' => 3, 'name' => 'Lekki', 'type' => 'store', 'status' => 'active']);
        DB::table('sync_dirty')->delete();
        DB::table('sync_feed')->delete();

        $p = $this->product('Feed Beans 1kg', 2500);
        $p->update(['price' => 2600]);
        $p->update(['price' => 2700]);
        app(PriceListResolver::class)->set(PriceList::forBranch($lek), $p, null, null, ['price' => 2900]);
        $this->product('Local only', 100, 'branch');
        SystemSetting::set('max_discount_percent', 12);
        SystemSetting::set('some_local_thing', 1);
        FieldPolicy::where('field', 'reorder_level')->first()?->update(['branch_rule' => 'override']);
        Feed::build();

        $this->assertSame(1, DB::table('sync_feed')->where('entity', 'product')->where('entity_uid', $p->uid)->count());
        $this->assertEquals(2700, json_decode(DB::table('sync_feed')->where('entity_uid', $p->uid)->value('payload'), true)['price']);
        $this->assertSame(0, DB::table('sync_feed')->where('entity', 'product')->where('entity_uid', Product::where('title', 'Local only')->value('uid'))->count());
        $this->assertTrue(DB::table('sync_feed')->where('entity', 'setting')->where('entity_uid', 'max_discount_percent')->exists());
        $this->assertFalse(DB::table('sync_feed')->where('entity', 'setting')->where('entity_uid', 'some_local_thing')->exists());

        // Lekki's price list reaches Lekki only
        $forIkj = collect(Feed::read($ikj, 0)['changes'])->pluck('entity')->all();
        $forLek = collect(Feed::read($lek, 0)['changes'])->pluck('entity')->all();
        $this->assertNotContains('price_list_item', $forIkj);
        $this->assertContains('price_list_item', $forLek);

        // compaction: another change replaces the row, cursor moves on
        $first = Feed::read($ikj, 0);
        $p->update(['price' => 2800]);
        Feed::build();
        $next = Feed::read($ikj, $first['cursor']);
        $this->assertSame(['product'], array_column($next['changes'], 'entity'));
        $this->assertSame(1, DB::table('sync_feed')->where('entity_uid', $p->uid)->count());
    }

    // ------------------------------------------------------------------ branch: applying the feed

    public function test_branch_applies_catalogue_but_keeps_what_it_decides(): void
    {
        $this->setMode('branch');
        $applier = app(FeedApplier::class);
        $uid = (string) Str::ulid();
        $payload = ['uid' => $uid, 'title' => 'Feed Garri 2kg', 'sku' => 'GAR-2', 'barcode' => '2900000099991', 'price' => 1500, 'cost_price' => 1100,
            'base_unit' => 'Piece', 'packs' => [], 'variations' => [], 'extra_barcodes' => [], 'reorder_level' => 10, 'is_active' => true,
            'category' => ['uid' => (string) Str::ulid(), 'name' => 'Grains ' . rand(1, 999)], 'distribution' => null];
        SyncContext::run(fn () => $applier->apply(['entity' => 'product', 'uid' => $uid, 'op' => 'upsert', 'payload' => $payload]));
        $p = Product::where('uid', $uid)->firstOrFail();
        $this->assertSame('central', $p->owner);
        $this->assertTrue((bool) $p->is_active);
        $this->assertSame(0, DB::table('sync_outbox')->count()); // nothing echoed back

        // branch decides reorder level and switches it off locally
        $p->update(['reorder_level' => 40, 'is_active' => false]);
        DB::table('product_local_overrides')->insert([['product_id' => $p->id, 'field' => 'reorder_level'], ['product_id' => $p->id, 'field' => 'is_active']]);
        $payload['price'] = 1600;
        $payload['reorder_level'] = 12;
        SyncContext::run(fn () => $applier->apply(['entity' => 'product', 'uid' => $uid, 'op' => 'upsert', 'payload' => $payload]));
        $p->refresh();
        $this->assertEquals(1600, (float) $p->price);
        $this->assertEquals(40, (float) $p->reorder_level);
        $this->assertFalse((bool) $p->is_active);

        // not distributed here any more → not sold here
        DB::table('product_local_overrides')->where('product_id', $p->id)->where('field', 'is_active')->delete();
        $payload['distribution'] = [(string) Str::ulid()];
        SyncContext::run(fn () => $applier->apply(['entity' => 'product', 'uid' => $uid, 'op' => 'upsert', 'payload' => $payload]));
        $this->assertFalse((bool) $p->fresh()->is_active);
    }

    public function test_branch_takes_head_office_identity_for_the_same_item_and_tells_head_office(): void
    {
        $this->setMode('branch');
        $local = $this->product('Peak Milk local', 3000, 'branch');
        $local->update(['barcode' => '6151000000017']);
        DB::table('sync_outbox')->delete();
        $hqUid = (string) Str::ulid();
        $payload = ProductPayload::export($local->fresh());
        $payload['uid'] = $hqUid;
        $payload['base_uid'] = (string) Str::ulid();
        $payload['is_active'] = true;
        $payload['distribution'] = null;
        SyncContext::run(fn () => app(FeedApplier::class)->apply(['entity' => 'product', 'uid' => $hqUid, 'op' => 'upsert', 'payload' => $payload]));

        $this->assertSame($hqUid, $local->fresh()->uid);           // same row, history kept
        $this->assertSame('central', $local->fresh()->owner);
        $aliases = DB::table('sync_outbox')->where('entity', 'uid_alias')->pluck('payload')->map(fn ($j) => json_decode($j, true));
        $this->assertTrue($aliases->contains(fn ($a) => $a['table'] === 'products' && $a['target_uid'] === $hqUid));
        $this->assertTrue($aliases->contains(fn ($a) => $a['table'] === 'product_unit'));
    }

    public function test_branch_applies_staff_roles_settings_and_loyalty_balance(): void
    {
        $this->setMode('branch');
        $applier = app(FeedApplier::class);
        $uid = (string) Str::ulid();
        SyncContext::run(function () use ($applier, $uid) {
            $applier->apply(['entity' => 'role', 'uid' => 'Till Captain', 'op' => 'upsert', 'payload' => ['name' => 'Till Captain', 'permissions' => ['View pos', 'Brand new permission']]]);
            $applier->apply(['entity' => 'user', 'uid' => $uid, 'op' => 'upsert', 'payload' => [
                'uid' => $uid, 'first_name' => 'Bisi', 'last_name' => 'A', 'username' => 'bisi-sync', 'email' => 'bisi-sync@test.com',
                'password' => Hash::make('secret123'), 'is_active' => true, 'login_rule' => 'inherit', 'roles' => ['Till Captain'],
                'branch_uid' => $this->home->uid, 'branch_uids' => [],
            ]]);
            $applier->apply(['entity' => 'setting', 'uid' => 'max_discount_percent', 'op' => 'upsert', 'payload' => ['key' => 'max_discount_percent', 'value' => '4']]);
            $applier->apply(['entity' => 'setting', 'uid' => 'evil', 'op' => 'upsert', 'payload' => ['key' => 'maintenance_lock', 'value' => 'true']]);
        });
        $u = User::where('uid', $uid)->firstOrFail();
        $this->assertTrue(Hash::check('secret123', $u->password));
        $this->assertTrue($u->hasRole('Till Captain'));
        $this->assertTrue($u->can('Brand new permission'));
        $this->assertSame($this->home->id, $u->branch_id);
        $this->assertEquals(4, SystemSetting::get('max_discount_percent'));
        $this->assertFalse((bool) SystemSetting::get('maintenance_lock', false)); // only head-office settings travel

        // loyalty: head office balance + what this branch earned but hasn't sent yet
        $c = Customer::create(['first_name' => 'Kemi', 'last_name' => 'B', 'phone_number' => '0805' . rand(1000000, 9999999), 'customer_type' => 'retail']);
        Outbox::recordEntity('customer_point_tx', (string) Str::ulid(), ['customer_id__uid' => $c->uid, 'points_earned' => 15, 'points_redeemed' => 0]);
        $payload = (array) DB::table('customers')->where('id', $c->id)->first();
        unset($payload['id']);
        $payload['__points'] = 500;
        SyncContext::run(fn () => $applier->apply(['entity' => 'customer', 'uid' => $c->uid, 'op' => 'upsert', 'payload' => $payload]));
        $this->assertSame(515, (int) DB::table('customer_points')->where('customer_id', $c->id)->value('points'));
    }

    // ------------------------------------------------------------------ the round trip over HTTP

    public function test_branch_sync_round_pushes_pulls_and_runs_commands(): void
    {
        $this->setMode('branch', ['central_url' => 'https://hq.test', 'remote_installation_uid' => (string) Str::ulid(),
            'token' => encrypt(Str::random(48), false), 'hmac_secret' => encrypt('s3cret', false)]);
        DB::table('installation')->update(['token' => \Illuminate\Support\Facades\Crypt::encryptString('tok'), 'hmac_secret' => \Illuminate\Support\Facades\Crypt::encryptString('s3cret')]);
        Install::refresh();
        $c = Customer::create(['first_name' => 'Round', 'last_name' => 'Trip', 'phone_number' => '0806' . rand(1000000, 9999999), 'customer_type' => 'retail']);
        $cmdUid = (string) Str::ulid();
        $prodUid = (string) Str::ulid();

        Http::fake([
            'hq.test/api/v1/sync/push' => function ($req) {
                return Http::response(['results' => collect($req->data()['events'])->map(fn ($e) => ['event_uid' => $e['event_uid'], 'status' => 'applied'])->all()]);
            },
            'hq.test/api/v1/sync/pull*' => Http::sequence()
                ->push(['changes' => [['id' => 7, 'entity' => 'product', 'uid' => $prodUid, 'op' => 'upsert', 'payload' => [
                    'uid' => $prodUid, 'title' => 'From HQ', 'price' => 700, 'base_unit' => 'Piece', 'packs' => [], 'variations' => [], 'extra_barcodes' => [],
                    'is_active' => true, 'distribution' => null]]],
                    'cursor' => 7, 'has_more' => false, 'head' => 7, 'commands' => [['uid' => $cmdUid, 'command' => 'diagnostics', 'payload' => null, 'min_version' => null]]])
                ->push(['changes' => [], 'cursor' => 7, 'has_more' => false, 'head' => 7, 'commands' => []]),
            'hq.test/api/v1/sync/commands/*' => Http::response(['updated' => 1]),
        ]);

        $r = app(SyncRunner::class)->run();
        $this->assertGreaterThanOrEqual(1, $r['sent']); // the customer (+ its audit entry)
        $this->assertSame(1, $r['applied']);
        $this->assertSame(1, $r['commands']);
        $this->assertSame('sent', DB::table('sync_outbox')->where('entity_uid', $c->uid)->value('status'));
        $this->assertTrue(Product::where('uid', $prodUid)->exists());
        $this->assertSame(7, SyncState::get('feed_cursor'));
        $this->assertSame('done', DB::table('sync_command_runs')->where('uid', $cmdUid)->value('status'));
        $this->assertTrue((bool) DB::table('sync_command_runs')->where('uid', $cmdUid)->value('reported'));

        Http::assertSent(fn ($req) => str_contains($req->url(), '/sync/pull') && str_contains($req->url(), 'cursor=0')
            && $req->hasHeader('X-Signature') && $req->hasHeader('X-Installation'));

        // a command for a newer version is refused, not run
        $runner = app(SyncRunner::class);
        $m = new \ReflectionMethod($runner, 'execute');
        [$status, $msg] = $m->invoke($runner, ['uid' => 'x', 'command' => 'resync', 'min_version' => '2099.1.0']);
        $this->assertSame('refused', $status);
        $this->assertSame(7, SyncState::get('feed_cursor'));
    }

    public function test_signed_push_and_pull_endpoints(): void
    {
        $this->setMode('central');
        $ikj = $this->ikj();
        $code = app(EnrollmentService::class)->issueCode($ikj);
        $r = app(EnrollmentService::class)->enroll($code, 'IKJ', 'test', '2026.11.0');
        $send = function (string $method, string $path, array $query = [], array $data = []) use ($r) {
            $body = $method === 'GET' ? '' : json_encode($data);
            $ts = time();
            $url = $path . ($query ? '?' . http_build_query($query) : '');
            return $this->call($method, $url, [], [], [], [
                'HTTP_X_INSTALLATION' => $r['installation']->uid, 'HTTP_AUTHORIZATION' => 'Bearer ' . $r['token'], 'HTTP_X_TIMESTAMP' => (string) $ts,
                'HTTP_X_SIGNATURE' => Signature::make($r['secret'], $method, $path, $body, $ts), 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            ], $body);
        };
        $this->product('Endpoint Oil 1L', 2100);
        DB::table('sync_commands')->insert(['uid' => (string) Str::ulid(), 'branch_id' => $ikj->id, 'command' => 'diagnostics', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        $pull = $send('GET', '/api/v1/sync/pull', ['cursor' => 0, 'limit' => 1000])->assertOk();
        $this->assertContains('product', array_column($pull->json('changes'), 'entity'));
        $this->assertSame('diagnostics', $pull->json('commands.0.command'));
        $send('GET', '/api/v1/sync/pull', ['cursor' => $pull->json('cursor')])->assertOk()->assertJsonCount(0, 'changes');

        $c = (string) Str::ulid();
        $send('POST', '/api/v1/sync/push', [], ['events' => [$this->event('customer', ['uid' => $c, 'first_name' => 'Api', 'last_name' => 'Push',
            'phone_number' => '0802' . rand(1000000, 9999999), 'customer_type' => 'retail', 'created_by__uid' => null, 'updated_by__uid' => null])]])
            ->assertOk()->assertJsonPath('results.0.status', 'applied');
        $this->assertSame($ikj->id, DB::table('customers')->where('uid', $c)->value('branch_id'));

        $cmd = $pull->json('commands.0.uid');
        $send('POST', '/api/v1/sync/commands/' . $cmd, [], ['status' => 'done', 'result' => 'ok'])->assertOk();
        $this->assertSame('done', DB::table('sync_commands')->where('uid', $cmd)->value('status'));
    }

    public function test_sync_pages_render(): void
    {
        $as = fn () => $this->actingAs($this->admin)->withSession([TwoFactor::SESSION_KEY => true]);
        $this->setMode('central');
        $this->ikj();
        $as()->get('/sync')->assertOk()->assertSee('Sync – all branches', false);
        $p = $this->product('Dist Test', 100);
        $as()->postJson("/products/{$p->id}/distribution", ['mode' => 'selected', 'branches' => [Branch::where('code', 'IKJ')->value('id')]])->assertOk();
        $this->assertSame('selected', $p->fresh()->distribution);
        $as()->get("/products/{$p->id}/edit")->assertOk()->assertSee('Which branches sell this');

        $this->setMode('branch');
        $as()->get('/sync')->assertOk()->assertSee('Sync with head office');
        Outbox::recordEntity('customer', (string) Str::ulid(), ['x' => 1]);
        DB::table('sync_outbox')->update(['status' => 'dead', 'attempts' => 20, 'last_error' => 'boom']);
        $as()->get('/sync')->assertOk()->assertSee('boom');
        $as()->postJson('/sync/retry')->assertOk();
        $this->assertSame(0, DB::table('sync_outbox')->where('status', 'dead')->count());
        $as()->postJson('/sync/resync')->assertOk();
        $this->assertSame(0, SyncState::get('feed_cursor'));
    }
}
