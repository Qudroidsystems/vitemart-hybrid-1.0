<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $permissions = [
        'Branches' => ['Manage branches', 'View branches', 'Access all branches'],
        'Synchronization' => ['Manage sync', 'View sync status'],
        'Product Distribution' => ['Distribute products', 'Publish products globally', 'Manage central products'],
        'Transfers' => ['Request stock transfer', 'Approve stock transfer', 'Dispatch stock transfer', 'Receive stock transfer', 'Allocate stock'],
        'Purchasing' => ['View purchasing', 'Create purchase order', 'Approve purchase order', 'Manage suppliers', 'Post goods receipt'],
    ];

    public function up(): void
    {
        Schema::table('stock_locations', function (Blueprint $t) {
            if (!Schema::hasColumn('stock_locations', 'type')) {
                $t->string('type', 20)->default('store')->after('code'); // store | warehouse | transit | quarantine
            }
        });

        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users', 'two_factor_secret')) {
                $t->text('two_factor_secret')->nullable();
                $t->text('two_factor_recovery_codes')->nullable();
                $t->timestamp('two_factor_confirmed_at')->nullable();
            }
        });

        $hasTitle = Schema::hasColumn('permissions', 'title');
        $ids = [];
        foreach ($this->permissions as $title => $names) {
            foreach ($names as $name) {
                $id = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
                if (!$id) {
                    $row = ['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()];
                    if ($hasTitle) {
                        $row['title'] = $title;
                    }
                    $id = DB::table('permissions')->insertGetId($row);
                }
                $ids[] = $id;
            }
        }
        foreach (DB::table('roles')->whereIn('name', ['Super Admin', 'Admin'])->pluck('id') as $roleId) {
            foreach ($ids as $pid) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $pid, 'role_id' => $roleId]);
            }
        }
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('stock_locations', 'type')) {
            Schema::table('stock_locations', fn (Blueprint $t) => $t->dropColumn('type'));
        }
        if (Schema::hasColumn('users', 'two_factor_secret')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']));
        }
        DB::table('permissions')->whereIn('name', collect($this->permissions)->flatten()->all())->delete();
    }
};
