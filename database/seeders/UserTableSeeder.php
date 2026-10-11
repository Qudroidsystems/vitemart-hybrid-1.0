<?php

namespace Database\Seeders;

use App\Models\BioModel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserTableSeeder extends Seeder
{
    /**
     * Default Super Admin login.
     */
    public const ADMIN_LOGIN = 'eliabsiji@admin.com';
    public const ADMIN_PASSWORD = '12345678';

    /**
     * Run the database seeds.
     *
     * Safe to run more than once: the admin user and roles are
     * created if missing and updated if they already exist.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // ------------------------------------------------------------
        // 1. Default admin user
        //    (the old `role` column was dropped from users; roles are
        //     handled by Spatie below)
        // ------------------------------------------------------------
        $attributes = [
            'first_name' => 'Ilemobayo',
            'last_name'  => 'Eliab',
            'username'   => self::ADMIN_LOGIN,
            'password'   => Hash::make(self::ADMIN_PASSWORD),
        ];

        // Optional columns - only set them if this database has them.
        $optional = [
            'avatar'    => 'unnamed.png',
            'is_active' => true,
        ];

        foreach ($optional as $column => $value) {
            if (Schema::hasColumn('users', $column)) {
                $attributes[$column] = $value;
            }
        }

        $user = User::updateOrCreate(
            ['email' => self::ADMIN_LOGIN],
            $attributes
        );

        // ------------------------------------------------------------
        // 2. Staff bio
        // ------------------------------------------------------------
        if (Schema::hasTable('staff_bio')) {
            BioModel::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'firstname'     => 'Ilemobayo',
                    'lastname'      => 'Eliab',
                    'othernames'    => 'siji',
                    'phone'         => '98385523567',
                    'address'       => 'ondo',
                    'gender'        => 'male',
                    'maritalstatus' => 'Single',
                    'nationality'   => 'nigerian',
                    'dob'           => '12-12-12',
                ]
            );
        }

        // ------------------------------------------------------------
        // 3. Roles (create if missing, keep badges up to date)
        // ------------------------------------------------------------
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $admin      = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        if (Schema::hasColumn('roles', 'badge')) {
            $superAdmin->forceFill(['badge' => 'badge bg-success'])->save();
            $admin->forceFill(['badge' => 'badge bg-primary'])->save();
        }

        // ------------------------------------------------------------
        // 4. Give both roles every permission and assign them to admin
        //    (DatabaseSeeder re-syncs Super Admin at the end, so it also
        //     gets permissions added by later seeders)
        // ------------------------------------------------------------
        $permissionIds = Permission::where('guard_name', 'web')->pluck('id')->all();

        $superAdmin->syncPermissions($permissionIds);
        $admin->syncPermissions($permissionIds);

        $user->syncRoles([$superAdmin, $admin]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
