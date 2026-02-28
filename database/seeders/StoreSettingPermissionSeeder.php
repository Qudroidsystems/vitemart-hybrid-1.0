<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class StoreSettingPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'View store setting',
            'Update store setting',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission], [
                'title' => 'Store Settings Management'
            ]);
        }
    }
}
