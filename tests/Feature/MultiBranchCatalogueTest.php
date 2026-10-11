<?php

namespace Tests\Feature;

use App\Exceptions\CentralProductException;
use App\Exceptions\DuplicateBarcodeException;
use App\Models\Branch;
use App\Models\PriceChangeRequest;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductSubmission;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Models\User;
use App\Services\BarcodeGenerator;
use App\Services\Catalogue\BarcodeIndex;
use App\Services\Catalogue\CatalogueRequests;
use App\Services\Catalogue\PriceListResolver;
use App\Services\Catalogue\ProductPayload;
use App\Services\MultiBranch\EnrollmentService;
use App\Services\MultiBranch\Install;
use App\Services\MultiBranch\LocationService;
use App\Services\MultiBranch\Signature;
use App\Services\PosService;
use App\Services\PricingService;
use App\Services\Security\TwoFactor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 3: barcode index, branch price lists, head-office field rules, price change requests and product submissions.
 */
class MultiBranchCatalogueTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $home;
    protected User $admin;
    protected Unit $piece;
    protected Unit $carton;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Requires MySQL.');
        }
        Cache::flush();
        Install::refresh();
        $this->home = Install::branch();
        app(LocationService::class)->ensureFor($this->home);

        foreach (['dashboard', 'View pos', 'View product', 'Create product', 'Update product', 'Manage price control', 'Manage price lists',
                     'Request price change', 'Approve price change', 'Submit product to head office', 'Approve product submission',
                     'Manage field policies', 'View price history'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        Role::findOrCreate('Super Admin', 'web');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'username' => 'cat-admin', 'email' => 'cat-admin@test.com', 'password' => Hash::make('password')]);
        $this->admin->assignRole('Super Admin');

        $this->piece = Unit::firstOrCreate(['name' => 'Piece'], ['short_name' => 'pc', 'is_active' => true]);
        $this->carton = Unit::firstOrCreate(['name' => 'Carton'], ['short_name' => 'ctn', 'is_active' => true]);
    }

    protected function setMode(string $mode): void
    {
        DB::table('installation')->update(['mode' => $mode]);
        Install::refresh();
        app()->forgetScopedInstances();
    }

    protected function product(string $title = 'Milo 500g', float $price = 2000, string $owner = 'branch'): Product
    {
        $p = Product::create(['title' => $title, 'price' => $price, 'cost_price' => $price * 0.8, 'product_type' => 'simple', 'is_active' => true]);
        $p->forceFill(['owner' => $owner])->save();
        ProductUnit::create(['product_id' => $p->id, 'unit_id' => $this->piece->id, 'quantity_per_unit' => 1, 'is_base' => true, 'is_sellable' => true]);
        ProductUnit::create(['product_id' => $p->id, 'unit_id' => $this->carton->id, 'quantity_per_unit' => 12, 'price' => $price * 11.5, 'is_sellable' => true, 'barcode' => BarcodeGenerator::next()]);
        return $p->fresh('packs');
    }

    protected function actingAdmin(): static
    {
        return $this->actingAs($this->admin)->withSession([TwoFactor::SESSION_KEY => true]);
    }

    // ------------------------------------------------------------------

    public function test_every_barcode_is_indexed_and_extra_codes_ring_up_the_item(): void
    {
        $p = $this->product();
        $this->assertDatabaseHas('product_barcodes', ['barcode' => $p->barcode, 'product_id' => $p->id, 'kind' => 'primary']);
        $carton = $p->packs->firstWhere('is_base', false);
        $this->assertDatabaseHas('product_barcodes', ['barcode' => $carton->barcode, 'product_unit_id' => $carton->id]);

        // change of barcode moves the index row
        $p->update(['barcode' => '5000112637922']);
        $this->assertSame(1, ProductBarcode::where('product_id', $p->id)->whereNull('product_unit_id')->where('kind', 'primary')->count());
        $this->assertDatabaseHas('product_barcodes', ['barcode' => '5000112637922', 'product_id' => $p->id]);

        // extra code (new packaging) → till finds the product
        BarcodeIndex::addExtra($p, '6151234567890', null, null, 'new pack design');
        $found = app(PosService::class)->lookup('6151234567890');
        $this->assertTrue($found['exact']);
        $this->assertSame($p->id, $found['items'][0]['product_id']);

        // extra code on the carton rings up a carton
        BarcodeIndex::addExtra($p, 'CTN-OLD-1', null, $carton);
        $this->assertSame('pack', BarcodeGenerator::owner('CTN-OLD-1')['type']);

        // a code can never point at two items
        $other = $this->product('Bournvita 500g', 1800);
        $this->expectException(DuplicateBarcodeException::class);
        BarcodeIndex::addExtra($other, '6151234567890');
    }

    public function test_product_form_refuses_a_barcode_that_is_an_extra_code_elsewhere(): void
    {
        $p = $this->product();
        BarcodeIndex::addExtra($p, '6151234567890');
        $this->assertTrue(BarcodeGenerator::exists('6151234567890'));
        $this->assertFalse(BarcodeGenerator::exists($p->barcode, 'products', $p->id)); // its own main code
        $this->assertTrue(BarcodeGenerator::exists($p->barcode, 'products', 999999));

        $this->actingAdmin()->postJson("/products/{$p->id}/barcodes", ['barcode' => $p->barcode])->assertStatus(422);
        $this->actingAdmin()->postJson("/products/{$p->id}/barcodes", ['barcode' => '4001234567892', 'note' => 'supplier B'])->assertCreated();
        $this->actingAdmin()->getJson("/products/{$p->id}/barcodes")->assertOk()->assertJsonFragment(['barcode' => '4001234567892', 'kind' => 'extra']);
    }

    public function test_branch_price_list_overrides_catalogue_price(): void
    {
        $p = $this->product('Peak Milk 400g', 1000);
        $carton = $p->packs->firstWhere('is_base', false);
        $pricing = app(PricingService::class);
        $this->assertSame(1000.0, $pricing->quote($p)['price']);

        $all = PriceList::create(['code' => 'ALL-LOW', 'name' => 'All branches', 'priority' => 10, 'is_active' => true]);
        $mine = PriceList::forBranch($this->home);
        $resolver = app(PriceListResolver::class);
        $resolver->set($all, $p, null, null, ['price' => 950]);
        $this->assertSame(950.0, $pricing->quote($p)['price']);

        $resolver->set($mine, $p, null, null, ['price' => 1100, 'wholesale_price' => 1050]);
        $q = $pricing->quote($p);
        $this->assertSame(1100.0, $q['price']);
        $this->assertSame('BR-' . $this->home->code, $q['price_list']);

        // carton has its own branch price
        $resolver->set($mine, $p, null, $carton, ['price' => 12500]);
        $this->assertSame(12500.0, $pricing->quote($p, null, $carton)['price']);

        // expired list is ignored
        $mine->update(['ends_at' => now()->subDay()]);
        $resolver->flush();
        $this->assertSame(950.0, $pricing->quote($p)['price']);

        // other branch's list never applies here
        $other = Branch::create(['code' => 'LEK', 'number' => 7, 'name' => 'Lekki', 'type' => 'store', 'status' => 'active']);
        $resolver->set(PriceList::forBranch($other), $p, null, null, ['price' => 10]);
        $this->assertSame(950.0, $pricing->quote($p)['price']);
    }

    public function test_branch_cannot_change_head_office_product_except_as_policy_allows(): void
    {
        $p = $this->product('Indomie Chicken 70g', 150, 'central');
        $this->setMode('branch');
        $this->assertTrue(CatalogueRequests::governed($p));

        $res = $this->actingAdmin()->putJson("/products/{$p->id}", [
            'title' => 'Hacked name', 'price' => 170, 'reorder_level' => 40, 'is_active' => 1, 'change_reason' => 'Shop next door sells 170',
            'product_type' => 'simple', 'base_unit_id' => $this->piece->id,
        ])->assertOk();
        $this->assertSame(1, $res->json('requests'));

        $p->refresh();
        $this->assertSame('Indomie Chicken 70g', $p->title);   // locked
        $this->assertEquals(150, (float) $p->price);             // waits for approval
        $this->assertEquals(40, (float) $p->reorder_level);      // branch decides
        $this->assertDatabaseHas('product_local_overrides', ['product_id' => $p->id, 'field' => 'reorder_level']);
        $req = PriceChangeRequest::where('product_id', $p->id)->firstOrFail();
        $this->assertSame('pending', $req->status);
        $this->assertEquals(170, (float) $req->requested_value);
        $this->assertEquals(150, (float) $req->current_value);
        $this->assertSame('Shop next door sells 170', $req->reason);

        // direct price writes are refused …
        try {
            app(PricingService::class)->setPrice($p, 'price', 99);
            $this->fail('expected CentralProductException');
        } catch (CentralProductException $e) {
            $this->assertStringContainsString('head-office product', $e->getMessage());
        }
        // … the price-control quick edit turns into a request, the bulk tool skips it, delete is refused
        $this->actingAdmin()->postJson('/price-control/quick', ['kind' => 'product', 'id' => $p->id, 'field' => 'price', 'value' => 160])
            ->assertOk()->assertJson(['requested' => true]);
        $this->assertSame(1, PriceChangeRequest::where('product_id', $p->id)->where('status', 'pending')->where('requested_value', 160)->count());
        $this->assertTrue(app(PricingService::class)->previewBulk(['product_ids' => [$p->id]], ['field' => 'price', 'method' => 'percent', 'value' => 10, 'direction' => 'increase'])->isEmpty());
        $this->actingAdmin()->deleteJson("/products/{$p->id}")->assertStatus(422);

        // branch's own products are not governed
        $local = $this->product('Local bread', 900, 'branch');
        $this->assertFalse(CatalogueRequests::governed($local));
        $this->assertNotNull(app(PricingService::class)->setPrice($local, 'price', 950));
    }

    public function test_head_office_approves_for_one_branch_or_for_all(): void
    {
        $this->setMode('central');
        $ikj = Branch::create(['code' => 'IKJ', 'number' => 2, 'name' => 'Ikeja', 'type' => 'store', 'status' => 'active']);
        $p = $this->product('Coke 50cl', 300, 'central');
        $svc = app(CatalogueRequests::class);
        $this->actingAdmin();

        $svc->receivePriceRequests($ikj, [
            ['uid' => (string) Str::ulid(), 'product_uid' => $p->uid, 'field' => 'price', 'requested_value' => 320, 'current_value' => 300, 'item_label' => 'Coke 50cl', 'reason' => 'transport'],
            ['uid' => $uid2 = (string) Str::ulid(), 'product_uid' => $p->uid, 'field' => 'wholesale_price', 'requested_value' => 280, 'item_label' => 'Coke 50cl'],
        ]);
        // idempotent
        $this->assertSame(0, $svc->receivePriceRequests($ikj, [['uid' => $uid2, 'product_uid' => $p->uid, 'field' => 'price', 'requested_value' => 1]]));

        [$a, $b] = PriceChangeRequest::where('branch_id', $ikj->id)->orderBy('id')->get()->all();
        $this->postJson("/catalogue/price-requests/{$a->id}/approve", ['scope' => 'branch', 'value' => 315, 'note' => 'ok at 315'])->assertOk();
        $this->postJson("/catalogue/price-requests/{$b->id}/reject", ['note' => 'no'])->assertOk();
        $this->postJson("/catalogue/price-requests/{$a->id}/approve", ['scope' => 'branch'])->assertStatus(422); // once only

        $list = PriceList::where('code', 'BR-IKJ')->firstOrFail();
        $this->assertSame($ikj->id, $list->branch_id);
        $this->assertEquals(315, (float) $list->items()->first()->price);
        $this->assertEquals(300, (float) $p->fresh()->price); // catalogue untouched

        // "all branches" changes the catalogue
        $svc->receivePriceRequests($ikj, [['uid' => (string) Str::ulid(), 'product_uid' => $p->uid, 'field' => 'price', 'requested_value' => 330, 'item_label' => 'Coke 50cl']]);
        $c = PriceChangeRequest::where('requested_value', 330)->firstOrFail();
        $svc->approvePrice($c, null, 'all');
        $this->assertEquals(330, (float) $p->fresh()->price);
        $this->assertNull($list->items()->first()->price); // branch-only price cleared so the new catalogue price shows

        $d = $svc->decisionsFor($ikj, null);
        $this->assertCount(3, $d['price_requests']);
        $this->assertNotEmpty($d['cursor']);
        $this->assertSame('approved', collect($d['price_requests'])->firstWhere('uid', $a->uid)['status']);
    }

    public function test_branch_applies_head_office_decisions(): void
    {
        $p = $this->product('Fanta 50cl', 300, 'central');
        $this->setMode('branch');
        $this->actingAdmin();
        $svc = app(CatalogueRequests::class);
        $r = $svc->request($p, null, null, 'price', 340, 'fuel');

        $this->assertTrue($svc->applyPriceDecision(['uid' => $r->uid, 'status' => 'approved', 'approved_value' => 335, 'scope' => 'branch', 'decided_by_name' => 'HQ', 'decided_at' => now()->toIso8601String()]));
        $this->assertFalse($svc->applyPriceDecision(['uid' => $r->uid, 'status' => 'approved', 'approved_value' => 999, 'scope' => 'branch'])); // applied once
        app(PriceListResolver::class)->flush();
        $this->assertSame(335.0, app(PricingService::class)->quote($p->fresh())['price']);
        $this->assertSame('approved', $r->fresh()->status);

        // submission decision
        $local = $this->product('Agege bread', 800, 'branch');
        $s = $svc->submit($local, 'sells well');
        $this->assertSame('submitted', $local->fresh()->central_status);
        $svc->applySubmissionDecision(['uid' => $s->uid, 'status' => 'approved', 'decided_by_name' => 'HQ']);
        $this->assertSame('central', $local->fresh()->owner);
        $this->assertTrue(CatalogueRequests::governed($local->fresh()));
    }

    public function test_submission_payload_round_trip_and_barcode_conflicts(): void
    {
        $p = $this->product('Golden Penny Semovita 1kg', 1500);
        BarcodeIndex::addExtra($p, 'SEMO-OLD');
        $payload = ProductPayload::export($p->fresh());
        $this->assertSame($p->uid, $payload['uid']);
        $this->assertCount(1, $payload['packs']);
        $this->assertSame('Carton', $payload['packs'][0]['unit']);
        $this->assertSame([['barcode' => 'SEMO-OLD', 'note' => null]], $payload['extra_barcodes']);

        // Same uid → updates in place, no conflict with its own codes
        $this->assertSame([], ProductPayload::conflicts($payload));
        $again = ProductPayload::import($payload, 'central');
        $this->assertSame($p->id, $again->id);
        $this->assertSame('central', $again->owner);

        // A different product carrying the same barcode is a conflict
        $clash = $payload;
        $clash['uid'] = (string) Str::ulid();
        $clash['packs'][0]['uid'] = (string) Str::ulid();
        $this->assertContains($p->barcode, ProductPayload::conflicts($clash));
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        ProductPayload::import($clash, 'central');
    }

    public function test_head_office_adds_a_submitted_product_with_the_same_uid(): void
    {
        $this->setMode('central');
        $ikj = Branch::create(['code' => 'IKJ', 'number' => 2, 'name' => 'Ikeja', 'type' => 'store', 'status' => 'active']);
        $uid = (string) Str::ulid();
        $packUid = (string) Str::ulid();
        $payload = ['uid' => $uid, 'title' => 'Ikeja Special Puff-puff', 'sku' => 'PUFF-1', 'barcode' => '2002000000017', 'price' => 200,
            'cost_price' => 120, 'base_unit' => 'Piece', 'category' => ['name' => 'Bakery'], 'brand' => null,
            'packs' => [['uid' => $packUid, 'unit' => 'Tray', 'quantity_per_unit' => 20, 'price' => 3800, 'barcode' => '2002000000024', 'is_sellable' => true]],
            'variations' => [], 'extra_barcodes' => []];
        app(CatalogueRequests::class)->receiveSubmissions($ikj, [['uid' => (string) Str::ulid(), 'product_uid' => $uid, 'title' => 'Ikeja Special Puff-puff', 'payload' => $payload]]);
        $s = ProductSubmission::where('product_uid', $uid)->firstOrFail();

        $this->actingAdmin()->getJson("/catalogue/submissions/{$s->id}")->assertOk()->assertJson(['conflicts' => []]);
        $this->actingAdmin()->postJson("/catalogue/submissions/{$s->id}/approve")->assertOk();

        $p = Product::where('uid', $uid)->firstOrFail();
        $this->assertSame('central', $p->owner);
        $this->assertSame('published', $p->central_status);
        $this->assertSame($ikj->id, $p->origin_branch_id);
        $this->assertSame('Bakery', $p->category->name);
        $this->assertSame($packUid, ProductUnit::where('product_id', $p->id)->where('is_base', false)->value('uid'));
        $this->assertSame('pack', BarcodeGenerator::owner('2002000000024')['type']);
        $this->assertSame('approved', $s->fresh()->status);
    }

    public function test_requests_travel_over_the_signed_api(): void
    {
        $this->setMode('central');
        $ikj = Branch::create(['code' => 'IKJ', 'number' => 2, 'name' => 'Ikeja', 'type' => 'store', 'status' => 'active']);
        $p = $this->product('Sprite 50cl', 300, 'central');
        $code = app(EnrollmentService::class)->issueCode($ikj);
        $r = app(EnrollmentService::class)->enroll($code, 'IKJ', 'test', '2026.11.0');
        $inst = $r['installation'];

        $send = function (string $method, string $path, array $data = []) use ($inst, $r) {
            $body = $method === 'GET' ? '' : json_encode($data);
            $ts = time();
            return $this->call($method, $path, [], [], [], [
                'HTTP_X_INSTALLATION' => $inst->uid, 'HTTP_AUTHORIZATION' => 'Bearer ' . $r['token'], 'HTTP_X_TIMESTAMP' => (string) $ts,
                'HTTP_X_SIGNATURE' => Signature::make($r['secret'], $method, parse_url($path, PHP_URL_PATH), $body, $ts),
                'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            ], $body);
        };

        $uid = (string) Str::ulid();
        $send('POST', '/api/v1/branch/price-requests', ['requests' => [
            ['uid' => $uid, 'product_uid' => $p->uid, 'field' => 'price', 'requested_value' => 310, 'item_label' => 'Sprite 50cl'],
        ]])->assertOk()->assertJson(['received' => 1]);
        $send('POST', '/api/v1/branch/price-requests', ['requests' => [['uid' => 'bad', 'product_uid' => $p->uid, 'field' => 'cost_price', 'requested_value' => 1]]])
            ->assertStatus(422);

        $this->actingAdmin();
        app(CatalogueRequests::class)->approvePrice(PriceChangeRequest::where('uid', $uid)->first(), null, 'branch');

        $res = $send('GET', '/api/v1/branch/decisions')->assertOk();
        $this->assertSame($uid, $res->json('price_requests.0.uid'));
        $cursor = $res->json('cursor');
        $this->assertNotEmpty($cursor);
        $send('GET', '/api/v1/branch/decisions?since=' . urlencode($cursor))->assertOk();
    }

    public function test_catalogue_pages_render(): void
    {
        $this->setMode('central');
        $p = $this->product('Pages test', 500, 'central');
        $list = PriceList::create(['code' => 'TEST', 'name' => 'Test list', 'priority' => 5, 'is_active' => true]);
        app(PriceListResolver::class)->set($list, $p, null, null, ['price' => 480]);

        $this->actingAdmin()->get('/catalogue/requests')->assertOk()->assertSee('Requests from branches');
        $this->actingAdmin()->get('/catalogue/price-lists')->assertOk()->assertSee('TEST');
        $this->actingAdmin()->get("/catalogue/price-lists/{$list->id}")->assertOk()->assertSee('Pages test');
        $this->actingAdmin()->get('/catalogue/rules')->assertOk()->assertSee('Selling price');
        $this->actingAdmin()->post('/catalogue/rules', ['rules' => ['reorder_level' => 'locked']])->assertRedirect();
        $this->assertSame('locked', \App\Models\FieldPolicy::where('field', 'reorder_level')->value('branch_rule'));

        $this->setMode('branch');
        $this->actingAdmin()->get("/products/{$p->id}/edit")->assertOk()->assertSee('Head office manages this product');
        $this->actingAdmin()->get('/catalogue/requests')->assertOk()->assertSee('Requests to head office');
    }
}
