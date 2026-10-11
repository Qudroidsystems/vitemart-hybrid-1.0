<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every price change (single edit, bulk change, promo, revert) is recorded here.
        if (!Schema::hasTable('price_histories')) {
            Schema::create('price_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('product_variation_id')->nullable()->index();
                $table->unsignedBigInteger('product_unit_id')->nullable()->index();
                $table->string('field', 40);              // price, cost_price, sale_price, wholesale_price, promo_price, pack_price
                $table->decimal('old_value', 12, 2)->nullable();
                $table->decimal('new_value', 12, 2)->nullable();
                $table->string('batch_id', 40)->nullable()->index(); // groups a bulk change so it can be reverted
                $table->string('source', 30)->default('manual');      // manual, bulk, import, revert, promo
                $table->string('reason')->nullable();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();
                $table->index(['product_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('price_change_batches')) {
            Schema::create('price_change_batches', function (Blueprint $table) {
                $table->id();
                $table->string('batch_id', 40)->unique();
                $table->string('description');
                $table->json('criteria')->nullable();
                $table->unsignedInteger('items_count')->default(0);
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamp('reverted_at')->nullable();
                $table->foreignId('reverted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // Stock adjustments / stock counts / goods received – need confirmation before posting.
        if (!Schema::hasTable('stock_adjustments')) {
            Schema::create('stock_adjustments', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 40)->unique();
                $table->string('type', 20)->default('adjustment'); // adjustment, count, damage, receive, expired
                $table->string('status', 20)->default('pending');   // draft, pending, approved, rejected, cancelled
                $table->foreignId('stock_location_id')->constrained('stock_locations');
                $table->string('reason')->nullable();
                $table->text('notes')->nullable();
                $table->string('supplier')->nullable();
                $table->string('supplier_invoice')->nullable();
                $table->decimal('total_value_change', 14, 2)->default(0);
                $table->unsignedInteger('items_count')->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('rejected_at')->nullable();
                $table->string('rejection_reason')->nullable();
                $table->timestamps();
                $table->index(['status', 'created_at']);
            });
        }

        if (!Schema::hasTable('stock_adjustment_items')) {
            Schema::create('stock_adjustment_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('product_variation_id')->nullable()->index();
                $table->unsignedBigInteger('product_unit_id')->nullable();   // pack used when entering qty
                $table->decimal('quantity_per_unit', 12, 3)->default(1);
                $table->string('mode', 10)->default('add');                   // add, remove, set
                $table->decimal('entered_quantity', 14, 3)->default(0);      // as typed (in pack units)
                $table->decimal('system_quantity', 14, 3)->default(0);       // balance when created
                $table->decimal('quantity_change', 14, 3)->default(0);       // signed, base units
                $table->decimal('balance_after', 14, 3)->nullable();         // filled at approval
                $table->decimal('unit_cost', 12, 2)->nullable();
                $table->string('reason')->nullable();
                $table->date('expiry_date')->nullable();
                $table->string('batch_number')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('price_change_batches');
        Schema::dropIfExists('price_histories');
    }
};
