<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Supermarket upgrade – products, variations and packaging (pack/carton) pricing.
 *
 * Every change is guarded so the migration is safe to run on a database that
 * has drifted from the migration history.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------
        // products
        // ---------------------------------------------------------------
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'wholesale_price')) {
                $table->decimal('wholesale_price', 12, 2)->nullable()->after('sale_price');
            }
            if (!Schema::hasColumn('products', 'promo_price')) {
                $table->decimal('promo_price', 12, 2)->nullable()->after('wholesale_price');
            }
            if (!Schema::hasColumn('products', 'promo_starts_at')) {
                $table->dateTime('promo_starts_at')->nullable()->after('promo_price');
            }
            if (!Schema::hasColumn('products', 'promo_ends_at')) {
                $table->dateTime('promo_ends_at')->nullable()->after('promo_starts_at');
            }
            if (!Schema::hasColumn('products', 'reorder_level')) {
                $table->decimal('reorder_level', 12, 3)->default(10)->after('stock');
            }
            if (!Schema::hasColumn('products', 'track_stock')) {
                $table->boolean('track_stock')->default(true)->after('reorder_level');
            }
            if (!Schema::hasColumn('products', 'is_weighted')) {
                // Sold by weight (kg) from a scale – quantity can be fractional.
                $table->boolean('is_weighted')->default(false)->after('track_stock');
            }
            if (!Schema::hasColumn('products', 'plu_code')) {
                // 5-digit code printed into scale (price/weight-embedded) barcodes.
                $table->string('plu_code', 10)->nullable()->after('barcode');
            }
            if (!Schema::hasColumn('products', 'allow_price_override')) {
                $table->boolean('allow_price_override')->default(false)->after('is_weighted');
            }
        });

        // Stock quantities become decimal so weighted goods (e.g. 1.250kg) work.
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('stock', 14, 3)->default(0)->change();
            $table->decimal('price', 12, 2)->change();
            $table->decimal('sale_price', 12, 2)->nullable()->change();
        });

        if (Schema::hasColumn('products', 'plu_code')) {
            try {
                Schema::table('products', fn (Blueprint $t) => $t->index('plu_code'));
            } catch (\Throwable $e) {
            }
        }

        // ---------------------------------------------------------------
        // product_variations – own stock, status, wholesale price
        // ---------------------------------------------------------------
        Schema::table('product_variations', function (Blueprint $table) {
            if (!Schema::hasColumn('product_variations', 'name')) {
                $table->string('name')->nullable()->after('product_id');
            }
            if (!Schema::hasColumn('product_variations', 'stock')) {
                // Cached balance, kept in sync by StockService from the stocks ledger.
                $table->decimal('stock', 14, 3)->default(0)->after('sale_price');
            }
            if (!Schema::hasColumn('product_variations', 'wholesale_price')) {
                $table->decimal('wholesale_price', 12, 2)->nullable()->after('sale_price');
            }
            if (!Schema::hasColumn('product_variations', 'reorder_level')) {
                $table->decimal('reorder_level', 12, 3)->nullable()->after('stock');
            }
            if (!Schema::hasColumn('product_variations', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('reorder_level');
            }
        });

        Schema::table('product_variations', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->nullable()->change();
            $table->decimal('sale_price', 12, 2)->nullable()->change();
        });

        // ---------------------------------------------------------------
        // product_unit – packaging: each pack has its own price + barcode
        // ---------------------------------------------------------------
        Schema::table('product_unit', function (Blueprint $table) {
            if (!Schema::hasColumn('product_unit', 'price')) {
                $table->decimal('price', 12, 2)->nullable()->after('quantity_per_unit');
            }
            if (!Schema::hasColumn('product_unit', 'wholesale_price')) {
                $table->decimal('wholesale_price', 12, 2)->nullable()->after('price');
            }
            if (!Schema::hasColumn('product_unit', 'cost_price')) {
                $table->decimal('cost_price', 12, 2)->nullable()->after('wholesale_price');
            }
            if (!Schema::hasColumn('product_unit', 'barcode')) {
                $table->string('barcode', 64)->nullable()->after('cost_price');
            }
            if (!Schema::hasColumn('product_unit', 'is_base')) {
                $table->boolean('is_base')->default(false)->after('barcode');
            }
            if (!Schema::hasColumn('product_unit', 'is_sellable')) {
                $table->boolean('is_sellable')->default(true)->after('is_base');
            }
            if (!Schema::hasColumn('product_unit', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('is_sellable');
            }
        });

        Schema::table('product_unit', function (Blueprint $table) {
            $table->decimal('quantity_per_unit', 12, 3)->default(1)->change();
        });

        try {
            Schema::table('product_unit', fn (Blueprint $t) => $t->index('barcode'));
        } catch (\Throwable $e) {
        }

        // Mark the first attached unit of every product as its base unit.
        DB::statement('
            UPDATE product_unit pu
            JOIN (SELECT MIN(id) AS id FROM product_unit GROUP BY product_id) f ON f.id = pu.id
            SET pu.is_base = 1
        ');

        // ---------------------------------------------------------------
        // stocks ledger – decimal quantities
        // ---------------------------------------------------------------
        Schema::table('stocks', function (Blueprint $table) {
            $table->decimal('quantity', 14, 3)->default(0)->change();
            $table->decimal('previous_quantity', 14, 3)->nullable()->change();
            $table->decimal('new_quantity', 14, 3)->nullable()->change();
        });

        // reference_number was UNIQUE, but one sale / adjustment writes one ledger row
        // per line item with the same reference – so multi-item sales failed.
        $uniques = collect(DB::select("SHOW INDEX FROM stocks WHERE Column_name = 'reference_number' AND Non_unique = 0"))
            ->pluck('Key_name')->unique();
        foreach ($uniques as $indexName) {
            DB::statement("ALTER TABLE stocks DROP INDEX `{$indexName}`");
        }
        $hasPlain = collect(DB::select("SHOW INDEX FROM stocks WHERE Column_name = 'reference_number'"))->isNotEmpty();
        if (!$hasPlain) {
            Schema::table('stocks', fn (Blueprint $t) => $t->index('reference_number'));
        }
        try {
            Schema::table('stocks', fn (Blueprint $t) => $t->index(['product_variation_id', 'stock_location_id'], 'stocks_variation_location_idx'));
        } catch (\Throwable $e) {
        }

        if (Schema::hasTable('stock_movements')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->decimal('quantity', 14, 3)->default(0)->change();
                $table->decimal('balance', 14, 3)->default(0)->change();
            });
        }

        // ---------------------------------------------------------------
        // order_items – snapshot cost, pack info and discounts
        // ---------------------------------------------------------------
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'product_unit_id')) {
                $table->unsignedBigInteger('product_unit_id')->nullable()->after('unit_id');
            }
            if (!Schema::hasColumn('order_items', 'quantity_per_unit')) {
                $table->decimal('quantity_per_unit', 12, 3)->default(1)->after('product_unit_id');
            }
            if (!Schema::hasColumn('order_items', 'base_quantity')) {
                // Quantity converted to the product's base unit (what left the shelf).
                $table->decimal('base_quantity', 14, 3)->nullable()->after('quantity_per_unit');
            }
            if (!Schema::hasColumn('order_items', 'cost_price')) {
                $table->decimal('cost_price', 12, 2)->nullable()->after('unit_price');
            }
            if (!Schema::hasColumn('order_items', 'discount_amount')) {
                $table->decimal('discount_amount', 12, 2)->default(0)->after('total_price');
            }
            if (!Schema::hasColumn('order_items', 'sku')) {
                $table->string('sku')->nullable()->after('title');
            }
            if (!Schema::hasColumn('order_items', 'barcode')) {
                $table->string('barcode')->nullable()->after('sku');
            }
            if (!Schema::hasColumn('order_items', 'returned_quantity')) {
                $table->decimal('returned_quantity', 14, 3)->default(0)->after('discount_amount');
            }
            if (!Schema::hasColumn('order_items', 'price_overridden')) {
                $table->boolean('price_overridden')->default(false)->after('returned_quantity');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('quantity', 14, 3)->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            foreach (['product_unit_id', 'quantity_per_unit', 'base_quantity', 'cost_price', 'discount_amount', 'sku', 'barcode', 'returned_quantity', 'price_overridden'] as $c) {
                if (Schema::hasColumn('order_items', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
        Schema::table('product_unit', function (Blueprint $table) {
            foreach (['price', 'wholesale_price', 'cost_price', 'barcode', 'is_base', 'is_sellable', 'sort_order'] as $c) {
                if (Schema::hasColumn('product_unit', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
        Schema::table('product_variations', function (Blueprint $table) {
            foreach (['name', 'stock', 'wholesale_price', 'reorder_level', 'is_active'] as $c) {
                if (Schema::hasColumn('product_variations', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
        Schema::table('products', function (Blueprint $table) {
            foreach (['wholesale_price', 'promo_price', 'promo_starts_at', 'promo_ends_at', 'reorder_level', 'track_stock', 'is_weighted', 'plu_code', 'allow_price_override'] as $c) {
                if (Schema::hasColumn('products', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
