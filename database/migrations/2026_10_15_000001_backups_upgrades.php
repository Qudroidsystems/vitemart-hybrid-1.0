<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-branch phase 8 – running it for real.
 *  backup_runs   every backup (scheduled, manual, before an upgrade / restore) and whether it worked
 *  upgrade_runs  every `vitemart:upgrade`, with its log and whether it was rolled back
 *  stock_transfer_lines.damaged_qty   goods that arrived damaged (go to quarantine, not the shelf)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('backup_runs')) {
            Schema::create('backup_runs', function (Blueprint $t) {
                $t->id();
                $t->string('kind', 20)->default('scheduled'); // scheduled | manual | pre-upgrade | pre-restore
                $t->string('status', 10)->default('running'); // running | ok | failed
                $t->string('path')->nullable();
                $t->unsignedBigInteger('size_bytes')->nullable();
                $t->string('app_version', 20)->nullable();
                $t->text('error')->nullable();
                $t->timestamp('started_at')->nullable();
                $t->timestamp('finished_at')->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('upgrade_runs')) {
            Schema::create('upgrade_runs', function (Blueprint $t) {
                $t->id();
                $t->string('from_version', 20)->nullable();
                $t->string('to_version', 20)->nullable();
                $t->string('status', 12)->default('running'); // running | ok | rolled_back | failed
                $t->unsignedBigInteger('backup_run_id')->nullable();
                $t->longText('log')->nullable();
                $t->timestamp('started_at')->nullable();
                $t->timestamp('finished_at')->nullable();
                $t->timestamps();
            });
        }
        if (Schema::hasTable('stock_transfer_lines') && !Schema::hasColumn('stock_transfer_lines', 'damaged_qty')) {
            Schema::table('stock_transfer_lines', fn (Blueprint $t) => $t->decimal('damaged_qty', 15, 3)->default(0)->after('received_qty'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
        Schema::dropIfExists('upgrade_runs');
        if (Schema::hasColumn('stock_transfer_lines', 'damaged_qty')) {
            Schema::table('stock_transfer_lines', fn (Blueprint $t) => $t->dropColumn('damaged_qty'));
        }
    }
};
