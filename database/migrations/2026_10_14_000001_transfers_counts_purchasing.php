<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-branch phase 6 – moving and buying stock.
 *
 *  stock_transfers (+lines)      branch ↔ branch / warehouse → branch, with dispatch, receipt and discrepancies
 *  stock_transfer_postings       which stock effects this server has already posted for a transfer (exactly once)
 *  stock_counts (+lines)         blind stock-takes; submitting creates a count adjustment for approval
 *  suppliers, purchase_orders (+lines), goods_receipts (+lines), supplier_returns (+lines)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('stock_transfers')) {
            Schema::create('stock_transfers', function (Blueprint $t) {
                $t->id();
                $t->char('uid', 26)->unique();
                $t->string('number', 40)->index();
                $t->unsignedBigInteger('from_branch_id')->index();
                $t->unsignedBigInteger('to_branch_id')->index();
                $t->string('status', 12)->default('requested'); // requested | approved | dispatched | received | closed | cancelled | rejected
                $t->unsignedInteger('version')->default(1);
                $t->boolean('has_discrepancy')->default(false);
                $t->string('notes', 500)->nullable();
                $t->string('carrier', 120)->nullable();       // driver / vehicle / waybill
                $t->string('requested_by_name', 120)->nullable();
                $t->timestamp('requested_at')->nullable();
                $t->string('approved_by_name', 120)->nullable();
                $t->timestamp('approved_at')->nullable();
                $t->string('dispatched_by_name', 120)->nullable();
                $t->timestamp('dispatched_at')->nullable();
                $t->string('received_by_name', 120)->nullable();
                $t->timestamp('received_at')->nullable();
                $t->string('closed_by_name', 120)->nullable();
                $t->timestamp('closed_at')->nullable();
                $t->string('cancel_reason', 255)->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('stock_transfer_lines')) {
            Schema::create('stock_transfer_lines', function (Blueprint $t) {
                $t->id();
                $t->char('uid', 26)->unique();
                $t->unsignedBigInteger('stock_transfer_id')->index();
                $t->unsignedBigInteger('product_id');
                $t->unsignedBigInteger('product_variation_id')->nullable();
                $t->unsignedBigInteger('product_unit_id')->nullable();   // pack it was asked for in (display only)
                $t->decimal('qty_per_unit', 15, 3)->default(1);
                $t->decimal('requested_qty', 15, 3)->default(0);        // all quantities in base units
                $t->decimal('approved_qty', 15, 3)->nullable();
                $t->decimal('dispatched_qty', 15, 3)->nullable();
                $t->decimal('received_qty', 15, 3)->nullable();
                $t->decimal('unit_cost', 15, 2)->nullable();
                $t->decimal('discrepancy_qty', 15, 3)->default(0);      // received − dispatched
                $t->string('discrepancy_status', 12)->default('none');  // none | open | written_off | found | charged
                $t->string('discrepancy_note', 255)->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('stock_transfer_postings')) {
            Schema::create('stock_transfer_postings', function (Blueprint $t) {
                $t->id();
                $t->char('transfer_uid', 26);
                $t->string('step', 20);
                $t->timestamp('created_at')->nullable();
                $t->unique(['transfer_uid', 'step']);
            });
        }

        if (!Schema::hasTable('stock_counts')) {
            Schema::create('stock_counts', function (Blueprint $t) {
                $t->id();
                $t->char('uid', 26)->unique();
                $t->string('number', 40);
                $t->unsignedBigInteger('branch_id')->nullable()->index();
                $t->unsignedBigInteger('stock_location_id');
                $t->string('title', 150);
                $t->string('status', 12)->default('open');  // open | submitted | cancelled
                $t->boolean('blind')->default(true);
                $t->decimal('recount_threshold', 8, 2)->nullable(); // % variance that asks for a recount
                $t->unsignedBigInteger('stock_adjustment_id')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('submitted_by')->nullable();
                $t->timestamp('submitted_at')->nullable();
                $t->string('notes', 500)->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('stock_count_lines')) {
            Schema::create('stock_count_lines', function (Blueprint $t) {
                $t->id();
                $t->char('uid', 26)->unique();
                $t->unsignedBigInteger('stock_count_id')->index();
                $t->unsignedBigInteger('product_id');
                $t->unsignedBigInteger('product_variation_id')->nullable();
                $t->decimal('counted_qty', 15, 3)->nullable();
                $t->decimal('system_qty', 15, 3)->nullable();   // filled when submitted
                $t->decimal('variance', 15, 3)->nullable();
                $t->boolean('recount')->default(false);       // waiting for a recount
                $t->boolean('recounted')->default(false);     // has been sent back once already
                $t->unsignedBigInteger('counted_by')->nullable();
                $t->timestamp('counted_at')->nullable();
                $t->timestamps();
                $t->unique(['stock_count_id', 'product_id', 'product_variation_id'], 'count_line_unique');
            });
        }

        if (!Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $t) {
                $t->id();
                $t->char('uid', 26)->unique();
                $t->string('code', 20)->nullable()->unique();
                $t->string('name')->unique();
                $t->string('contact_person', 120)->nullable();
                $t->string('phone', 40)->nullable();
                $t->string('email')->nullable();
                $t->string('address')->nullable();
                $t->string('tax_id', 40)->nullable();
                $t->unsignedSmallInteger('payment_terms_days')->default(0);
                $t->unsignedSmallInteger('lead_time_days')->nullable();
                $t->string('bank_details')->nullable();
                $t->boolean('is_active')->default(true);
                $t->string('notes', 500)->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('purchase_orders')) {
            Schema::create('purchase_orders', function (Blueprint $t) {
                $t->id();
                $t->char('uid', 26)->unique();
                $t->string('number', 40)->index();
                $t->unsignedBigInteger('branch_id')->index();       // delivered to
                $t->unsignedBigInteger('supplier_id')->index();
                $t->string('status', 20)->default('draft');        // draft | submitted | approved | sent | partial | received | closed | cancelled
                $t->unsignedInteger('version')->default(1);
                $t->date('expected_date')->nullable();
                $t->decimal('total', 15, 2)->default(0);
                $t->string('notes', 500)->nullable();
                $t->string('created_by_name', 120)->nullable();
                $t->string('approved_by_name', 120)->nullable();
                $t->timestamp('approved_at')->nullable();
                $t->timestamp('sent_at')->nullable();
                $t->timestamp('closed_at')->nullable();
                $t->string('cancel_reason')->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('purchase_order_lines')) {
            Schema::create('purchase_order_lines', function (Blueprint $t) {
                $t->id();
                $t->char('uid', 26)->unique();
                $t->unsignedBigInteger('purchase_order_id')->index();
                $t->unsignedBigInteger('product_id');
                $t->unsignedBigInteger('product_variation_id')->nullable();
                $t->unsignedBigInteger('product_unit_id')->nullable();
                $t->decimal('qty_per_unit', 15, 3)->default(1);
                $t->decimal('ordered_qty', 15, 3);      // in packs (as ordered)
                $t->decimal('received_qty', 15, 3)->default(0);
                $t->decimal('unit_cost', 15, 2)->default(0); // per pack
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('goods_receipts')) {
            Schema::create('goods_receipts', function (Blueprint $t) {
                $t->id();
                $t->char('uid', 26)->unique();
                $t->string('number', 40)->index();
                $t->unsignedBigInteger('branch_id')->nullable()->index();
                $t->unsignedBigInteger('stock_location_id');
                $t->unsignedBigInteger('supplier_id')->nullable()->index();
                $t->unsignedBigInteger('purchase_order_id')->nullable()->index();
                $t->string('supplier_invoice', 80)->nullable();
                $t->date('invoice_date')->nullable();
                $t->decimal('total', 15, 2)->default(0);
                $t->string('status', 12)->default('posted');
                $t->string('notes', 500)->nullable();
                $t->unsignedBigInteger('received_by')->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('goods_receipt_lines')) {
            Schema::create('goods_receipt_lines', function (Blueprint $t) {
                $t->id();
                $t->char('uid', 26)->unique();
                $t->unsignedBigInteger('goods_receipt_id')->index();
                $t->unsignedBigInteger('purchase_order_line_id')->nullable();
                $t->unsignedBigInteger('product_id');
                $t->unsignedBigInteger('product_variation_id')->nullable();
                $t->unsignedBigInteger('product_unit_id')->nullable();
                $t->decimal('qty_per_unit', 15, 3)->default(1);
                $t->decimal('quantity', 15, 3);           // in packs
                $t->decimal('free_qty', 15, 3)->default(0); // bonus packs (no cost)
                $t->decimal('unit_cost', 15, 2)->default(0); // per pack
                $t->date('expiry_date')->nullable();
                $t->string('batch_number', 60)->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('supplier_returns')) {
            Schema::create('supplier_returns', function (Blueprint $t) {
                $t->id();
                $t->char('uid', 26)->unique();
                $t->string('number', 40)->index();
                $t->unsignedBigInteger('branch_id')->nullable()->index();
                $t->unsignedBigInteger('stock_location_id');
                $t->unsignedBigInteger('supplier_id')->index();
                $t->unsignedBigInteger('goods_receipt_id')->nullable();
                $t->string('reason', 255);
                $t->decimal('total', 15, 2)->default(0);
                $t->string('credit_note', 80)->nullable();
                $t->string('status', 12)->default('posted');
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('supplier_return_lines')) {
            Schema::create('supplier_return_lines', function (Blueprint $t) {
                $t->id();
                $t->char('uid', 26)->unique();
                $t->unsignedBigInteger('supplier_return_id')->index();
                $t->unsignedBigInteger('product_id');
                $t->unsignedBigInteger('product_variation_id')->nullable();
                $t->unsignedBigInteger('product_unit_id')->nullable();
                $t->decimal('qty_per_unit', 15, 3)->default(1);
                $t->decimal('quantity', 15, 3);
                $t->decimal('unit_cost', 15, 2)->default(0);
                $t->timestamps();
            });
        }

        // Settings & permissions
        $now = now();
        if (Schema::hasTable('system_settings')) {
            foreach (['po_approval_limit' => '0', 'count_recount_threshold' => '10'] as $k => $v) {
                if (!DB::table('system_settings')->where('key', $k)->exists()) {
                    DB::table('system_settings')->insert(['key' => $k, 'value' => $v, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }
        if (Schema::hasTable('permissions')) {
            $names = ['Request stock transfer', 'Approve stock transfer', 'Dispatch stock transfer', 'Receive stock transfer', 'Allocate stock',
                'Resolve transfer discrepancy', 'View purchasing', 'Create purchase order', 'Approve purchase order', 'Manage suppliers',
                'Post goods receipt', 'Return to supplier', 'Count stock'];
            foreach ($names as $name) {
                if (!DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->exists()) {
                    DB::table('permissions')->insert(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
                }
            }
            $perms = DB::table('permissions')->whereIn('name', $names)->pluck('id');
            foreach (DB::table('roles')->whereIn('name', ['Super Admin', 'Admin'])->pluck('id') as $roleId) {
                foreach ($perms as $pid) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $pid, 'role_id' => $roleId]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['supplier_return_lines', 'supplier_returns', 'goods_receipt_lines', 'goods_receipts', 'purchase_order_lines', 'purchase_orders',
                     'suppliers', 'stock_count_lines', 'stock_counts', 'stock_transfer_postings', 'stock_transfer_lines', 'stock_transfers'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
