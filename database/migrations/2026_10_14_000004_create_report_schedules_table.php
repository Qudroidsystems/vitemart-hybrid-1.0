<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduled sales-report emails: who gets them, how often, and what period they cover.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('report_schedules')) {
            Schema::create('report_schedules', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->json('recipients');
                $t->string('frequency', 16)->default('daily');      // daily | weekly | monthly | hourly
                $t->unsignedTinyInteger('every_hours')->nullable();  // hourly: every N hours
                $t->string('send_time', 5)->default('22:00');        // HH:MM
                $t->unsignedTinyInteger('weekday')->nullable();      // weekly: 1 = Monday … 7 = Sunday
                $t->unsignedTinyInteger('month_day')->nullable();    // monthly: 1–28, 31 = last day
                $t->string('period', 24)->default('today');
                $t->string('channels', 8)->default('pos');           // pos | all
                $t->json('branch_ids')->nullable();
                $t->boolean('include_profit')->default(false);
                $t->boolean('include_shifts')->default(true);
                $t->boolean('include_products')->default(true);
                $t->boolean('include_transactions')->default(true);  // list in the email (when short) + spreadsheet
                $t->boolean('skip_if_empty')->default(false);
                $t->boolean('is_active')->default(true);
                $t->timestamp('next_run_at')->nullable()->index();
                $t->timestamp('retry_for')->nullable();             // a failed run being retried: the time it was due
                $t->timestamp('last_sent_at')->nullable();
                $t->string('last_status', 16)->nullable();           // sent | skipped | failed
                $t->text('last_message')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
            });
        }

        if (Schema::hasTable('permissions')) {
            $id = DB::table('permissions')->where('name', 'Manage report emails')->where('guard_name', 'web')->value('id');
            if (!$id) {
                $row = ['name' => 'Manage report emails', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()];
                if (Schema::hasColumn('permissions', 'title')) {
                    $row['title'] = 'Reports';
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
        Schema::dropIfExists('report_schedules');
        if (Schema::hasTable('permissions')) {
            $id = DB::table('permissions')->where('name', 'Manage report emails')->value('id');
            if ($id) {
                DB::table('role_has_permissions')->where('permission_id', $id)->delete();
                DB::table('permissions')->where('id', $id)->delete();
            }
        }
    }
};
