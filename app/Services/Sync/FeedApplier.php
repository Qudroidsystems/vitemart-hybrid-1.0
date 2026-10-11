<?php

namespace App\Services\Sync;

use App\Models\Branch;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalogue\ProductPayload;
use App\Services\MultiBranch\Install;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Branch side: applies head office's feed.
 *
 * Head office wins for what it owns (catalogue, prices, rules, staff, roles, central settings),
 * except fields this branch is allowed to decide and has changed (product_local_overrides).
 */
class FeedApplier
{
    public function apply(array $change): void
    {
        $entity = $change['entity'];
        $p = $change['payload'] ?? [];
        if (($change['op'] ?? 'upsert') === 'delete') {
            $this->delete($entity, $change['uid']);
            return;
        }
        match ($entity) {
            'product' => $this->product($p),
            'branch' => $this->branch($p),
            'policies' => $this->policies($p),
            'setting' => $this->setting($p),
            'role' => $this->role($p),
            'user' => $this->user($p),
            'customer' => $this->customer($p),
            default => $this->row($entity, $p),
        };
    }

    protected function row(string $entity, array $p): void
    {
        if (!isset(Registry::DOWN[$entity]['table'])) {
            return;
        }
        $def = Registry::DOWN[$entity];
        $values = RowCodec::decode($def, $p);
        RowCodec::upsert($def, $values, 'adopt');
        if (in_array($entity, ['price_list', 'price_list_item'], true)) {
            app(\App\Services\Catalogue\PriceListResolver::class)->flush();
        }
    }

    protected function product(array $p): void
    {
        $local = Product::where('uid', $p['uid'])->first();
        $mine = Install::branch()?->uid;
        $notHere = is_array($p['distribution'] ?? null) && !in_array($mine, $p['distribution'], true);
        if (!$local && $notHere) {
            return; // not sold at this branch
        }
        if (!$local && ($clash = ProductPayload::conflicts($p))) {
            $local = $this->adoptLocalProduct($p, $clash);
        }
        $overrides = $local ? DB::table('product_local_overrides')->where('product_id', $local->id)->pluck('field')->all() : [];
        $keep = $local ? $local->only(array_merge($overrides, ['is_active'])) : [];

        $product = ProductPayload::import(ProductPayload::stripConflicts($p), 'central');

        $after = [
            'promo_price' => $p['promo_price'] ?? null,
            'promo_starts_at' => $p['promo_starts_at'] ?? null,
            'promo_ends_at' => $p['promo_ends_at'] ?? null,
            'sale_price' => $p['sale_price'] ?? null,
        ];
        foreach ($overrides as $f) {
            if (array_key_exists($f, $keep) && $f !== 'is_active') {
                $after[$f] = $keep[$f];
            }
        }
        // Sold here?  Head office switching it off, or not distributing it here, wins; otherwise the branch's choice stays.
        if (empty($p['is_active']) || $notHere) {
            $after['is_active'] = false;
        } elseif (in_array('is_active', $overrides, true)) {
            $after['is_active'] = $keep['is_active'];
        } else {
            $after['is_active'] = true;
        }
        $product->forceFill($after)->saveQuietly();
    }

    /**
     * This branch created the same item itself (same barcode) before head office added it to the catalogue:
     * the branch's product becomes the head-office product, keeping its sales and stock history.
     */
    protected function adoptLocalProduct(array $p, array $clash): ?Product
    {
        $row = DB::table('product_barcodes')->whereIn('barcode', $clash)->first();
        $local = $row ? Product::find($row->product_id) : null;
        if (!$local || $local->owner === 'central') {
            // Two head-office products can't share a code; drop it from the incoming copy instead.
            return null;
        }
        DB::table('products')->where('id', $local->id)->update(['uid' => $p['uid']]);
        UidAliases::adopted('products', $local->uid, $p['uid']);
        foreach ($p['packs'] ?? [] as $pk) {
            if (!empty($pk['barcode']) && ($r = DB::table('product_barcodes')->where('barcode', $pk['barcode'])->where('product_id', $local->id)->whereNotNull('product_unit_id')->first())) {
                UidAliases::adopted('product_unit', DB::table('product_unit')->where('id', $r->product_unit_id)->value('uid'), $pk['uid']);
                DB::table('product_unit')->where('id', $r->product_unit_id)->update(['uid' => $pk['uid']]);
            }
        }
        foreach ($p['variations'] ?? [] as $v) {
            if (!empty($v['barcode']) && ($r = DB::table('product_barcodes')->where('barcode', $v['barcode'])->where('product_id', $local->id)->whereNotNull('product_variation_id')->first())) {
                UidAliases::adopted('product_variations', DB::table('product_variations')->where('id', $r->product_variation_id)->value('uid'), $v['uid']);
                DB::table('product_variations')->where('id', $r->product_variation_id)->update(['uid' => $v['uid']]);
            }
        }
        return $local->fresh();
    }

    protected function branch(array $p): void
    {
        $b = Branch::where('uid', $p['uid'])->first()
            ?? Branch::where('code', $p['code'])->first()
            ?? Branch::where('number', $p['number'])->first();
        $values = collect($p)->only(['uid', 'code', 'number', 'name', 'type', 'status', 'address', 'phone', 'email', 'receipt_header', 'receipt_footer'])->all();
        if (array_key_exists('opening_hours', $p)) {
            $values['opening_hours'] = is_array($p['opening_hours']) ? json_encode($p['opening_hours']) : $p['opening_hours'];
        }
        if ($b) {
            DB::table('branches')->where('id', $b->id)->update($values + ['updated_at' => now()]);
        } else {
            DB::table('branches')->insert($values + ['created_at' => now(), 'updated_at' => now()]);
        }
        Install::refresh();
    }

    protected function policies(array $p): void
    {
        foreach ($p['rules'] ?? [] as $r) {
            DB::table('field_policies')->updateOrInsert(['field' => $r['field']],
                ['label' => $r['label'], 'branch_rule' => $r['branch_rule'], 'help' => $r['help'] ?? null, 'updated_at' => now()]);
        }
        Cache::forget('field_policies');
    }

    protected function setting(array $p): void
    {
        if (!in_array($p['key'] ?? '', Registry::centralSettings(), true)) {
            return;
        }
        DB::table('system_settings')->updateOrInsert(['key' => $p['key']], ['value' => $p['value'], 'updated_at' => now()]);
        Cache::forget(\App\Models\SystemSetting::CACHE_KEY);
    }

    protected function role(array $p): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $role = Role::findOrCreate($p['name'], 'web');
        $perms = [];
        foreach ($p['permissions'] ?? [] as $name) {
            $perms[] = Permission::findOrCreate($name, 'web');
        }
        $role->syncPermissions($perms);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function user(array $p): void
    {
        $u = User::where('uid', $p['uid'])->first()
            ?? User::where('email', $p['email'])->first()
            ?? User::where('username', $p['username'])->first();
        $values = collect($p)->only(['uid', 'first_name', 'last_name', 'username', 'email', 'phone_number', 'password', 'is_active',
            'login_rule', 'login_start_time', 'login_end_time', 'login_days', 'force_logout_at', 'pos_pin', 'max_discount_percent',
            'email_verified_at'])->all();
        $values['branch_id'] = !empty($p['branch_uid']) ? RowCodec::idOf(Branch::class, $p['branch_uid']) : null;
        if (!$u) {
            $u = new User();
        } elseif ($u->uid !== $p['uid']) {
            UidAliases::adopted('users', $u->uid, $p['uid']);
        }
        // Raw values: the password and PIN arrive already hashed.
        $u->setRawAttributes(array_merge($u->getAttributes(), $values));
        $u->saveQuietly();

        $roles = array_values(array_filter($p['roles'] ?? [], fn ($r) => Role::where('name', $r)->where('guard_name', 'web')->exists()));
        $u->syncRoles($roles);
        $ids = collect($p['branch_uids'] ?? [])->map(fn ($x) => RowCodec::idOf(Branch::class, $x))->filter()->all();
        $u->branches()->sync($ids);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function customer(array $p): void
    {
        $points = $p['__points'] ?? null;
        unset($p['__points']);
        $def = Registry::DOWN['customer'];
        $values = RowCodec::decode($def, $p);
        unset($values['outstanding_balance'], $values['credit_balance']);
        [$id] = RowCodec::upsert($def, $values, 'adopt');
        if ($points !== null) {
            // Head office's balance + what this branch has earned / spent and not yet sent.
            $pending = 0;
            $txs = DB::table('sync_outbox')->where('entity', 'customer_point_tx')->where('status', '!=', 'sent')->pluck('payload');
            $cuid = $p['uid'];
            foreach ($txs as $json) {
                $t = json_decode($json, true);
                if (($t['customer_id__uid'] ?? null) === $cuid) {
                    $pending += (int) ($t['points_earned'] ?? 0) - (int) ($t['points_redeemed'] ?? 0);
                }
            }
            $bal = max(0, (int) $points + $pending);
            $row = DB::table('customer_points')->where('customer_id', $id)->first();
            if ($row) {
                DB::table('customer_points')->where('id', $row->id)->update(['points' => $bal, 'updated_at' => now()]);
            } else {
                DB::table('customer_points')->insert(['uid' => (string) \Illuminate\Support\Str::ulid(), 'customer_id' => $id, 'points' => $bal,
                    'points_value' => 0, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('customers')->where('id', $id)->update(['loyalty_points' => $bal]);
        }
    }

    protected function delete(string $entity, string $uid): void
    {
        switch ($entity) {
            case 'product':
                // never delete history – stop selling it here
                DB::table('products')->where('uid', $uid)->where('owner', 'central')->update(['is_active' => false]);
                break;
            case 'price_list':
                DB::table('price_lists')->where('uid', $uid)->update(['is_active' => false]);
                break;
            case 'price_list_item':
                DB::table('price_list_items')->where('uid', $uid)->delete();
                app(\App\Services\Catalogue\PriceListResolver::class)->flush();
                break;
            case 'user':
                DB::table('users')->where('uid', $uid)->update(['is_active' => false]);
                break;
            case 'setting':
                break;
            default:
                // categories / brands / units / customers stay; they may be referenced by history
                break;
        }
    }
}
