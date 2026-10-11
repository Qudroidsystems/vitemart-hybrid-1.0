<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The till reads each product's stock at its own location in one grouped query.
 * This index keeps that instant even with millions of stock movements.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('stocks')) {
            return;
        }
        foreach (Schema::getIndexes('stocks') as $ix) {
            if ($ix['name'] === 'stocks_location_product_idx'
                || array_slice($ix['columns'], 0, 2) === ['stock_location_id', 'product_id']) {
                return;
            }
        }
        Schema::table('stocks', fn ($t) => $t->index(['stock_location_id', 'product_id', 'product_variation_id'], 'stocks_location_product_idx'));
    }

    public function down(): void
    {
        if (Schema::hasTable('stocks') && collect(Schema::getIndexes('stocks'))->contains('name', 'stocks_location_product_idx')) {
            Schema::table('stocks', fn ($t) => $t->dropIndex('stocks_location_product_idx'));
        }
    }
};
