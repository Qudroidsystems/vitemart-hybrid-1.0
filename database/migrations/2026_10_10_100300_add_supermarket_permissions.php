<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers the permissions used by the new modules and grants them to
 * "Super Admin" (and "Admin", where it exists).
 */
return new class extends Migration
{
    private array $permissions = [
        'Security'          => ['Manage login control', 'View active sessions', 'Force logout users'],
        'Audit Trail'       => ['View audit trail', 'Export audit trail'],
        'Price Control'     => ['Manage price control', 'View price history', 'Revert price change'],
        'Stock Adjustment'  => ['View stock adjustment', 'Create stock adjustment', 'Approve stock adjustment', 'Receive stock'],
        'Pos Management'    => ['Override pos price', 'Give pos discount', 'Void pos sale', 'Process pos return', 'Sell below cost', 'Manage pos shifts', 'View all pos shifts'],
        'Product Management' => ['Print barcode labels'],
        'Dashboard'         => ['dashboard', 'View profit figures'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $hasTitle = Schema::hasColumn('permissions', 'title');
        $ids = [];

        foreach ($this->permissions as $title => $names) {
            foreach ($names as $name) {
                $existing = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->first();
                if ($existing) {
                    $ids[] = $existing->id;
                    continue;
                }
                $row = ['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()];
                if ($hasTitle) {
                    $row['title'] = $title;
                }
                $ids[] = DB::table('permissions')->insertGetId($row);
            }
        }

        $roles = DB::table('roles')->whereIn('name', ['Super Admin', 'Admin'])->pluck('id');
        foreach ($roles as $roleId) {
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
        $names = collect($this->permissions)->flatten()->reject(fn ($n) => $n === 'dashboard')->all();
        DB::table('permissions')->whereIn('name', $names)->delete();
    }
};
