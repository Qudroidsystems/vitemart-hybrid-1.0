<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\FieldPolicy;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductUnit;
use App\Models\ProductVariation;
use App\Models\SystemSetting;
use App\Models\Unit;
use App\Models\User;
use App\Services\MultiBranch\Install;
use App\Services\Sync\Feed;
use App\Services\Sync\Outbox;
use App\Services\Sync\Registry;
use App\Services\Sync\SyncContext;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Events\PermissionAttached;
use Spatie\Permission\Events\PermissionDetached;
use Spatie\Permission\Events\RoleAttached;
use Spatie\Permission\Events\RoleDetached;
use Spatie\Permission\Models\Role;

/**
 * Wires model changes into sync:
 *   branch server → sync_outbox (what to push)
 *   head office   → sync_dirty  (what to put in the feed)
 * Both are switched off while sync data itself is being applied.
 */
class SyncServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // ---------------- branch: outbox
        foreach (Registry::UP as $entity => $def) {
            if ($entity === 'user') {
                Event::listen('eloquent.created: ' . $def['model'], fn ($m) => Outbox::record($m));
                continue;
            }
            Event::listen('eloquent.saved: ' . $def['model'], fn ($m) => Outbox::record($m));
        }
        foreach ([ProductVariation::class, ProductUnit::class, ProductBarcode::class] as $child) {
            Event::listen(['eloquent.saved: ' . $child, 'eloquent.deleted: ' . $child], function ($m) {
                if (Install::isBranch() && !SyncContext::applying() && ($p = $m->product) && $p->owner !== 'central') {
                    Outbox::record($p);
                }
            });
        }

        // ---------------- head office: feed
        $mark = function (string $entity, callable $uid) {
            return function ($m) use ($entity, $uid) {
                if (Install::isCentral() && !SyncContext::applying()) {
                    Feed::mark($entity, $uid($m));
                }
            };
        };
        $both = fn (string $class) => ['eloquent.saved: ' . $class, 'eloquent.deleted: ' . $class];

        Event::listen($both(Product::class), function ($p) {
            if (Install::isCentral() && !SyncContext::applying() && ($p->owner === 'central' || $p->wasChanged('owner'))) {
                Feed::mark('product', $p->uid);
            }
        });
        foreach ([ProductVariation::class, ProductUnit::class, ProductBarcode::class] as $child) {
            Event::listen($both($child), function ($m) {
                if (Install::isCentral() && !SyncContext::applying() && ($p = Product::find($m->product_id)) && $p->owner === 'central') {
                    Feed::mark('product', $p->uid);
                }
            });
        }
        // (also while applying a branch's data: a category / brand / unit head office learns from one branch goes to all)
        $markAlways = fn (string $entity) => function ($m) use ($entity) {
            if (Install::isCentral()) {
                Feed::mark($entity, $m->uid);
            }
        };
        Event::listen($both(Category::class), $markAlways('category'));
        Event::listen($both(Brand::class), $markAlways('brand'));
        Event::listen($both(Unit::class), $markAlways('unit'));
        Event::listen($both(PriceList::class), $mark('price_list', fn ($m) => $m->uid));
        Event::listen($both(PriceListItem::class), $mark('price_list_item', fn ($m) => $m->uid));
        Event::listen('eloquent.saved: ' . FieldPolicy::class, $mark('policies', fn () => 'all'));
        Event::listen('eloquent.saved: ' . Branch::class, $mark('branch', fn ($m) => $m->uid));
        Event::listen($both(Customer::class), $mark('customer', fn ($m) => $m->uid));
        Event::listen('eloquent.saved: ' . SystemSetting::class, function ($s) {
            if (Install::isCentral() && !SyncContext::applying() && in_array($s->key, Registry::centralSettings(), true)) {
                Feed::mark('setting', $s->key);
            }
        });
        Event::listen('eloquent.saved: ' . User::class, function (User $u) {
            if (!Install::isCentral() || SyncContext::applying()) {
                return;
            }
            $relevant = ['first_name', 'last_name', 'username', 'email', 'phone_number', 'password', 'is_active', 'login_rule',
                'login_start_time', 'login_end_time', 'login_days', 'force_logout_at', 'pos_pin', 'max_discount_percent', 'branch_id'];
            if ($u->wasRecentlyCreated || array_intersect(array_keys($u->getChanges()), $relevant)) {
                Feed::mark('user', $u->uid);
            }
        });
        Event::listen('eloquent.saved: ' . Role::class, $mark('role', fn ($m) => $m->name));
        Event::listen([RoleAttached::class, RoleDetached::class], function ($e) {
            if ($e->model instanceof User) {
                Feed::mark('user', $e->model->uid);
            }
        });
        Event::listen([PermissionAttached::class, PermissionDetached::class], function ($e) {
            if ($e->model instanceof Role) {
                Feed::mark('role', $e->model->name);
            }
        });
    }
}
