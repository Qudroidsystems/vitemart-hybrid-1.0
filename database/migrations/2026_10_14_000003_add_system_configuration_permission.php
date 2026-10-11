<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Manage system configuration" – server address, head office link, database,
 * email and maintenance tools. Given to Super Admin only: it can change the
 * database connection, so grant it to other roles with care.
 */
return new class extends Migration
{
    private string $name = 'Manage system configuration';

    public function up(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }
        $id = DB::table('permissions')->where('name', $this->name)->where('guard_name', 'web')->value('id');
        if (!$id) {
            $row = ['name' => $this->name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()];
            if (Schema::hasColumn('permissions', 'title')) {
                $row['title'] = 'System';
            }
            $id = DB::table('permissions')->insertGetId($row);
        }
        foreach (DB::table('roles')->where('name', 'Super Admin')->pluck('id') as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $roleId]);
        }
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            $id = DB::table('permissions')->where('name', $this->name)->value('id');
            if ($id) {
                DB::table('role_has_permissions')->where('permission_id', $id)->delete();
                DB::table('permissions')->where('id', $id)->delete();
            }
        }
    }
};
