<?php

namespace App\Services\Catalogue;

use App\Support\Xlsx\XlsxWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The product spreadsheet: its columns, and the download (all products or a blank template).
 * The same columns are read back by ProductImporter.
 */
class ProductSpreadsheet
{
    public const SHEET = 'Products';

    /**
     * key => [header, width, type, flags]
     * flags: required (for new products), muted (information only – ignored on upload), list (dropdown source)
     */
    public static function columns(): array
    {
        return [
            'id' => ['ID (don\'t change)', 10, 'number', ['muted' => true]],
            'title' => ['Product name *', 34, 'text', ['required' => true]],
            'sku' => ['SKU', 16, 'text', []],
            'barcode' => ['Barcode', 17, 'text', []],
            'category' => ['Category', 20, 'text', ['list' => 'A']],
            'brand' => ['Brand', 18, 'text', ['list' => 'B']],
            'unit' => ['Unit', 12, 'text', ['list' => 'C']],
            'price' => ['Selling price *', 14, 'number', ['required' => true]],
            'cost_price' => ['Cost price', 12, 'number', []],
            'wholesale_price' => ['Wholesale price', 14, 'number', []],
            'promo_price' => ['Promo price', 12, 'number', []],
            'promo_starts_at' => ['Promo starts', 13, 'date', []],
            'promo_ends_at' => ['Promo ends', 13, 'date', []],
            'reorder_level' => ['Reorder level', 12, 'number', []],
            'is_weighted' => ['Sold by weight', 13, 'text', ['list' => 'D']],
            'track_stock' => ['Track stock', 11, 'text', ['list' => 'D']],
            'allow_price_override' => ['Cashier can change price', 14, 'text', ['list' => 'D']],
            'is_active' => ['Active', 9, 'text', ['list' => 'D']],
            'plu_code' => ['Scale PLU', 10, 'text', []],
            'pack1_unit' => ['Pack 1 unit', 12, 'text', ['list' => 'C']],
            'pack1_qty' => ['Pack 1 contains', 12, 'number', []],
            'pack1_price' => ['Pack 1 price', 12, 'number', []],
            'pack1_barcode' => ['Pack 1 barcode', 17, 'text', []],
            'pack2_unit' => ['Pack 2 unit', 12, 'text', ['list' => 'C']],
            'pack2_qty' => ['Pack 2 contains', 12, 'number', []],
            'pack2_price' => ['Pack 2 price', 12, 'number', []],
            'pack2_barcode' => ['Pack 2 barcode', 17, 'text', []],
            'description' => ['Description', 40, 'text', []],
            'stock' => ['Current stock (info)', 13, 'number', ['muted' => true]],
            'receive_qty' => ['Add stock (received)', 14, 'number', []],
            'count_qty' => ['Stock count (replaces)', 15, 'number', []],
            'product_type' => ['Type (info)', 10, 'text', ['muted' => true]],
        ];
    }

    /** Header text (any case, with or without * or brackets) and older names → column key. */
    public static function headerMap(): array
    {
        $map = [];
        foreach (self::columns() as $key => [$title]) {
            $map[self::norm($title)] = $key;
            $map[self::norm($key)] = $key;
        }
        $aliases = [
            'id' => ['product id'],
            'title' => ['name', 'product', 'item', 'item name', 'product title', 'title'],
            'barcode' => ['ean', 'upc', 'bar code'],
            'category' => ['category name', 'category id', 'category_id'],
            'brand' => ['brand name', 'brand id', 'brand_id'],
            'unit' => ['base unit', 'unit of measure', 'uom', 'sold as'],
            'price' => ['selling price', 'retail price', 'price'],
            'cost_price' => ['cost', 'buying price', 'purchase price'],
            'wholesale_price' => ['wholesale'],
            'promo_starts_at' => ['promo start', 'promo starts at'],
            'promo_ends_at' => ['promo end', 'promo ends at'],
            'reorder_level' => ['reorder', 'minimum stock', 'min stock'],
            'is_weighted' => ['weighted', 'by weight'],
            'allow_price_override' => ['price override'],
            'is_active' => ['status', 'active'],
            'plu_code' => ['plu'],
            'stock' => ['current stock', 'stock', 'quantity in stock', 'qty'],
            'receive_qty' => ['add stock', 'received', 'qty received', 'stock received', 'opening stock'],
            'count_qty' => ['stock count', 'counted', 'counted stock', 'new stock'],
        ];
        foreach ($aliases as $key => $list) {
            foreach ($list as $a) {
                $map[self::norm($a)] = $key;
            }
        }
        return $map;
    }

    public static function norm(string $h): string
    {
        $h = strtolower(trim($h));
        $h = preg_replace('/\([^)]*\)/', '', $h);
        $h = preg_replace('/[^a-z0-9]+/', ' ', $h);
        return trim($h);
    }

    /**
     * Write the spreadsheet. $filters: status (all|active|inactive), category_id, template (bool).
     * Returns the file path.
     */
    public function export(string $path, array $filters = []): string
    {
        @set_time_limit(600);
        $cols = self::columns();
        $keys = array_keys($cols);
        $w = new XlsxWriter($path);

        // ---- Products sheet
        $w->sheet(self::SHEET, array_map(fn ($c) => [
            'title' => $c[0], 'width' => $c[1], 'type' => $c[2],
            'required' => !empty($c[3]['required']), 'muted' => !empty($c[3]['muted']),
        ], array_values($cols)));

        $lists = $this->lists();
        $listCol = ['A' => count($lists['categories']), 'B' => count($lists['brands']), 'C' => count($lists['units']), 'D' => 2];
        foreach ($keys as $i => $k) {
            $l = $cols[$k][3]['list'] ?? null;
            if ($l) {
                $src = 'Lists!$' . $l . '$2:$' . $l . '$' . max(2, $listCol[$l] + 1);
                $w->validation($i, $src, 2, 200000);
            }
        }

        $count = 0;
        if (empty($filters['template'])) {
            foreach ($this->productRows($filters) as $row) {
                $w->row(array_map(fn ($k) => $row[$k] ?? null, $keys));
                $count++;
            }
        }

        // ---- Instructions
        $w->sheet('Instructions', [['width' => 30], ['width' => 100]], false);
        $w->row(['How to use this spreadsheet', ''], XlsxWriter::S_TITLE);
        $w->row(['', '']);
        foreach ($this->instructions() as [$a, $b]) {
            $w->row([$a, $b], XlsxWriter::S_WRAP);
        }

        // ---- Lists (dropdown sources)
        $w->sheet('Lists', [['title' => 'Categories', 'width' => 28], ['title' => 'Brands', 'width' => 24], ['title' => 'Units', 'width' => 16], ['title' => 'Yes / No', 'width' => 10]]);
        $max = max(count($lists['categories']), count($lists['brands']), count($lists['units']), 2);
        for ($i = 0; $i < $max; $i++) {
            $w->row([$lists['categories'][$i] ?? null, $lists['brands'][$i] ?? null, $lists['units'][$i] ?? null, ['Yes', 'No'][$i] ?? null]);
        }

        $w->close();
        return $path;
    }

    /** Rows for every matching product, 1,000 at a time. */
    public function productRows(array $filters = []): \Generator
    {
        $categories = DB::table('categories')->pluck('name', 'id')->all();
        $brands = Schema::hasTable('brands') ? DB::table('brands')->pluck('name', 'id')->all() : [];
        $units = DB::table('units')->pluck('name', 'id')->all();

        $q = DB::table('products')->orderBy('id');
        if (($filters['status'] ?? 'all') === 'active') {
            $q->where('is_active', true);
        } elseif (($filters['status'] ?? 'all') === 'inactive') {
            $q->where('is_active', false);
        }
        if (!empty($filters['category_id'])) {
            $q->where('category_id', $filters['category_id']);
        }

        $last = 0;
        do {
            $chunk = (clone $q)->where('id', '>', $last)->limit(1000)->get();
            if ($chunk->isEmpty()) {
                break;
            }
            $packs = DB::table('product_unit')->whereIn('product_id', $chunk->pluck('id'))
                ->orderBy('product_id')->orderByDesc('is_base')->orderBy('sort_order')->orderBy('id')->get()->groupBy('product_id');

            foreach ($chunk as $p) {
                $ps = $packs->get($p->id, collect());
                $base = $ps->firstWhere('is_base', 1) ?? $ps->first();
                $extra = $ps->filter(fn ($x) => !$base || $x->id !== $base->id)->values();
                $row = [
                    'id' => $p->id,
                    'title' => $p->title,
                    'sku' => $p->sku,
                    'barcode' => $p->barcode,
                    'category' => $categories[$p->category_id] ?? null,
                    'brand' => $brands[$p->brand_id] ?? null,
                    'unit' => $base ? ($units[$base->unit_id] ?? null) : null,
                    'price' => $p->price,
                    'cost_price' => $p->cost_price,
                    'wholesale_price' => $p->wholesale_price ?? null,
                    'promo_price' => $p->promo_price ?? null,
                    'promo_starts_at' => !empty($p->promo_starts_at) ? new \DateTime(substr($p->promo_starts_at, 0, 10), new \DateTimeZone('UTC')) : null,
                    'promo_ends_at' => !empty($p->promo_ends_at) ? new \DateTime(substr($p->promo_ends_at, 0, 10), new \DateTimeZone('UTC')) : null,
                    'reorder_level' => $p->reorder_level ?? null,
                    'is_weighted' => !empty($p->is_weighted) ? 'Yes' : 'No',
                    'track_stock' => ($p->track_stock ?? 1) ? 'Yes' : 'No',
                    'allow_price_override' => !empty($p->allow_price_override) ? 'Yes' : 'No',
                    'is_active' => ($p->is_active ?? 1) ? 'Yes' : 'No',
                    'plu_code' => $p->plu_code ?? null,
                    'description' => $p->description !== null ? mb_substr(strip_tags((string) $p->description), 0, 3000) : null,
                    'stock' => $p->stock,
                    'product_type' => $p->product_type,
                ];
                foreach ([1, 2] as $n) {
                    $x = $extra[$n - 1] ?? null;
                    if ($x) {
                        $row["pack{$n}_unit"] = $units[$x->unit_id] ?? null;
                        $row["pack{$n}_qty"] = (float) $x->quantity_per_unit;
                        $row["pack{$n}_price"] = $x->price;
                        $row["pack{$n}_barcode"] = $x->barcode;
                    }
                }
                yield $row;
                $last = $p->id;
            }
        } while ($chunk->count() === 1000);
    }

    public function lists(): array
    {
        return [
            'categories' => DB::table('categories')->orderBy('name')->pluck('name')->filter()->unique()->values()->all(),
            'brands' => Schema::hasTable('brands') ? DB::table('brands')->orderBy('name')->pluck('name')->filter()->unique()->values()->all() : [],
            'units' => DB::table('units')->where(fn ($q) => Schema::hasColumn('units', 'is_active') ? $q->where('is_active', true)->orWhereNull('is_active') : $q)
                ->orderBy('name')->pluck('name')->filter()->unique()->values()->all(),
        ];
    }

    protected function instructions(): array
    {
        return [
            ['Adding new products', 'Add a new row at the bottom of the Products sheet and leave the ID column empty. Only "Product name" and "Selling price" are required. A barcode and SKU are created for you if you leave them empty.'],
            ['Changing products', 'Edit the cells you want to change and upload the file. Products are matched by ID, then by SKU, then by barcode – so you can also upload a spreadsheet from your supplier that has SKUs or barcodes but no IDs.'],
            ['Empty cells', 'An empty cell means "leave as it is" – it never deletes anything. To clear an optional value (cost, wholesale or promo price, promo dates, brand, category, description, PLU) type a single dash: -'],
            ['Category, Brand, Unit', 'Pick from the dropdown or type a name. On the upload page you can choose to create any category, brand or unit that doesn\'t exist yet.'],
            ['Unit', 'How the product is counted and sold: Piece, Kilogram, Litre, Pack… Kilogram/Litre products can be sold in fractions (0.5 kg).'],
            ['Packs', 'Bigger packs of the same product, e.g. Pack 1 unit = Carton, Pack 1 contains = 24, Pack 1 price = 7,200. A pack is matched by its unit; packs that aren\'t in the file are left untouched.'],
            ['Promo price and dates', 'The promo price is used between "Promo starts" and "Promo ends" (dates like 2026-12-01). Leave the dates empty to run the promo until you remove it.'],
            ['Yes / No columns', 'Sold by weight, Track stock, Cashier can change price, Active: type Yes or No.'],
            ['Current stock (info)', 'Shown for information only – changing it does nothing. Stock is changed only through the two stock columns, so every change is recorded in the stock history.'],
            ['Add stock (received)', 'Quantity of stock that has just arrived (in the product\'s unit). It is added to the current stock as one "goods received" entry for the whole upload.'],
            ['Stock count (replaces)', 'The quantity you counted on the shelf. The stock is set to this number (a stock count entry records the difference). Leave empty for products you didn\'t count.'],
            ['Approval', 'If your role needs approval for stock changes, the stock entries are created as "pending" and a supervisor approves them under Stock Adjustments.'],
            ['Grey columns', 'ID, Current stock and Type are for information. Don\'t change the ID – it is how the system knows which product a row is.'],
            ['Variable products', 'For products with variations (sizes, colours) only the product-level details are updated here; edit variation prices on the product page.'],
            ['Check before saving', 'After uploading you see a preview – how many products will be added, changed or skipped, and any problems with the row number – before anything is saved.'],
            ['File types', 'Excel .xlsx or .csv. Old .xls files: open them in Excel and "Save as" .xlsx first.'],
        ];
    }
}
