<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PosPermissionTableSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'View pos',
        ];

        foreach ($permissions as $permission) {
            $str = $permission;
            $delimiter = ' ';
            $words = explode($delimiter, $str);

            foreach ($words as $word) {
                if ($word == "pos") {
                    Permission::create([
                        'name'  => $permission,
                        'title' => "Pos Management"
                    ]);
                }
            }
        }

        // Clear cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}