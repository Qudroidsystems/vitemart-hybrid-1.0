<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Multi-branch phase 3 – catalogue.
 *
 *  - product_barcodes: one row per barcode (primary + extra codes), unique across the catalogue
 *  - price_lists / price_list_items: branch prices that override the catalogue price
 *  - field_policies: what a branch may change on a head-office product
 *  - product_local_overrides: fields a branch has changed locally (sync must not overwrite them)
 *  - price_change_requests: a branch asks head office to change a price
 *  - product_submissions: a branch offers a product it created to the head-office catalogue
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_barcodes')) {
            Schema::create('product_barcodes', function (Blueprint $t) {
                $t->id();
                $t->char('uid', 26)->unique();
                $t->string('barcode', 64)->unique();
                $t->unsignedBigInteger('product_id')->index();
                $t->unsignedBigInteger('product_variation_id')->nullable()->index();
                $t->unsignedBigInteger('product_unit_id')->nullable()->index();
                $t->string('kind', 12)->default('primary'); // primary | extra
                $t->string('note')->nullable();
                $t->unsignedBigInteger('origin_branch_id')->nullable();
                $t->timestamps();
            });

            // Backfill from the existing barcode columns (first owner wins on duplicates).
            $now = now();
            $rows = [];
            $seen = [];
            $add = function ($code, $pid, $vid, $uid) use (&$rows, &$seen, $now) {
                $code = trim((string) $code);
                if ($code === '' || isset($seen[$code]) || !$pid) {
                    return;
                }
                $seen[$code] = true;
                $rows[] = ['uid' => (string) Str::ulid(), 'barcode' => $code, 'product_id' => $pid, 'product_variation_id' => $vid,
                    'product_unit_id' => $uid, 'kind' => 'primary', 'created_at' => $now, 'updated_at' => $now];
            };
            foreach (DB::table('products')->whereNotNull('barcode')->where('barcode', '!=', '')->orderBy('id')->get(['id', 'barcode']) as $r) {
                $add($r->barcode, $r->id, null, null);
            }
            foreach (DB::table('product_variations')->whereNotNull('barcode')->where('barcode', '!=', '')->orderBy('id')->get(['id', 'product_id', 'barcode']) as $r) {
                $add($r->barcode, $r->product_id, $r->id, null);
            }
            if (Schema::hasTable('product_unit')) {
                foreach (DB::table('product_unit')->whereNotNull('barcode')->where('barcode', '!=', '')->orderBy('id')->get(['id', 'product_id', 'barcode']) as $r) {
                    $add($r->barcode, $r->product_id, null, $r->id);
                }
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('product_barcodes')->insert($chunk);
            }
        }

        if (!Schema::hasTable('price_lists')) {
            Schema::create('price_lists', function (Blueprint $t) {
                $t->id();
                $t->char('uid', 26)->unique();
                $t->string('code', 30)->unique();
                $t->string('name');
                $t->unsignedBigInteger('branch_id')->nullable()->index(); // null = every branch
                $t->unsignedSmallInteger('priority')->default(10);       // higher wins
                $t->boolean('is_active')->default(true);
                $t->timestamp('starts_at')->nullable();
                $t->timestamp('ends_at')->nullable();
                $t->string('owner', 10)->default('central');             // central | branch
                $t->string('notes')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('price_list_items')) {
            Schema::create('price_list_items', function (Blueprint $t) {
                $t->id();
                $t->char('uid', 26)->unique();
                $t->unsignedBigInteger('price_list_id');
                $t->unsignedBigInteger('product_id')->index();
                $t->unsignedBigInteger('product_variation_id')->nullable();
                $t->unsignedBigInteger('product_unit_id')->nullable();
                $t->string('item_key', 60);                           // p12-v0-u0
                $t->decimal('price', 15, 2)->nullable();
                $t->decimal('wholesale_price', 15, 2)->nullable();
                $t->decimal('promo_price', 15, 2)->nullable();
                $t->timestamp('promo_starts_at')->nullable();
                $t->timestamp('promo_ends_at')->nullable();
                $t->unsignedInteger('version')->default(1);
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->unique(['price_list_id', 'item_key']);
            });
        }

        if (!Schema::hasTable('field_policies')) {
            Schema::create('field_policies', function (Blueprint $t) {
                $t->id();
                $t->string('field', 40)->unique();
                $t->string('label');
                $t->string('branch_rule', 10)->default('locked'); // locked | request | override
                $t->string('help')->nullable();
                $t->timestamps();
            });
            $now = now();
            $p = fn ($f, $l, $r, $h = null) => ['field' => $f, 'label' => $l, 'branch_rule' => $r, 'help' => $h, 'created_at' => $now, 'updated_at' => $now];
            DB::table('field_policies')->insert([
                $p('title', 'Name', 'locked'),
                $p('description', 'Description', 'locked'),
                $p('barcode', 'Barcodes', 'locked', 'Branches can add extra barcodes but not change the main one.'),
                $p('sku', 'SKU', 'locked'),
                $p('category_id', 'Category', 'locked'),
                $p('brand_id', 'Brand', 'locked'),
                $p('base_unit_id', 'Base unit', 'locked'),
                $p('product_type', 'Product type', 'locked'),
                $p('variations', 'Variations', 'locked'),
                $p('packs', 'Packs (carton, pack…)', 'locked'),
                $p('plu_code', 'Scale PLU code', 'locked'),
                $p('is_weighted', 'Sold by weight', 'locked'),
                $p('track_stock', 'Track stock', 'locked'),
                $p('cost_price', 'Cost price', 'locked', 'Branch cost comes from goods received.'),
                $p('price', 'Selling price', 'request', 'The branch asks head office; approved prices apply to that branch.'),
                $p('wholesale_price', 'Wholesale price', 'request'),
                $p('promo_price', 'Promo price', 'request'),
                $p('pack_price', 'Pack prices', 'request'),
                $p('reorder_level', 'Reorder level', 'override', 'Each branch sets its own.'),
                $p('is_active', 'Sold at this branch', 'override', 'A branch can stop selling an item locally.'),
                $p('allow_price_override', 'Cashier may change price', 'locked'),
            ]);
        }

        if (!Schema::hasTable('product_local_overrides')) {
            Schema::create('product_local_overrides', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('product_id');
                $t->string('field', 40);
                $t->timestamps();
                $t->unique(['product_id', 'field']);
            });
        }

        if (!Schema::hasTable('price_change_requests')) {
            Schema::create('price_change_requests', function (Blueprint $t) {
                $t->id();
                $t->char('uid', 26)->unique();
                $t->unsignedBigInteger('branch_id')->index();
                $t->unsignedBigInteger('product_id')->nullable()->index();
                $t->char('product_uid', 26)->index();
                $t->char('variation_uid', 26)->nullable();
                $t->char('pack_uid', 26)->nullable();
                $t->string('item_label');
                $t->string('field', 30);                      // price | wholesale_price | promo_price
                $t->decimal('current_value', 15, 2)->nullable();
                $t->decimal('requested_value', 15, 2);
                $t->timestamp('promo_starts_at')->nullable();
                $t->timestamp('promo_ends_at')->nullable();
                $t->string('reason')->nullable();
                $t->string('status', 12)->default('pending'); // pending | approved | rejected | cancelled
                $t->boolean('sent')->default(false);          // branch side: delivered to HQ
                $t->string('requested_by_name')->nullable();
                $t->unsignedBigInteger('requested_by')->nullable();
                $t->decimal('approved_value', 15, 2)->nullable();
                $t->string('scope', 10)->nullable();          // branch | all
                $t->string('decided_by_name')->nullable();
                $t->timestamp('decided_at')->nullable();
                $t->string('decision_note')->nullable();
                $t->boolean('applied')->default(false);       // branch side: decision applied locally
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('product_submissions')) {
            Schema::create('product_submissions', function (Blueprint $t) {
                $t->id();
                $t->char('uid', 26)->unique();
                $t->unsignedBigInteger('branch_id')->index();
                $t->char('product_uid', 26)->index();
                $t->string('title');
                $t->string('barcode', 64)->nullable();
                $t->json('payload');
                $t->string('status', 12)->default('pending'); // pending | approved | rejected
                $t->boolean('sent')->default(false);
                $t->string('submitted_by_name')->nullable();
                $t->string('note')->nullable();
                $t->string('decided_by_name')->nullable();
                $t->timestamp('decided_at')->nullable();
                $t->string('decision_note')->nullable();
                $t->unsignedBigInteger('central_product_id')->nullable();
                $t->boolean('applied')->default(false);
                $t->timestamps();
            });
        }

        // Permissions
        if (Schema::hasTable('permissions')) {
            $now = now();
            foreach (['Manage price lists', 'Request price change', 'Approve price change', 'Submit product to head office',
                         'Approve product submission', 'Manage field policies'] as $name) {
                if (!DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->exists()) {
                    DB::table('permissions')->insert(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
                }
            }
            $perms = DB::table('permissions')->whereIn('name', ['Manage price lists', 'Request price change', 'Approve price change',
                'Submit product to head office', 'Approve product submission', 'Manage field policies'])->pluck('id');
            foreach (DB::table('roles')->whereIn('name', ['Super Admin', 'Admin'])->pluck('id') as $roleId) {
                foreach ($perms as $pid) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $pid, 'role_id' => $roleId]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['product_submissions', 'price_change_requests', 'product_local_overrides', 'field_policies',
                     'price_list_items', 'price_lists', 'product_barcodes'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
