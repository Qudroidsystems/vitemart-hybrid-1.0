<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-branch phase 5 – synchronisation.
 *
 *  Branch → head office (push)          Head office → branches (pull)
 *  ─────────────────────────────         ──────────────────────────────
 *  sync_outbox   events to send          sync_dirty   things that changed at HQ
 *  sync_inbox    (HQ) events received    sync_feed    the change feed branches read with a cursor
 *  sync_state    (branch) cursor, last push / pull
 *  sync_commands (HQ) instructions for one branch; sync_command_runs (branch) what it already ran
 *  sync_uid_aliases (HQ) a branch record that turned out to be the same as an existing one
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sync_outbox')) {
            Schema::create('sync_outbox', function (Blueprint $t) {
                $t->id();
                $t->char('event_uid', 26)->unique();
                $t->string('entity', 40);
                $t->char('entity_uid', 26);
                $t->string('op', 10)->default('upsert');
                $t->longText('payload');
                $t->string('status', 10)->default('pending'); // pending | sent | dead
                $t->unsignedSmallInteger('attempts')->default(0);
                $t->string('last_error', 500)->nullable();
                $t->timestamp('next_attempt_at')->nullable();
                $t->timestamp('sent_at')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->index(['status', 'id']);
                $t->index(['entity', 'entity_uid']);
            });
        }

        if (!Schema::hasTable('sync_inbox')) {
            Schema::create('sync_inbox', function (Blueprint $t) {
                $t->id();
                $t->char('event_uid', 26)->unique();
                $t->unsignedBigInteger('branch_id')->index();
                $t->unsignedBigInteger('branch_installation_id')->nullable();
                $t->string('entity', 40);
                $t->char('entity_uid', 26);
                $t->string('status', 10);           // applied | failed
                $t->string('error', 500)->nullable();
                $t->unsignedSmallInteger('attempts')->default(1);
                $t->timestamps();
                $t->index(['status', 'branch_id']);
            });
        }

        if (!Schema::hasTable('sync_dirty')) {
            Schema::create('sync_dirty', function (Blueprint $t) {
                $t->id();
                $t->string('entity', 40);
                $t->string('entity_uid', 64);
                $t->timestamp('created_at')->nullable();
                $t->unique(['entity', 'entity_uid']);
            });
        }

        if (!Schema::hasTable('sync_feed')) {
            Schema::create('sync_feed', function (Blueprint $t) {
                $t->id();                                   // the cursor
                $t->string('entity', 40);
                $t->string('entity_uid', 64);
                $t->unsignedBigInteger('branch_id')->nullable(); // null = every branch
                $t->string('op', 10)->default('upsert');    // upsert | delete
                $t->longText('payload')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->index(['entity', 'entity_uid']);
                $t->index(['branch_id', 'id']);
            });
        }

        if (!Schema::hasTable('sync_state')) {
            Schema::create('sync_state', function (Blueprint $t) {
                $t->string('key', 60)->primary();
                $t->text('value')->nullable();
                $t->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('sync_commands')) {
            Schema::create('sync_commands', function (Blueprint $t) {
                $t->id();
                $t->char('uid', 26)->unique();
                $t->unsignedBigInteger('branch_id')->index();
                $t->string('command', 40);
                $t->json('payload')->nullable();
                $t->string('min_version', 20)->nullable();
                $t->string('status', 12)->default('pending'); // pending | done | failed | refused | expired
                $t->text('result')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamp('delivered_at')->nullable();
                $t->timestamp('finished_at')->nullable();
                $t->timestamp('expires_at')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('sync_command_runs')) {
            Schema::create('sync_command_runs', function (Blueprint $t) {
                $t->char('uid', 26)->primary();
                $t->string('command', 40);
                $t->string('status', 12);
                $t->text('result')->nullable();
                $t->boolean('reported')->default(false);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('sync_uid_aliases')) {
            Schema::create('sync_uid_aliases', function (Blueprint $t) {
                $t->id();
                $t->string('entity', 40);
                $t->char('alias_uid', 26);
                $t->char('target_uid', 26);
                $t->unsignedBigInteger('branch_id')->nullable();
                $t->string('reason')->nullable();
                $t->timestamps();
                $t->unique(['entity', 'alias_uid']);
            });
        }

        if (!Schema::hasTable('product_distributions')) {
            Schema::create('product_distributions', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('product_id');
                $t->unsignedBigInteger('branch_id');
                $t->timestamps();
                $t->unique(['product_id', 'branch_id']);
            });
        }
        if (!Schema::hasColumn('products', 'distribution')) {
            Schema::table('products', fn (Blueprint $t) => $t->string('distribution', 10)->default('all')); // all | selected
        }

        // Several branches each have a "Main store" – names / codes are unique per branch, not globally.
        $indexes = collect(DB::select('SHOW INDEX FROM stock_locations'))->pluck('Key_name')->unique();
        Schema::table('stock_locations', function (Blueprint $t) use ($indexes) {
            if ($indexes->contains('stock_locations_name_unique')) {
                $t->dropUnique('stock_locations_name_unique');
            }
            if ($indexes->contains('stock_locations_code_unique')) {
                $t->dropUnique('stock_locations_code_unique');
            }
            if (!$indexes->contains('stock_locations_branch_code_unique')) {
                $t->unique(['branch_id', 'code'], 'stock_locations_branch_code_unique');
            }
        });

        // Permissions
        if (Schema::hasTable('permissions')) {
            $now = now();
            $names = ['View sync status', 'Manage sync', 'Distribute products'];
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
        foreach (['sync_outbox', 'sync_inbox', 'sync_dirty', 'sync_feed', 'sync_state', 'sync_commands', 'sync_command_runs',
                     'sync_uid_aliases', 'product_distributions'] as $t) {
            Schema::dropIfExists($t);
        }
        if (Schema::hasColumn('products', 'distribution')) {
            Schema::table('products', fn (Blueprint $t) => $t->dropColumn('distribution'));
        }
    }
};
