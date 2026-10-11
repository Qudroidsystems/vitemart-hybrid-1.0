<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Default Super Admin login (created by UserTableSeeder).
     */
    public const DEFAULT_ADMIN_LOGIN = 'eliabsiji@admin.com';
    public const DEFAULT_ADMIN_PASSWORD = '12345678';

    /**
     * Seeders already recorded as successful in seeder_log.
     */
    protected array $runSeeders = [];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->loadRunSeeders();

        $startTime = microtime(true);

        $seededCount = 0;
        $failedCount = 0;
        $skippedCount = 0;

        $this->command->info('');
        $this->command->info('╔═══════════════════════════════════════════════════════════════════════════════╗');
        $this->command->info('║                    🚀 VITEMART DATABASE SEEDING PROCESS                       ║');
        $this->command->info('║                    Starting at: ' . now()->format('Y-m-d H:i:s') . '                           ║');
        $this->command->info('╚═══════════════════════════════════════════════════════════════════════════════╝');
        $this->command->info('');

        // ============================================================
        // PART 1: CORE PERMISSIONS, ROLES & DEFAULT ADMIN
        // ============================================================

        $this->printSection('🔐 PART 1: CORE PERMISSIONS, ROLES & DEFAULT ADMIN');

        $this->runSeederList([
            'PermissionTableSeeder' => '🔐 Seeding user, role, permission & dashboard permissions...',
            'UserTableSeeder'       => '👤 Seeding default admin user and Super Admin / Admin roles...',
        ], $seededCount, $failedCount, $skippedCount);

        // ============================================================
        // PART 2: CATALOGUE PERMISSIONS
        // ============================================================

        $this->printSection('🛍️ PART 2: CATALOGUE PERMISSIONS');

        $this->runSeederList([
            'BrandPermissionTableSeeder'         => '🏷️ Seeding brand permissions...',
            'CategoryPermissionTableSeeder'      => '🗂️ Seeding category permissions...',
            'BannerPermissionTableSeeder'        => '🖼️ Seeding banner permissions...',
            'ProductPermissionTableSeeder'       => '📦 Seeding product permissions...',
            'ProductReviewPermissionTableSeeder' => '⭐ Seeding product review permissions...',
        ], $seededCount, $failedCount, $skippedCount);

        // ============================================================
        // PART 3: SALES, POS & CUSTOMER PERMISSIONS
        // ============================================================

        $this->printSection('🧾 PART 3: SALES, POS & CUSTOMER PERMISSIONS');

        $this->runSeederList([
            'PosPermissionTableSeeder'         => '🖥️ Seeding POS permissions...',
            'OrderPermissionTableSeeder'       => '🧾 Seeding order permissions...',
            'SalesPermissionTableSeeder'       => '💰 Seeding sales permissions...',
            'SalesPersonPermissionTableSeeder' => '🧑‍💼 Seeding sales person permissions...',
            'CustomerPermissionTableSeeder'    => '👥 Seeding customer permissions...',
        ], $seededCount, $failedCount, $skippedCount);

        // ============================================================
        // PART 4: INVENTORY & STORE SETTINGS PERMISSIONS
        // ============================================================

        $this->printSection('📦 PART 4: INVENTORY & STORE SETTINGS PERMISSIONS');

        $this->runSeederList([
            'InventoryPermissionTableSeeder' => '📊 Seeding inventory permissions...',
            'StoreSettingPermissionSeeder'   => '⚙️ Seeding store setting permissions...',
        ], $seededCount, $failedCount, $skippedCount);

        // ============================================================
        // PART 5: REFERENCE DATA
        // NOTE: UnitsTableSeeder TRUNCATES the units table, so it is
        // tracked and only runs once. Never pass $force = true here.
        // ============================================================

        $this->printSection('📚 PART 5: REFERENCE DATA');

        // Safety: on an existing install (units already filled, but no
        // seeder_log entry yet) mark it as run instead of wiping units.
        $this->markAsRunIfTableHasData('UnitsTableSeeder', 'units');

        $this->runSeederList([
            'UnitsTableSeeder' => '📏 Seeding units of measure (pc, kg, L, carton...)...',
        ], $seededCount, $failedCount, $skippedCount);

        // ============================================================
        // PART 6: ALWAYS RE-RUN SEEDERS
        // Put new permission seeders here ONLY if they use
        // firstOrCreate / updateOrCreate, so new permissions reach
        // existing installs on every `php artisan db:seed`.
        // ============================================================

        $alwaysRunSeeders = [
            // 'ExamplePermissionSeeder' => '🆕 Seeding example permissions...',
        ];

        if (count($alwaysRunSeeders) > 0) {
            $this->printSection('🆕 PART 6: MODULE PERMISSIONS (always re-run)');

            $this->runSeederList($alwaysRunSeeders, $seededCount, $failedCount, $skippedCount, true);
        }

        // Super Admin always holds every permission, including ones added above.
        $this->syncSuperAdminPermissions();

        // ============================================================
        // PART 7: DEMO / TEST DATA
        // ============================================================

        $demoSeeders = $this->getDemoSeeders();

        if (app()->environment('local', 'development')) {

            $this->printSection('🧪 PART 7: DEMO & TEST DATA (Development Environment)');
            $this->command->warn('⚠️  Running in DEVELOPMENT mode - seeding demo data...');
            $this->command->info('');

            $this->runSeederList($demoSeeders, $seededCount, $failedCount, $skippedCount);

        } else {

            $this->printSection('🚀 PART 7: PRODUCTION ENVIRONMENT');
            $this->command->info('Skipping demo data - only seeding essential data');
            $this->command->info('');

            $skippedCount += count($demoSeeders);
        }

        // ============================================================
        // DATABASE STATISTICS
        // ============================================================

        $this->printSection('📊 DATABASE STATISTICS');
        $this->showDatabaseStats();

        // ============================================================
        // COMPLETION SUMMARY
        // ============================================================

        $executionTime = round(microtime(true) - $startTime, 2);

        $this->command->info('');
        $this->command->info('╔═══════════════════════════════════════════════════════════════════════════════╗');
        $this->command->info('║                         ✅ SEEDING COMPLETED                                 ║');
        $this->command->info('╠═══════════════════════════════════════════════════════════════════════════════╣');
        $this->command->info('║  📊 Total Seeders Executed: ' . str_pad($seededCount, 45) . '║');
        $this->command->info('║  ⏭️  Seeders Skipped: ' . str_pad($skippedCount, 49) . '║');
        $this->command->info('║  ❌ Failed Seeders: ' . str_pad($failedCount, 49) . '║');
        $this->command->info('║  ⏱️  Execution Time: ' . str_pad($executionTime . ' seconds', 45) . '║');
        $this->command->info('║  🕐 Completed at: ' . str_pad(now()->format('Y-m-d H:i:s'), 45) . '║');
        $this->command->info('╚═══════════════════════════════════════════════════════════════════════════════╝');
        $this->command->info('');

        if ($failedCount > 0) {
            $this->command->warn('⚠️  Some seeders failed. Please check the errors above (also logged in storage/logs and the seeder_log table).');
            $this->command->warn('💡 Tip: fix the seeder and run "php artisan db:seed" again - successful ones will be skipped.');
        } else {
            $this->command->info('🎉 Database seeding completed successfully!');
            $this->command->info('💡 You can now run "php artisan serve" to start the application.');
        }

        $this->command->info('');
        $this->command->info('🔑 Default Super Admin login');
        $this->command->info('   Username / Email : ' . self::DEFAULT_ADMIN_LOGIN);
        $this->command->info('   Password         : ' . self::DEFAULT_ADMIN_PASSWORD);
        $this->command->warn('   ⚠️  Change this password after the first login on a live server.');
        $this->command->info('');
    }

    // ================================================================
    // HELPER: PRINT SECTION
    // ================================================================

    protected function printSection(string $title): void
    {
        $this->command->info('┌─────────────────────────────────────────────────────────────────────────────┐');
        $this->command->info('│ ' . str_pad($title, 75) . '│');
        $this->command->info('└─────────────────────────────────────────────────────────────────────────────┘');
        $this->command->info('');
    }

    // ================================================================
    // HELPER: RUN A LIST OF SEEDERS
    // ================================================================

    protected function runSeederList(
        array $seeders,
        int &$seededCount,
        int &$failedCount,
        int &$skippedCount,
        bool $force = false
    ): void {
        foreach ($seeders as $seeder => $message) {
            $result = $this->safeCall($seeder, $seeder, $message, $force);

            $this->updateStats($result, $seededCount, $failedCount, $skippedCount);
        }

        $this->command->info('');
    }

    // ================================================================
    // SUPER ADMIN GETS EVERY PERMISSION
    // ================================================================

    protected function syncSuperAdminPermissions(): void
    {
        try {
            $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();

            if ($superAdmin) {
                $permissionIds = Permission::where('guard_name', 'web')->pluck('id')->all();

                $superAdmin->syncPermissions($permissionIds);

                $this->command->info('  ✅ Super Admin now has all ' . count($permissionIds) . ' permissions');
            } else {
                $this->command->warn('  ⚠️  Super Admin role not found - permissions not synced');
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            $this->command->warn('  ⚠️  Could not sync Super Admin permissions: ' . $e->getMessage());
        }

        $this->command->info('');
    }

    // ================================================================
    // PROTECT DESTRUCTIVE SEEDERS ON EXISTING DATABASES
    // ================================================================

    protected function markAsRunIfTableHasData(string $seeder, string $table): void
    {
        try {
            if (!$this->hasBeenRun($seeder) && Schema::hasTable($table) && DB::table($table)->exists()) {
                $this->logSeederRun($seeder, true, "Skipped: {$table} already had data");
            }
        } catch (\Throwable $e) {
            Log::warning("Could not check {$table} before {$seeder}: " . $e->getMessage());
        }
    }

    // ================================================================
    // LOAD PREVIOUSLY RUN SEEDERS
    // ================================================================

    protected function loadRunSeeders(): void
    {
        $this->runSeeders = [];

        if (!Schema::hasTable('seeder_log')) {
            $this->createSeederLogTable();

            return;
        }

        try {
            $this->runSeeders = DB::table('seeder_log')
                ->whereNotNull('completed_at')
                ->where('success', true)
                ->pluck('seeder_name')
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning('Could not load seeder log: ' . $e->getMessage());
        }
    }

    // ================================================================
    // CREATE SEEDER LOG TABLE
    // ================================================================

    protected function createSeederLogTable(): void
    {
        try {
            Schema::create('seeder_log', function ($table) {
                $table->id();
                $table->string('seeder_name')->unique();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->boolean('success')->default(false);
                $table->text('error_message')->nullable();
                $table->timestamps();
            });
        } catch (\Throwable $e) {
            Log::warning('Could not create seeder_log table: ' . $e->getMessage());
        }
    }

    // ================================================================
    // NORMALIZE SEEDER CLASS
    // ================================================================

    protected function resolveSeederClass($seeder): string
    {
        // Already fully qualified, e.g. Database\Seeders\PermissionTableSeeder
        if (is_string($seeder) && str_contains($seeder, '\\')) {
            return $seeder;
        }

        // PosPermissionTableSeeder -> Database\Seeders\PosPermissionTableSeeder
        return __NAMESPACE__ . '\\' . $seeder;
    }

    // ================================================================
    // CHECK IF SEEDER ALREADY RAN
    // ================================================================

    protected function hasBeenRun(string $seederName): bool
    {
        $seederClass = $this->resolveSeederClass($seederName);

        if (in_array($seederClass, $this->runSeeders, true)) {
            return true;
        }

        // Backward compatibility: old logs may hold the short class name.
        return in_array(class_basename($seederClass), $this->runSeeders, true);
    }

    // ================================================================
    // LOG SEEDER EXECUTION
    // ================================================================

    protected function logSeederRun(string $seederName, bool $success, ?string $error = null): void
    {
        try {
            $seederClass = $this->resolveSeederClass($seederName);

            DB::table('seeder_log')->updateOrInsert(
                ['seeder_name' => $seederClass],
                [
                    'seeder_name'   => $seederClass,
                    'started_at'    => now(),
                    'completed_at'  => $success ? now() : null,
                    'success'       => $success,
                    'error_message' => $error,
                    'updated_at'    => now(),
                ]
            );

            if ($success && !in_array($seederClass, $this->runSeeders, true)) {
                $this->runSeeders[] = $seederClass;
            }
        } catch (\Throwable $e) {
            // Logging should NEVER break database seeding.
            Log::warning('Could not log seeder run', [
                'seeder' => $seederName,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    // ================================================================
    // UPDATE STATISTICS
    // ================================================================

    protected function updateStats(array $result, int &$seededCount, int &$failedCount, int &$skippedCount): void
    {
        if (isset($result['skipped']) && $result['skipped'] === true) {
            $skippedCount++;
        } elseif (isset($result['success']) && $result['success'] === true) {
            $seededCount++;
        } else {
            $failedCount++;
        }
    }

    // ================================================================
    // PERMISSION HELPERS
    // ================================================================

    protected function permissionExists(string $permissionName, string $guardName = 'web'): bool
    {
        return Permission::where('name', $permissionName)
            ->where('guard_name', $guardName)
            ->exists();
    }

    protected function createPermissionIfNotExists(
        string $permissionName,
        string $guardName = 'web',
        ?string $title = null
    ): bool {
        if ($this->permissionExists($permissionName, $guardName)) {
            return false;
        }

        $data = [
            'name'       => $permissionName,
            'guard_name' => $guardName,
        ];

        if ($title !== null) {
            $data['title'] = $title;
        }

        Permission::create($data);

        return true;
    }

    // ================================================================
    // SAFE SEEDER CALL
    // ================================================================

    protected function safeCall($seeder, $name, $message = null, bool $force = false): array
    {
        if ($message) {
            $this->command->getOutput()->write($message);
        }

        $seederClass = $this->resolveSeederClass($seeder);

        // Seeder file missing -> skip instead of crashing.
        if (!class_exists($seederClass)) {
            if ($message) {
                $this->command->getOutput()->write("\r\033[K");
            }

            $this->command->warn("  ⚠️  Seeder not found: {$name} - skipping");

            return ['success' => false, 'skipped' => true];
        }

        // Already ran successfully -> skip.
        if (!$force && $this->hasBeenRun($seederClass)) {
            if ($message) {
                $this->command->getOutput()->write("\r\033[K");
            }

            $this->command->info("  ⏭️  {$name} already run - skipping");

            return ['success' => true, 'skipped' => true];
        }

        try {
            $this->call($seederClass);

            $this->logSeederRun($seederClass, true);

            if ($message) {
                $this->command->getOutput()->write("\r\033[K");
                $this->command->info("  ✅ {$name} completed successfully!");
            }

            return ['success' => true, 'skipped' => false];

        } catch (\Throwable $e) {

            $errorMessage = $e->getMessage();

            $isDuplicateError =
                str_contains($errorMessage, 'PermissionAlreadyExists')
                || str_contains($errorMessage, 'RoleAlreadyExists')
                || str_contains(strtolower($errorMessage), 'already exists')
                || str_contains(strtolower($errorMessage), 'duplicate entry');

            if ($isDuplicateError) {
                if ($message) {
                    $this->command->getOutput()->write("\r\033[K");
                    $this->command->warn("  ⚠️  {$name} - some records already exist, continuing");
                }

                // Treat duplicate data as handled.
                $this->logSeederRun($seederClass, true, 'Some permissions or records already existed');

                return ['success' => true, 'skipped' => false];
            }

            // Real failure.
            if ($message) {
                $this->command->getOutput()->write("\r\033[K");
                $this->command->error("  ❌ {$name} failed: " . $errorMessage);
            }

            Log::error("Seeder failed: {$seederClass}", [
                'seeder' => $seederClass,
                'error'  => $errorMessage,
                'file'   => $e->getFile(),
                'line'   => $e->getLine(),
            ]);

            $this->logSeederRun($seederClass, false, $errorMessage);

            return ['success' => false, 'skipped' => false];
        }
    }

    // ================================================================
    // DATABASE STATISTICS
    // ================================================================

    protected function showDatabaseStats(): void
    {
        $tables = [
            'users'          => '👤 Users',
            'roles'          => '👥 Roles',
            'permissions'    => '🔐 Permissions',
            'branches'       => '🏬 Branches',
            'customers'      => '🧑‍🤝‍🧑 Customers',
            'categories'     => '🗂️ Categories',
            'brands'         => '🏷️ Brands',
            'units'          => '📏 Units',
            'products'       => '📦 Products',
            'stocks'         => '📊 Stock Records',
            'inventory_logs' => '📝 Inventory Logs',
            'orders'         => '🧾 Orders',
            'order_items'    => '🛒 Order Items',
            'order_payments' => '💳 Order Payments',
            'pos_shifts'     => '🕐 POS Shifts',
        ];

        $stats = [];

        foreach ($tables as $table => $label) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            try {
                $count = DB::table($table)->count();

                if ($count > 0) {
                    $stats[] = "  {$label}: " . number_format($count);
                }
            } catch (\Throwable $e) {
                // Ignore inaccessible tables.
            }
        }

        $this->command->info(count($stats) > 0 ? implode("\n", $stats) : '  ℹ️  No data found in tables yet.');
    }

    // ================================================================
    // DEMO SEEDERS (local / development only)
    // ================================================================

    protected function getDemoSeeders(): array
    {
        return [
            'CustomerSeeder' => '🧑‍🤝‍🧑 Seeding sample customers...',
        ];
    }
}
