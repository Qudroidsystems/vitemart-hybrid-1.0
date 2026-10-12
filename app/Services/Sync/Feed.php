<?php

namespace App\Services\Sync;

use App\Models\Branch;
use App\Models\FieldPolicy;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Catalogue\ProductPayload;
use App\Services\MultiBranch\Install;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * Head office side: the change feed branches read with a cursor.
 *
 * A change marks (entity, uid) dirty.  build() turns dirty marks into feed rows carrying the full current
 * record, and deletes older rows for the same record (so the feed stays about as long as the catalogue).
 * A branch reads rows with id > its cursor that are for everyone or for it.
 */
class Feed
{
    public static function mark(string $entity, ?string $uid): void
    {
        if (!$uid || !Install::isCentral() || !Schema::hasTable('sync_dirty')) {
            return;
        }
        DB::table('sync_dirty')->insertOrIgnore(['entity' => $entity, 'entity_uid' => $uid, 'created_at' => now()]);
    }

    /** Everything branches should have – used when the feed starts, or to repair it. */
    public static function snapshot(): int
    {
        $n = 0;
        $add = function (string $entity, iterable $uids) use (&$n) {
            $rows = [];
            foreach ($uids as $u) {
                $rows[] = ['entity' => $entity, 'entity_uid' => $u, 'created_at' => now()];
                $n++;
                if (count($rows) >= 500) {
                    DB::table('sync_dirty')->insertOrIgnore($rows);
                    $rows = [];
                }
            }
            if ($rows) {
                DB::table('sync_dirty')->insertOrIgnore($rows);
            }
        };
        $add('branch', DB::table('branches')->pluck('uid'));
        $add('unit', DB::table('units')->pluck('uid'));
        $add('category', DB::table('categories')->orderByRaw('parent_id IS NOT NULL')->orderBy('id')->pluck('uid'));
        $add('brand', DB::table('brands')->pluck('uid'));
        $add('role', Role::where('guard_name', 'web')->pluck('name'));
        $add('user', DB::table('users')->whereNotNull('uid')->pluck('uid'));
        $add('policies', ['all']);
        $add('setting', array_values(array_intersect(Registry::centralSettings(), DB::table('system_settings')->pluck('key')->all())));
        $add('product', DB::table('products')->where('owner', 'central')->pluck('uid'));
        $add('price_list', DB::table('price_lists')->pluck('uid'));
        $add('price_list_item', DB::table('price_list_items')->pluck('uid'));
        $add('customer', DB::table('customers')->whereNull('deleted_at')->pluck('uid'));
        if (Schema::hasTable('suppliers')) {
            $add('supplier', DB::table('suppliers')->pluck('uid'));
            $add('transfer', DB::table('stock_transfers')->whereNotIn('status', ['closed', 'cancelled', 'rejected'])->pluck('uid'));
            $add('purchase_order', DB::table('purchase_orders')->whereNotIn('status', ['closed', 'cancelled', 'received'])->pluck('uid'));
        }
        return $n;
    }

    /** Dirty marks → feed rows.  Order of entities matters for first-time loads. */
    public static function build(int $limit = 5000): int
    {
        if (!Install::isCentral()) {
            return 0;
        }
        $order = ['branch', 'unit', 'category', 'brand', 'role', 'user', 'policies', 'setting', 'product', 'price_list', 'price_list_item', 'customer',
            'supplier', 'transfer', 'purchase_order'];
        $n = 0;
        RowCodec::flush();
        $dirty = DB::table('sync_dirty')->orderByRaw("FIELD(entity, '" . implode("','", $order) . "')")->orderBy('id')->limit($limit)->get();
        foreach ($dirty as $d) {
            try {
                [$op, $payload, $branchId] = static::payload($d->entity, $d->entity_uid);
                DB::transaction(function () use ($d, $op, $payload, $branchId, &$n) {
                    // a document shared by two branches (a transfer) gets one row for each of them
                    $firstId = null;
                    foreach (is_array($branchId) ? array_values(array_unique(array_filter($branchId))) : [$branchId] as $bid) {
                        $id = DB::table('sync_feed')->insertGetId([
                            'entity' => $d->entity, 'entity_uid' => $d->entity_uid, 'branch_id' => $bid, 'op' => $op,
                            'payload' => $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'created_at' => now(),
                        ]);
                        $firstId ??= $id;
                    }
                    if ($firstId) {
                        DB::table('sync_feed')->where('entity', $d->entity)->where('entity_uid', $d->entity_uid)->where('id', '<', $firstId)->delete();
                    }
                    DB::table('sync_dirty')->where('id', $d->id)->delete();
                    $n++;
                });
            } catch (\Throwable $e) {
                report($e);
                DB::table('sync_dirty')->where('id', $d->id)->delete(); // don't loop on a broken record
            }
        }
        return $n;
    }

    /** @return array{0:string, 1:?array, 2:?int} op, payload, branch_id (null = all) */
    public static function payload(string $entity, string $uid): array
    {
        switch ($entity) {
            case 'product':
                $p = Product::where('uid', $uid)->first();
                if (!$p || $p->owner !== 'central') {
                    return ['delete', null, null];
                }
                $data = ProductPayload::export($p->load(['packs.unit', 'variations', 'category', 'brand']));
                $data['is_active'] = (bool) $p->is_active;
                $data['promo_price'] = $p->promo_price !== null ? (float) $p->promo_price : null;
                $data['promo_starts_at'] = $p->promo_starts_at ? \Carbon\Carbon::parse($p->promo_starts_at)->toDateTimeString() : null;
                $data['promo_ends_at'] = $p->promo_ends_at ? \Carbon\Carbon::parse($p->promo_ends_at)->toDateTimeString() : null;
                $data['sale_price'] = $p->sale_price !== null ? (float) $p->sale_price : null;
                $data['distribution'] = ($p->distribution ?? 'all') === 'selected'
                    ? DB::table('product_distributions')->join('branches', 'branches.id', '=', 'product_distributions.branch_id')
                        ->where('product_id', $p->id)->pluck('branches.uid')->all()
                    : null;
                return ['upsert', $data, null];

            case 'branch':
                $b = Branch::where('uid', $uid)->first();
                return $b ? ['upsert', $b->toSync() + ['uid' => $b->uid], null] : ['delete', null, null];

            case 'policies':
                return ['upsert', ['rules' => FieldPolicy::query()->get(['field', 'label', 'branch_rule', 'help'])->toArray()], null];

            case 'setting':
                if (!in_array($uid, Registry::centralSettings(), true)) {
                    return ['delete', null, null];
                }
                return ['upsert', ['key' => $uid, 'value' => DB::table('system_settings')->where('key', $uid)->value('value')], null];

            case 'role':
                $r = Role::where('name', $uid)->where('guard_name', 'web')->first();
                return $r ? ['upsert', ['name' => $r->name, 'permissions' => $r->permissions()->pluck('name')->all()], null] : ['delete', null, null];

            case 'user':
                $u = User::where('uid', $uid)->first();
                if (!$u) {
                    return ['delete', null, null];
                }
                $attrs = $u->getAttributes();
                return ['upsert', [
                    'uid' => $u->uid, 'first_name' => $u->first_name, 'last_name' => $u->last_name, 'username' => $u->username,
                    'email' => $u->email, 'phone_number' => $u->phone_number, 'password' => $attrs['password'] ?? null,
                    'is_active' => (bool) ($attrs['is_active'] ?? true), 'login_rule' => $attrs['login_rule'] ?? 'inherit',
                    'login_start_time' => $attrs['login_start_time'] ?? null, 'login_end_time' => $attrs['login_end_time'] ?? null,
                    'login_days' => $attrs['login_days'] ?? null, 'force_logout_at' => $attrs['force_logout_at'] ?? null,
                    'pos_pin' => $attrs['pos_pin'] ?? null, 'max_discount_percent' => $attrs['max_discount_percent'] ?? null,
                    'email_verified_at' => $attrs['email_verified_at'] ?? null,
                    'roles' => $u->getRoleNames()->all(),
                    'branch_uid' => RowCodec::uidOf(Branch::class, $u->branch_id),
                    'branch_uids' => $u->branches()->pluck('branches.uid')->all(),
                ], null];

            case 'customer':
                $def = Registry::DOWN['customer'];
                $row = DB::table('customers')->where('uid', $uid)->first();
                if (!$row || $row->deleted_at) {
                    return ['delete', null, null];
                }
                $data = RowCodec::encode($def, $row, false);
                $data['__points'] = (int) DB::table('customer_points')->where('customer_id', $row->id)->value('points');
                unset($data['outstanding_balance'], $data['credit_balance']);
                return ['upsert', $data, null];

            case 'price_list':
                $def = Registry::DOWN['price_list'];
                $row = DB::table('price_lists')->where('uid', $uid)->first();
                return $row ? ['upsert', RowCodec::encode($def, $row, false), $row->branch_id] : ['delete', null, null];

            case 'price_list_item':
                $def = Registry::DOWN['price_list_item'];
                $row = DB::table('price_list_items')->where('uid', $uid)->first();
                if (!$row) {
                    return ['delete', null, null];
                }
                $branchId = DB::table('price_lists')->where('id', $row->price_list_id)->value('branch_id');
                return ['upsert', RowCodec::encode($def, $row, false), $branchId];

            case 'transfer':
                $t = \App\Models\StockTransfer::with('lines')->where('uid', $uid)->first();
                return $t ? ['upsert', \App\Services\Inventory\DocumentPayload::transfer($t), [$t->from_branch_id, $t->to_branch_id]] : ['delete', null, null];

            case 'purchase_order':
                $po = \App\Models\PurchaseOrder::with('lines')->where('uid', $uid)->first();
                return $po ? ['upsert', \App\Services\Inventory\DocumentPayload::purchaseOrder($po), $po->branch_id] : ['delete', null, null];

            default: // category, brand, unit, supplier
                $def = Registry::DOWN[$entity];
                $row = DB::table($def['table'])->where('uid', $uid)->first();
                return $row ? ['upsert', RowCodec::encode($def, $row, false), null] : ['delete', null, null];
        }
    }

    /** Rows for one branch after a cursor. */
    public static function read(Branch $branch, int $cursor, int $limit = 500): array
    {
        static::build();
        // Only hand out rows that are a couple of seconds old, so a row still being committed is never skipped.
        $head = (int) (DB::table('sync_feed')->where('created_at', '<=', now()->subSeconds((int) config('vitemart.sync.feed_settle_seconds', 2)))->max('id') ?? 0);
        $head = max($head, $cursor);
        $rows = DB::table('sync_feed')->where('id', '>', $cursor)->where('id', '<=', $head)
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branch->id))
            ->orderBy('id')->limit($limit + 1)->get();
        $more = $rows->count() > $limit;
        $rows = $rows->take($limit);
        return [
            'changes' => $rows->map(fn ($r) => ['id' => $r->id, 'entity' => $r->entity, 'uid' => $r->entity_uid, 'op' => $r->op,
                'payload' => $r->payload ? json_decode($r->payload, true) : null])->values()->all(),
            // rows for other branches are skipped by moving the cursor past them
            'cursor' => $more ? (int) $rows->last()->id : $head,
            'has_more' => $more,
            'head' => (int) DB::table('sync_feed')->max('id'),
        ];
    }
}
