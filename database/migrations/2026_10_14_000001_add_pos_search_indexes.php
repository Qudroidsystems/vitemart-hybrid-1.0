<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes that keep the POS search fast on large catalogues (100k+ products).
 * Every index is only added if it isn't there already, so this is safe to run
 * on any install. On a big products table it can take a minute to build.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Product grid ("Popular") and category tabs
        $this->addIndex('products', ['is_active', 'sold_quantity'], 'products_active_sold_idx');
        $this->addIndex('products', ['category_id', 'is_active'], 'products_category_active_idx');

        // Exact SKU look-ups and "SKU starts with" searches
        $this->addIndex('products', ['sku'], 'products_sku_idx');
        $this->addIndex('product_variations', ['sku'], 'product_variations_sku_idx');
        $this->addIndex('product_variations', ['barcode'], 'product_variations_barcode_idx');

        if ($this->isMySql()) {
            // "Name starts with …" – a prefix index is enough and stays small
            if (!$this->hasIndexOn('products', ['title'])) {
                DB::statement('CREATE INDEX products_title_idx ON products (title(100))');
            }
            // Word search anywhere in the name / SKU
            if (!$this->hasFullText('products')) {
                DB::statement('ALTER TABLE products ADD FULLTEXT products_search_ft (title, sku)');
            }
        } elseif (!$this->hasIndexOn('products', ['title'])) {
            Schema::table('products', fn ($t) => $t->index('title', 'products_title_idx'));
        }

        Cache::forget('pos_search_fulltext');
    }

    public function down(): void
    {
        foreach ([
            'products' => ['products_active_sold_idx', 'products_category_active_idx', 'products_sku_idx', 'products_title_idx', 'products_search_ft'],
            'product_variations' => ['product_variations_sku_idx', 'product_variations_barcode_idx'],
        ] as $table => $names) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $existing = collect(Schema::getIndexes($table))->pluck('name')->all();
            foreach ($names as $name) {
                if (in_array($name, $existing, true)) {
                    Schema::table($table, fn ($t) => $t->dropIndex($name));
                }
            }
        }
        Cache::forget('pos_search_fulltext');
    }

    protected function isMySql(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }

    protected function addIndex(string $table, array $columns, string $name): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }
        foreach ($columns as $c) {
            if (!Schema::hasColumn($table, $c)) {
                return;
            }
        }
        if ($this->hasIndexOn($table, $columns) || collect(Schema::getIndexes($table))->contains('name', $name)) {
            return;
        }
        Schema::table($table, fn ($t) => $t->index($columns, $name));
    }

    /** An existing (non full-text) index that starts with these columns already does the job. */
    protected function hasIndexOn(string $table, array $columns): bool
    {
        foreach (Schema::getIndexes($table) as $ix) {
            if (($ix['type'] ?? '') === 'fulltext') {
                continue;
            }
            if (array_slice($ix['columns'], 0, count($columns)) === $columns) {
                return true;
            }
        }
        return false;
    }

    protected function hasFullText(string $table): bool
    {
        foreach (Schema::getIndexes($table) as $ix) {
            if (($ix['type'] ?? '') === 'fulltext' && $ix['columns'] === ['title', 'sku']) {
                return true;
            }
        }
        return false;
    }
};
