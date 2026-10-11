<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Multi-branch foundations (Phase 1).
 *
 *  - organizations, branches, installation (this server's identity), branch_installations (HQ side)
 *  - branch_enrollment_codes, document_sequences, branch_user
 *  - `uid` (ULID) on every record that travels between servers
 *  - `branch_id` on branch-owned data
 *
 * Existing single-store data becomes branch #1 ("MAIN") in "standalone" mode until
 * `php artisan vitemart:install` turns this server into a central or branch install.
 */
return new class extends Migration
{
    /** Tables that get a permanent ULID. */
    public const UID_TABLES = [
        'users', 'categories', 'brands', 'units', 'products', 'product_variations', 'product_unit', 'product_images',
        'customers', 'customer_points', 'customer_point_transactions', 'stock_locations', 'stocks',
        'orders', 'order_items', 'order_payments', 'refunds', 'stock_adjustments', 'stock_adjustment_items',
        'pos_shifts', 'pos_cash_movements', 'price_histories', 'price_change_batches', 'audit_logs',
    ];

    /** Branch-owned tables. */
    public const BRANCH_TABLES = [
        'users', 'stock_locations', 'stocks', 'orders', 'order_payments', 'refunds', 'stock_adjustments',
        'pos_shifts', 'pos_held_sales', 'price_histories', 'audit_logs', 'customers',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('organizations')) {
            Schema::create('organizations', function (Blueprint $t) {
                $t->id();
                $t->char('uid', 26)->unique();
                $t->string('name');
                $t->string('currency_code', 3)->default('NGN');
                $t->string('timezone')->default('Africa/Lagos');
                $t->boolean('prices_include_tax')->default(true);
                $t->decimal('vat_rate', 5, 2)->default(7.5);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $t) {
                $t->id();
                $t->char('uid', 26)->unique();
                $t->string('code', 10)->unique();            // IKJ
                $t->unsignedTinyInteger('number')->unique();  // 0 = HQ, 1..99 branches (used in barcodes)
                $t->string('name');
                $t->string('type', 20)->default('store');    // store | warehouse
                $t->string('status', 20)->default('active'); // active | suspended | closed
                $t->string('address')->nullable();
                $t->string('phone', 40)->nullable();
                $t->string('email')->nullable();
                $t->unsignedBigInteger('manager_user_id')->nullable();
                $t->json('opening_hours')->nullable();
                $t->string('receipt_header')->nullable();
                $t->string('receipt_footer')->nullable();
                $t->json('settings')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('installation')) {
            Schema::create('installation', function (Blueprint $t) {
                $t->id();
                $t->char('uid', 26)->unique();
                $t->string('mode', 20)->default('standalone'); // standalone | central | branch
                $t->unsignedBigInteger('organization_id')->nullable();
                $t->unsignedBigInteger('branch_id')->nullable();
                $t->string('central_url')->nullable();
                $t->char('remote_installation_uid', 26)->nullable(); // our id as registered at HQ
                $t->text('token')->nullable();        // encrypted
                $t->text('hmac_secret')->nullable();  // encrypted
                $t->timestamp('enrolled_at')->nullable();
                $t->timestamp('bootstrapped_at')->nullable();
                $t->string('app_version', 20)->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('branch_installations')) {
            Schema::create('branch_installations', function (Blueprint $t) {
                $t->id();
                $t->char('uid', 26)->unique();
                $t->unsignedBigInteger('branch_id')->index();
                $t->string('token_hash', 64);
                $t->text('hmac_secret'); // encrypted
                $t->string('fingerprint')->nullable();
                $t->string('status', 20)->default('active'); // active | revoked
                $t->timestamp('enrolled_at')->nullable();
                $t->timestamp('revoked_at')->nullable();
                $t->timestamp('last_seen_at')->nullable();
                $t->timestamp('last_push_at')->nullable();
                $t->timestamp('last_pull_at')->nullable();
                $t->string('last_ip', 45)->nullable();
                $t->string('app_version', 20)->nullable();
                $t->unsignedInteger('pending_events')->default(0);
                $t->unsignedInteger('dead_letters')->default(0);
                $t->unsignedBigInteger('disk_free_mb')->nullable();
                $t->timestamp('last_backup_at')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('branch_enrollment_codes')) {
            Schema::create('branch_enrollment_codes', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('branch_id')->index();
                $t->string('code_hash', 64)->unique();
                $t->timestamp('expires_at');
                $t->timestamp('used_at')->nullable();
                $t->unsignedBigInteger('used_by_installation_id')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('document_sequences')) {
            Schema::create('document_sequences', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('branch_id');
                $t->string('doc_type', 20);
                $t->unsignedBigInteger('next_value')->default(1);
                $t->timestamps();
                $t->unique(['branch_id', 'doc_type']);
            });
        }

        if (!Schema::hasTable('branch_user')) {
            Schema::create('branch_user', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('branch_id');
                $t->unsignedBigInteger('user_id');
                $t->timestamps();
                $t->unique(['branch_id', 'user_id']);
            });
        }

        // ---------------------------------------------------------------
        // uid + branch_id columns
        // ---------------------------------------------------------------
        foreach (self::UID_TABLES as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'uid')) {
                Schema::table($table, fn (Blueprint $t) => $t->char('uid', 26)->nullable()->after('id'));
            }
        }
        foreach (self::BRANCH_TABLES as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'branch_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->unsignedBigInteger('branch_id')->nullable()->index());
            }
        }
        foreach (['products', 'product_variations', 'customers', 'categories', 'brands'] as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'origin_branch_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->unsignedBigInteger('origin_branch_id')->nullable());
            }
        }
        if (!Schema::hasColumn('products', 'owner')) {
            Schema::table('products', function (Blueprint $t) {
                $t->string('owner', 10)->default('branch');          // central | branch
                $t->string('central_status', 20)->default('local');  // local | submitted | published
                $t->unsignedInteger('version')->default(1);
            });
        }

        // ---------------------------------------------------------------
        // Seed: organisation, branch #1, installation (standalone)
        // ---------------------------------------------------------------
        $store = Schema::hasTable('store_settings') ? DB::table('store_settings')->first() : null;
        $orgId = DB::table('organizations')->value('id') ?? DB::table('organizations')->insertGetId([
            'uid' => (string) Str::ulid(),
            'name' => $store->store_name ?? config('app.name', 'Supermarket'),
            'currency_code' => $store->currency_code ?? 'NGN',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->orderBy('id')->value('id') ?? DB::table('branches')->insertGetId([
            'uid' => (string) Str::ulid(),
            'code' => 'MAIN',
            'number' => 1,
            'name' => $store->store_name ?? 'Main Store',
            'type' => 'store',
            'address' => $store->address ?? null,
            'phone' => $store->phone ?? null,
            'email' => $store->email ?? null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        if (!DB::table('installation')->exists()) {
            DB::table('installation')->insert([
                'uid' => (string) Str::ulid(),
                'mode' => 'standalone',
                'organization_id' => $orgId,
                'branch_id' => $branchId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach (self::BRANCH_TABLES as $table) {
            if (Schema::hasColumn($table, 'branch_id')) {
                DB::table($table)->whereNull('branch_id')->update(['branch_id' => $branchId]);
            }
        }
        // Existing products: owned by this store until it is attached to an HQ.
        DB::table('products')->whereNull('origin_branch_id')->update(['origin_branch_id' => $branchId]);

        // Backfill ULIDs in creation order so they stay time-sortable.
        foreach (self::UID_TABLES as $table) {
            if (!Schema::hasColumn($table, 'uid')) {
                continue;
            }
            $hasCreated = Schema::hasColumn($table, 'created_at');
            DB::table($table)->whereNull('uid')
                ->orderBy($hasCreated ? 'created_at' : 'id')->orderBy('id')
                ->select('id', $hasCreated ? 'created_at' : DB::raw('NULL as created_at'))
                ->chunk(1000, function ($rows) use ($table) {
                    foreach ($rows as $r) {
                        $ts = $r->created_at ? \Carbon\Carbon::parse($r->created_at) : now();
                        DB::table($table)->where('id', $r->id)->update(['uid' => (string) Str::ulid($ts)]);
                    }
                });
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->char('uid', 26)->nullable(false)->change();
                $t->unique('uid', "{$table}_uid_unique");
            });
        }
    }

    public function down(): void
    {
        foreach (self::UID_TABLES as $table) {
            if (Schema::hasColumn($table, 'uid')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->dropUnique("{$table}_uid_unique");
                    $t->dropColumn('uid');
                });
            }
        }
        foreach (self::BRANCH_TABLES as $table) {
            if (Schema::hasColumn($table, 'branch_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('branch_id'));
            }
        }
        foreach (['products', 'product_variations', 'customers', 'categories', 'brands'] as $table) {
            if (Schema::hasColumn($table, 'origin_branch_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('origin_branch_id'));
            }
        }
        if (Schema::hasColumn('products', 'owner')) {
            Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['owner', 'central_status', 'version']));
        }
        foreach (['branch_user', 'document_sequences', 'branch_enrollment_codes', 'branch_installations', 'installation', 'branches', 'organizations'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
