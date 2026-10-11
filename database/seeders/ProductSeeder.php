<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\ProductService;
use App\Services\StockAdjustmentService;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Sample supermarket products (prices in NGN - sample values, adjust as needed).
 *
 * Products are created through ProductService (same code as the product form),
 * so SKUs, barcodes, packs and price history are generated normally.
 * Opening stock is posted as ONE approved "goods received" stock adjustment.
 *
 * Safe to run again: products whose title already exists are skipped.
 */
class ProductSeeder extends Seeder
{
    /**
     * Each row:
     *  title, category, brand (or null), unit (base unit name),
     *  cost, price, wholesale, stock (opening, in base units), reorder,
     *  packs: [[unit, qty per pack, price, wholesale]], weighted
     */
    protected function products(): array
    {
        return [
            // ---------------- Beverages ----------------
            ['Coca-Cola 50cl PET', 'Beverages', 'Coca-Cola', 'Bottle', 300, 400, 360, 120, 24, [['Pack', 12, 4500, 4200]]],
            ['Pepsi 50cl PET', 'Beverages', 'Pepsi', 'Bottle', 280, 380, 340, 96, 24, [['Pack', 12, 4300, 4000]]],
            ['Bigi Cola 60cl', 'Beverages', 'Bigi', 'Bottle', 200, 300, 260, 60, 12, [['Pack', 12, 3400, 3100]]],
            ['Eva Table Water 75cl', 'Beverages', 'Eva', 'Bottle', 180, 250, 220, 144, 24, [['Pack', 12, 2800, 2600]]],
            ['Chivita Exotic Juice 1L', 'Beverages', 'Chivita', 'Bottle', 1800, 2300, 2100, 30, 6, [['Carton', 10, 22000, 20500]]],
            ['Milo Refill 400g', 'Beverages', 'Nestlé', 'Piece', 3800, 4500, 4250, 25, 5, []],
            ['Nescafé Classic 50g', 'Beverages', 'Nestlé', 'Piece', 2200, 2800, 2600, 20, 5, []],
            ['Lipton Yellow Label Tea (100 bags)', 'Beverages', 'Lipton', 'Pack', 2500, 3200, 2950, 18, 4, []],

            // ---------------- Dairy & Breakfast ----------------
            ['Peak Full Cream Milk Powder 400g', 'Dairy & Breakfast', 'Peak', 'Piece', 4200, 5000, 4700, 24, 6, []],
            ['Hollandia Evaporated Milk 160g', 'Dairy & Breakfast', 'Hollandia', 'Piece', 450, 600, 540, 96, 24, [['Carton', 48, 27500, 25500]]],
            ['Golden Morn 500g', 'Dairy & Breakfast', 'Nestlé', 'Pack', 2600, 3200, 3000, 20, 5, []],
            ['Fresh Eggs', 'Dairy & Breakfast', null, 'Piece', 170, 220, 195, 150, 30, [['Crate', 30, 6200, 5700]]],

            // ---------------- Grains & Staples ----------------
            ['Mama Gold Rice 5kg', 'Grains & Staples', 'Mama Gold', 'Bag', 9500, 11500, 10800, 20, 5, []],
            ['Mama Gold Rice 50kg', 'Grains & Staples', 'Mama Gold', 'Bag', 85000, 98000, 94000, 6, 2, []],
            ['Golden Penny Semovita 1kg', 'Grains & Staples', 'Golden Penny', 'Pack', 1500, 1900, 1750, 40, 10, [['Bag', 10, 18500, 17000]]],
            ['Honeywell Wheat Meal 1kg', 'Grains & Staples', 'Honeywell', 'Pack', 1700, 2100, 1950, 30, 10, []],
            ['Dangote Granulated Sugar 1kg', 'Grains & Staples', 'Dangote', 'Pack', 1800, 2200, 2050, 40, 10, []],
            ['Honey Beans', 'Grains & Staples', null, 'Kilogram', 1600, 2000, 1850, 50, 10, [], true],
            ['Ijebu Garri', 'Grains & Staples', null, 'Kilogram', 900, 1200, 1100, 60, 10, [], true],

            // ---------------- Noodles & Pasta ----------------
            ['Indomie Chicken Flavour 70g', 'Noodles & Pasta', 'Indomie', 'Piece', 250, 320, 290, 200, 40, [['Carton', 40, 12500, 11600]]],
            ['Indomie Belle-Full 210g', 'Noodles & Pasta', 'Indomie', 'Piece', 550, 700, 640, 80, 20, [['Carton', 20, 13500, 12600]]],
            ['Golden Penny Spaghetti 500g', 'Noodles & Pasta', 'Golden Penny', 'Pack', 850, 1100, 1000, 100, 20, [['Carton', 20, 21500, 20000]]],

            // ---------------- Cooking Essentials ----------------
            ['Power Oil 1L', 'Cooking Essentials', 'Power Oil', 'Bottle', 3800, 4500, 4250, 30, 6, [['Carton', 12, 53000, 50000]]],
            ['Kings Vegetable Oil 3L', 'Cooking Essentials', 'Kings', 'Bottle', 10500, 12500, 11800, 12, 3, []],
            ['Gino Tomato Paste 70g', 'Cooking Essentials', 'Gino', 'Sachet', 180, 250, 220, 200, 50, [['Carton', 50, 12000, 10800]]],
            ['Maggi Star Cubes (100 pcs)', 'Cooking Essentials', 'Maggi', 'Pack', 1100, 1500, 1350, 30, 6, []],
            ['Knorr Chicken Cubes (50 pcs)', 'Cooking Essentials', 'Knorr', 'Pack', 900, 1200, 1100, 30, 6, []],
            ['Dangote Iodized Salt 500g', 'Cooking Essentials', 'Dangote', 'Pack', 300, 450, 400, 50, 10, []],

            // ---------------- Snacks & Biscuits ----------------
            ["McVitie's Digestive 400g", 'Snacks & Biscuits', "McVitie's", 'Pack', 2800, 3500, 3250, 20, 5, []],
            ['Pringles Original 165g', 'Snacks & Biscuits', 'Pringles', 'Piece', 4500, 5500, 5100, 15, 4, []],
            ['Gala Sausage Roll', 'Snacks & Biscuits', 'Gala', 'Piece', 180, 250, 220, 100, 20, [['Carton', 24, 5600, 5100]]],
            ['Plantain Chips 100g', 'Snacks & Biscuits', null, 'Pack', 300, 500, 420, 40, 10, []],

            // ---------------- Household & Cleaning ----------------
            ['Ariel Detergent 900g', 'Household & Cleaning', 'Ariel', 'Pack', 3200, 3900, 3650, 25, 5, []],
            ['Hypo Bleach 1L', 'Household & Cleaning', 'Hypo', 'Bottle', 1200, 1600, 1450, 24, 6, [['Carton', 12, 18000, 16800]]],
            ['Morning Fresh Dishwashing Liquid 450ml', 'Household & Cleaning', 'Morning Fresh', 'Bottle', 1300, 1700, 1550, 24, 6, []],
            ['Mortein Insecticide 300ml', 'Household & Cleaning', 'Mortein', 'Piece', 2500, 3200, 2950, 15, 4, []],
            ['Toilet Tissue (4 rolls)', 'Household & Cleaning', null, 'Pack', 1000, 1400, 1250, 40, 10, [['Bag', 12, 16000, 14800]]],

            // ---------------- Personal Care ----------------
            ['Close Up Toothpaste 140g', 'Personal Care', 'Close Up', 'Piece', 1300, 1700, 1550, 36, 8, []],
            ['Dettol Antiseptic Liquid 250ml', 'Personal Care', 'Dettol', 'Bottle', 2500, 3100, 2900, 20, 5, []],
            ['Lux Beauty Soap 100g', 'Personal Care', 'Lux', 'Piece', 450, 600, 540, 72, 12, [['Pack', 6, 3400, 3150]]],
            ['Nivea Body Lotion 400ml', 'Personal Care', 'Nivea', 'Bottle', 4500, 5500, 5100, 12, 3, []],

            // ---------------- Baby Care ----------------
            ['Pampers Diapers Size 3 (30 pcs)', 'Baby Care', 'Pampers', 'Pack', 9500, 11500, 10800, 12, 3, []],
            ['Cerelac Wheat 400g', 'Baby Care', 'Nestlé', 'Piece', 4000, 4800, 4500, 15, 4, []],

            // ---------------- Fresh Produce (sold by weight) ----------------
            ['Fresh Tomatoes', 'Fresh Produce', null, 'Kilogram', 1200, 1600, 1450, 40, 10, [], true],
            ['Irish Potatoes', 'Fresh Produce', null, 'Kilogram', 1000, 1400, 1250, 40, 10, [], true],
            ['Red Onions', 'Fresh Produce', null, 'Kilogram', 900, 1300, 1150, 40, 10, [], true],
        ];
    }

    public function run(): void
    {
        // Act as the default admin so created_by / audit logs are filled in.
        $admin = User::where('email', UserTableSeeder::ADMIN_LOGIN)->first();
        if ($admin) {
            auth()->setUser($admin);
        }

        ProductService::ensureDefaultUnits();

        $productService = app(ProductService::class);

        $categoryIds = [];
        $brandIds = [];
        $unitIds = [];
        $stockItems = [];
        $created = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($this->products() as $row) {
            [$title, $categoryName, $brandName, $unitName, $cost, $price, $wholesale, $stock, $reorder, $packs] = $row;
            $weighted = $row[10] ?? false;

            if (Product::where('title', $title)->exists()) {
                $skipped++;
                continue;
            }

            $categoryIds[$categoryName] ??= Category::firstOrCreate(['name' => $categoryName], ['is_featured' => false])->id;

            if ($brandName) {
                $brandIds[$brandName] ??= Brand::firstOrCreate(['name' => $brandName], ['is_featured' => false])->id;
            }

            $payload = [
                'title'           => $title,
                'product_type'    => 'simple',
                'category_id'     => $categoryIds[$categoryName],
                'brand_id'        => $brandName ? $brandIds[$brandName] : null,
                'cost_price'      => $cost,
                'price'           => $price,
                'wholesale_price' => $wholesale,
                'reorder_level'   => $reorder,
                'base_unit_id'    => $this->unitId($unitName, $unitIds),
                'is_active'       => 1,
                'track_stock'     => 1,
                'is_weighted'     => $weighted ? 1 : 0,
                'description'     => $title,
                'packs'           => [],
            ];

            foreach ($packs as [$packUnit, $qty, $packPrice, $packWholesale]) {
                $payload['packs'][] = [
                    'unit_id'         => $this->unitId($packUnit, $unitIds),
                    'quantity_per_unit' => $qty,
                    'price'           => $packPrice,
                    'wholesale_price' => $packWholesale,
                    'cost_price'      => round($cost * $qty, 2),
                    'is_sellable'     => 1,
                    'auto_barcode'    => 1,
                ];
            }

            try {
                $product = $productService->save(Request::create('/seed/products', 'POST', $payload));
                $created++;

                if ($stock > 0) {
                    $stockItems[] = [
                        'product_id' => $product->id,
                        'mode'       => 'add',
                        'quantity'   => $stock,
                        'unit_cost'  => $cost,
                    ];
                }
            } catch (ValidationException $e) {
                $failed++;
                $this->command?->warn("    ⚠️  {$title}: " . collect($e->errors())->flatten()->implode(' '));
            } catch (\Throwable $e) {
                $failed++;
                $this->command?->warn("    ⚠️  {$title}: " . $e->getMessage());
            }
        }

        // Opening stock: one approved "goods received" document for everything.
        if ($stockItems) {
            app(StockAdjustmentService::class)->create([
                'type'        => 'receive',
                'location_id' => app(StockService::class)->defaultLocation()->id,
                'reason'      => 'Opening stock',
                'notes'       => 'Sample products added by ProductSeeder',
                'supplier'    => 'Opening balance',
                'items'       => $stockItems,
            ], 'approve');
        }

        $this->command?->info("    📦 Products created: {$created}, already existed: {$skipped}, failed: {$failed}");

        if ($failed > 0) {
            // Mark the seeder as failed so DatabaseSeeder retries it next time.
            throw new \RuntimeException("{$failed} product(s) could not be created - see warnings above.");
        }
    }

    /**
     * Find a unit by name (creating it if needed) and cache the id.
     */
    protected function unitId(string $name, array &$cache): int
    {
        if (!isset($cache[$name])) {
            $unit = Unit::where('name', $name)->first()
                ?? Unit::create(['name' => $name, 'short_name' => strtolower(substr($name, 0, 3)), 'is_active' => true]);
            $cache[$name] = $unit->id;
        }

        return $cache[$name];
    }
}
