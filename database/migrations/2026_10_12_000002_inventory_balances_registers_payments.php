<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Multi-branch phase 4 – the branch till.
 *
 *  - inventory_balances: per branch & item → on hand (sellable), reserved, quarantined, in transit, incoming
 *  - stock_reservations: stock promised to something (transfer, online order) and not yet moved
 *  - cash_registers: each till, which stock location it sells from; shifts and sales record the register
 *  - order_payments.status: transfers can wait for confirmation; failed / reversed payments are tracked
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('inventory_balances')) {
            Schema::create('inventory_balances', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('branch_id');
                $t->unsignedBigInteger('product_id');
                $t->unsignedBigInteger('variation_key')->default(0); // variation id, 0 = none
                $t->decimal('on_hand', 15, 3)->default(0);       // in store / warehouse locations
                $t->decimal('reserved', 15, 3)->default(0);      // promised, not yet moved
                $t->decimal('quarantined', 15, 3)->default(0);   // damaged / held back
                $t->decimal('in_transit', 15, 3)->default(0);    // dispatched, not yet received
                $t->decimal('incoming', 15, 3)->default(0);      // expected (transfers / purchase orders)
                $t->timestamp('updated_at')->nullable();
                $t->unique(['branch_id', 'product_id', 'variation_key'], 'inv_bal_unique');
                $t->index(['product_id']);
            });
        }

        if (!Schema::hasTable('stock_reservations')) {
            Schema::create('stock_reservations', function (Blueprint $t) {
                $t->id();
                $t->char('uid', 26)->unique();
                $t->unsignedBigInteger('branch_id')->index();
                $t->unsignedBigInteger('product_id');
                $t->unsignedBigInteger('product_variation_id')->nullable();
                $t->decimal('quantity', 15, 3);
                $t->string('reference_type', 30);    // transfer | order | …
                $t->string('reference', 60);
                $t->string('status', 12)->default('active'); // active | released | consumed
                $t->timestamp('expires_at')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->index(['reference_type', 'reference']);
            });
        }

        if (!Schema::hasTable('cash_registers')) {
            Schema::create('cash_registers', function (Blueprint $t) {
                $t->id();
                $t->char('uid', 26)->unique();
                $t->unsignedBigInteger('branch_id')->index();
                $t->string('code', 20);
                $t->string('name');
                $t->unsignedBigInteger('stock_location_id')->nullable();
                $t->string('status', 12)->default('active');
                $t->string('receipt_printer')->nullable();
                $t->timestamp('last_used_at')->nullable();
                $t->unsignedBigInteger('last_user_id')->nullable();
                $t->timestamps();
                $t->unique(['branch_id', 'code']);
            });
        }

        Schema::table('pos_shifts', function (Blueprint $t) {
            if (!Schema::hasColumn('pos_shifts', 'cash_register_id')) {
                $t->unsignedBigInteger('cash_register_id')->nullable()->index();
            }
        });
        Schema::table('orders', function (Blueprint $t) {
            if (!Schema::hasColumn('orders', 'cash_register_id')) {
                $t->unsignedBigInteger('cash_register_id')->nullable();
            }
        });
        Schema::table('order_payments', function (Blueprint $t) {
            if (!Schema::hasColumn('order_payments', 'status')) {
                $t->string('status', 12)->default('confirmed')->index(); // confirmed | pending | failed | reversed
                $t->string('provider', 40)->nullable();                 // bank / POS terminal name
                $t->unsignedBigInteger('confirmed_by')->nullable();
                $t->timestamp('confirmed_at')->nullable();
                $t->string('status_note')->nullable();
                $t->unsignedBigInteger('cash_register_id')->nullable();
            }
        });

        // ---- the ledger needs 'transfer_in' (receiving side of a transfer); the original enum lacked it
        $col = DB::selectOne("SELECT COLUMN_TYPE t, IS_NULLABLE n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stocks' AND COLUMN_NAME = 'type'");
        if ($col && str_starts_with(strtolower($col->t), 'enum(') && !str_contains($col->t, "'transfer_in'")) {
            DB::statement("ALTER TABLE stocks MODIFY `type` ENUM('in','out','adjustment','transfer','transfer_in','return','damage') "
                . ($col->n === 'YES' ? 'NULL' : "NOT NULL DEFAULT 'in'"));
        }

        // ---- backfill balances from the ledger
        $sellable = "COALESCE(l.type, 'store') IN ('store', 'warehouse')";
        $signed = "CASE WHEN s.type IN ('in','adjustment','transfer_in','return') THEN s.quantity WHEN s.type IN ('out','damage','transfer') THEN -s.quantity ELSE 0 END";
        $fallbackBranch = (int) (DB::table('installation')->value('branch_id') ?? DB::table('branches')->orderBy('id')->value('id') ?? 0);
        $rows = DB::table('stocks as s')
            ->leftJoin('stock_locations as l', 'l.id', '=', 's.stock_location_id')
            ->whereNull('s.deleted_at')
            ->selectRaw("COALESCE(l.branch_id, s.branch_id, {$fallbackBranch}) as branch_id, s.product_id, COALESCE(s.product_variation_id, 0) as vkey,
                SUM(CASE WHEN {$sellable} THEN {$signed} ELSE 0 END) as on_hand,
                SUM(CASE WHEN l.type = 'quarantine' THEN {$signed} ELSE 0 END) as quarantined,
                SUM(CASE WHEN l.type = 'transit' THEN {$signed} ELSE 0 END) as in_transit")
            ->groupBy(DB::raw("COALESCE(l.branch_id, s.branch_id, {$fallbackBranch})"), 's.product_id', DB::raw('COALESCE(s.product_variation_id, 0)'))
            ->get();
        $now = now();
        foreach ($rows->chunk(500) as $chunk) {
            DB::table('inventory_balances')->upsert($chunk->map(fn ($r) => [
                'branch_id' => $r->branch_id, 'product_id' => $r->product_id, 'variation_key' => $r->vkey,
                'on_hand' => round((float) $r->on_hand, 3), 'quarantined' => round((float) $r->quarantined, 3),
                'in_transit' => round((float) $r->in_transit, 3), 'updated_at' => $now,
            ])->all(), ['branch_id', 'product_id', 'variation_key'], ['on_hand', 'quarantined', 'in_transit', 'updated_at']);
        }

        // ---- one register per branch to start with
        // (only this server's own branch – each branch server creates its own tills)
        $ownBranch = DB::table('installation')->value('branch_id');
        foreach (DB::table('branches')->where('type', 'store')->when($ownBranch, fn ($q) => $q->where('id', $ownBranch))->get() as $b) {
            if (!DB::table('cash_registers')->where('branch_id', $b->id)->exists()) {
                DB::table('cash_registers')->insert([
                    'uid' => (string) Str::ulid(), 'branch_id' => $b->id, 'code' => 'T1', 'name' => 'Till 1',
                    'stock_location_id' => DB::table('stock_locations')->where('branch_id', $b->id)
                        ->where(fn ($q) => $q->where('type', 'store')->orWhereNull('type'))->orderByDesc('is_default')->value('id'),
                    'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        // ---- settings & permissions
        if (Schema::hasTable('system_settings')) {
            foreach (['transfer_needs_confirmation' => '1', 'card_needs_terminal_ref' => '0'] as $k => $v) {
                if (!DB::table('system_settings')->where('key', $k)->exists()) {
                    DB::table('system_settings')->insert(['key' => $k, 'value' => $v, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }
        if (Schema::hasTable('permissions')) {
            $names = ['Manage cash registers', 'Confirm payments', 'View stock balances'];
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
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('cash_registers');
        foreach (['pos_shifts' => ['cash_register_id'], 'orders' => ['cash_register_id'],
                     'order_payments' => ['status', 'provider', 'confirmed_by', 'confirmed_at', 'status_note', 'cash_register_id']] as $table => $cols) {
            foreach ($cols as $c) {
                if (Schema::hasColumn($table, $c)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropColumn($c));
                }
            }
        }
    }
};
