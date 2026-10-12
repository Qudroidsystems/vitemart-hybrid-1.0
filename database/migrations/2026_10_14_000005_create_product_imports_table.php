<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Product spreadsheet uploads: each is checked first, then applied in batches. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_imports')) {
            Schema::create('product_imports', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->nullable()->index();
                $t->string('original_name');
                $t->string('ext', 8);
                $t->string('status', 16)->default('checking');   // checking | checked | applying | done | failed | cancelled
                $t->json('options')->nullable();
                $t->unsignedInteger('total_rows')->default(0);
                $t->unsignedInteger('processed')->default(0);
                $t->unsignedBigInteger('cursor')->default(0);     // byte offset in the staged rows file
                $t->json('counts')->nullable();
                $t->json('issues')->nullable();
                $t->json('preview')->nullable();
                $t->json('columns')->nullable();                 // recognised / ignored headers
                $t->json('result')->nullable();
                $t->text('error')->nullable();
                $t->timestamp('applied_at')->nullable();
                $t->timestamps();
            });
        }
        if (Schema::hasTable('permissions')) {
            $id = DB::table('permissions')->where('name', 'Import products')->where('guard_name', 'web')->value('id');
            if (!$id) {
                $row = ['name' => 'Import products', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()];
                if (Schema::hasColumn('permissions', 'title')) {
                    $row['title'] = 'Product Management';
                }
                $id = DB::table('permissions')->insertGetId($row);
            }
            foreach (DB::table('roles')->whereIn('name', ['Super Admin', 'Admin'])->pluck('id') as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $roleId]);
            }
            try {
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            } catch (\Throwable $e) {
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_imports');
        if (Schema::hasTable('permissions')) {
            $id = DB::table('permissions')->where('name', 'Import products')->value('id');
            if ($id) {
                DB::table('role_has_permissions')->where('permission_id', $id)->delete();
                DB::table('permissions')->where('id', $id)->delete();
            }
        }
    }
};
