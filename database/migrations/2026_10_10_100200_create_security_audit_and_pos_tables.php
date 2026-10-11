<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------
        // Generic key/value system settings (login control, POS rules…)
        // ---------------------------------------------------------------
        if (!Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        // ---------------------------------------------------------------
        // Per-role login windows
        // ---------------------------------------------------------------
        if (!Schema::hasTable('role_login_rules')) {
            Schema::create('role_login_rules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('role_id')->unique();
                $table->boolean('enabled')->default(true);
                $table->string('start_time', 5)->nullable();   // HH:MM
                $table->string('end_time', 5)->nullable();     // HH:MM
                $table->json('days')->nullable();              // [1..7] ISO days
                $table->timestamps();
            });
        }

        // ---------------------------------------------------------------
        // users – account status, per-user login window, tracking
        // ---------------------------------------------------------------
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (!Schema::hasColumn('users', 'login_rule')) {
                // inherit | custom | always | blocked
                $table->string('login_rule', 20)->default('inherit');
            }
            if (!Schema::hasColumn('users', 'login_start_time')) {
                $table->string('login_start_time', 5)->nullable();
            }
            if (!Schema::hasColumn('users', 'login_end_time')) {
                $table->string('login_end_time', 5)->nullable();
            }
            if (!Schema::hasColumn('users', 'login_days')) {
                $table->json('login_days')->nullable();
            }
            if (!Schema::hasColumn('users', 'force_logout_at')) {
                $table->timestamp('force_logout_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'last_login_ip')) {
                $table->string('last_login_ip', 45)->nullable();
            }
            if (!Schema::hasColumn('users', 'pos_pin')) {
                // Hashed 4–6 digit PIN used to authorise supervisor overrides at the till.
                $table->string('pos_pin')->nullable();
            }
            if (!Schema::hasColumn('users', 'max_discount_percent')) {
                $table->decimal('max_discount_percent', 5, 2)->nullable();
            }
        });

        // ---------------------------------------------------------------
        // Audit trail
        // ---------------------------------------------------------------
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('user_name')->nullable();          // snapshot
                $table->string('event', 40);                      // created, updated, deleted, login, logout, login_failed, ...
                $table->string('module', 40)->nullable();         // products, pos, inventory, security, ...
                $table->string('auditable_type')->nullable();
                $table->string('auditable_id', 64)->nullable();
                $table->string('auditable_label')->nullable();    // e.g. product title at time of change
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('description', 500)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->string('url', 500)->nullable();
                $table->string('method', 10)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['auditable_type', 'auditable_id']);
                $table->index(['module', 'created_at']);
                $table->index(['event', 'created_at']);
                $table->index('created_at');
            });
        }

        // ---------------------------------------------------------------
        // POS: shifts / cash drawer, held sales, split payments
        // ---------------------------------------------------------------
        if (!Schema::hasTable('pos_shifts')) {
            Schema::create('pos_shifts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained();
                $table->string('register', 50)->nullable();
                $table->timestamp('opened_at');
                $table->decimal('opening_float', 12, 2)->default(0);
                $table->timestamp('closed_at')->nullable();
                $table->decimal('expected_cash', 12, 2)->nullable();
                $table->decimal('counted_cash', 12, 2)->nullable();
                $table->decimal('variance', 12, 2)->nullable();
                $table->decimal('cash_in', 12, 2)->default(0);
                $table->decimal('cash_out', 12, 2)->default(0);
                $table->json('summary')->nullable();
                $table->string('status', 20)->default('open');
                $table->text('notes')->nullable();
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['user_id', 'status']);
            });
        }

        if (!Schema::hasTable('pos_cash_movements')) {
            Schema::create('pos_cash_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pos_shift_id')->constrained()->cascadeOnDelete();
                $table->string('type', 10); // in, out
                $table->decimal('amount', 12, 2);
                $table->string('reason')->nullable();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('pos_held_sales')) {
            Schema::create('pos_held_sales', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained();
                $table->string('label')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->json('cart');
                $table->decimal('total', 12, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('order_payments')) {
            Schema::create('order_payments', function (Blueprint $table) {
                $table->id();
                $table->string('order_id');
                $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
                $table->string('method', 30);           // cash, card, transfer, pos, credit, points
                $table->decimal('amount', 12, 2);
                $table->string('reference')->nullable();
                $table->unsignedBigInteger('pos_shift_id')->nullable()->index();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();
                $table->index(['method', 'created_at']);
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            $add = [
                'subtotal' => fn () => $table->decimal('subtotal', 12, 2)->nullable()->after('total_amount'),
                'discount_amount' => fn () => $table->decimal('discount_amount', 12, 2)->default(0)->after('total_amount'),
                'discount_type' => fn () => $table->string('discount_type', 20)->nullable()->after('total_amount'),
                'discount_value' => fn () => $table->decimal('discount_value', 12, 2)->nullable()->after('total_amount'),
                'amount_paid' => fn () => $table->decimal('amount_paid', 12, 2)->nullable()->after('total_amount'),
                'change_due' => fn () => $table->decimal('change_due', 12, 2)->nullable()->after('total_amount'),
                'cost_total' => fn () => $table->decimal('cost_total', 12, 2)->nullable()->after('total_amount'),
                'pos_shift_id' => fn () => $table->unsignedBigInteger('pos_shift_id')->nullable()->index(),
                'channel' => fn () => $table->string('channel', 20)->default('online'),
                'notes' => fn () => $table->text('notes')->nullable(),
                'voided_at' => fn () => $table->timestamp('voided_at')->nullable(),
                'voided_by' => fn () => $table->unsignedBigInteger('voided_by')->nullable(),
                'void_reason' => fn () => $table->string('void_reason')->nullable(),
                'paid_at' => fn () => $table->timestamp('paid_at')->nullable(),
            ];
            foreach ($add as $col => $fn) {
                if (!Schema::hasColumn('orders', $col)) {
                    $fn();
                }
            }
        });

        try {
            Schema::table('orders', fn (Blueprint $t) => $t->index(['order_date', 'status']));
        } catch (\Throwable $e) {
        }

        // POS orders created before this upgrade
        \Illuminate\Support\Facades\DB::table('orders')->where('id', 'like', 'POS-%')->update(['channel' => 'pos']);
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payments');
        Schema::dropIfExists('pos_held_sales');
        Schema::dropIfExists('pos_cash_movements');
        Schema::dropIfExists('pos_shifts');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('role_login_rules');
        Schema::dropIfExists('system_settings');
    }
};
