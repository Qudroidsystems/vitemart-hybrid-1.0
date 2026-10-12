<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Stock;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The single place where stock is changed.
 *
 * The `stocks` table is an append-only ledger; balances are the signed sum of rows.
 * After every write the cached balances on products.stock and product_variations.stock
 * are refreshed so lists and the POS can read them without aggregating.
 */
class StockService
{
    public const IN_TYPES = ['in', 'adjustment', 'transfer_in', 'return'];
    public const OUT_TYPES = ['out', 'damage', 'transfer'];

    public static function signedSql(string $alias = ''): string
    {
        $c = $alias ? $alias . '.' : '';
        return "COALESCE(SUM(CASE WHEN {$c}type IN ('in','adjustment','transfer_in','return') THEN {$c}quantity "
            . "WHEN {$c}type IN ('out','damage','transfer') THEN -{$c}quantity ELSE 0 END), 0)";
    }

    public function defaultLocation(): StockLocation
    {
        $branchId = \App\Services\MultiBranch\Install::branchId();
        if ($branchId) {
            $loc = StockLocation::where('branch_id', $branchId)->where('is_active', true)
                ->where(fn ($q) => $q->where('type', 'store')->orWhereNull('type'))
                ->orderByDesc('is_default')->orderBy('id')->first();
            if ($loc) {
                return $loc;
            }
        }

        $loc = StockLocation::where('is_default', true)->where('is_active', true)->first()
            ?? StockLocation::where('code', 'MAIN')->first()
            ?? StockLocation::where('is_active', true)->orderBy('sort_order')->first();

        if (!$loc) {
            $loc = StockLocation::create(['name' => 'Main Store', 'code' => 'MAIN', 'is_default' => true, 'is_active' => true, 'type' => 'store', 'branch_id' => $branchId]);
        }
        return $loc;
    }

    /**
     * Current balance. Pass a variation id to get that variation's balance only.
     */
    /**
     * @param bool $current read the latest committed rows (locking read). Inside a transaction a plain read can see an
     *                      older snapshot – two tills would both see the last unit.  record() always uses this.
     */
    public function balance(int $productId, ?int $variationId = null, ?int $locationId = null, bool $current = false): float
    {
        $q = DB::table('stocks')->where('product_id', $productId)->whereNull('deleted_at');
        if ($current) {
            $q->sharedLock();
        }
        if ($variationId) {
            $q->where('product_variation_id', $variationId);
        }
        if ($locationId) {
            $q->where('stock_location_id', $locationId);
        }
        return round((float) $q->selectRaw(self::signedSql() . ' as total')->value('total'), 3);
    }

    /**
     * Post one movement to the ledger.
     *
     * @param array{
     *   product_id:int, variation_id?:int|null, location_id?:int|null,
     *   direction:'in'|'out', quantity:float, type?:string,
     *   reference_type?:string, reference_number?:string, unit_cost?:float|null,
     *   reason?:string|null, notes?:string|null, allow_negative?:bool,
     *   expiry_date?:string|null, batch_number?:string|null, user_id?:int|null
     * } $data
     */
    public function record(array $data): Stock
    {
        $qty = round(abs((float) $data['quantity']), 3);
        if ($qty <= 0) {
            throw new \InvalidArgumentException('Quantity must be greater than zero.');
        }

        $direction = $data['direction'];
        $type = $data['type'] ?? ($direction === 'in' ? 'in' : 'out');
        if ($direction === 'in' && !in_array($type, self::IN_TYPES, true)) {
            $type = 'in';
        }
        if ($direction === 'out' && !in_array($type, self::OUT_TYPES, true)) {
            $type = 'out';
        }

        $productId = (int) $data['product_id'];
        $variationId = !empty($data['variation_id']) ? (int) $data['variation_id'] : null;
        $locationId = (int) ($data['location_id'] ?? $this->defaultLocation()->id);

        $run = function () use ($data, $qty, $direction, $type, $productId, $variationId, $locationId) {
            // Serialise concurrent sales of the same product.
            $product = Product::query()->whereKey($productId)->lockForUpdate()->firstOrFail();

            $previous = $this->balance($productId, $variationId, $locationId, true);
            $new = $direction === 'in' ? $previous + $qty : $previous - $qty;

            $allowNegative = $data['allow_negative'] ?? (bool) SystemSetting::get('allow_negative_stock', false);
            if ($direction === 'out' && $product->track_stock !== false && !$allowNegative && $new < -0.0001) {
                $name = $product->title;
                if ($variationId) {
                    $name .= ' – ' . (ProductVariation::find($variationId)?->label ?? '');
                }
                throw new InsufficientStockException($name, $previous, $qty);
            }

            $unitCost = $data['unit_cost'] ?? null;
            if ($unitCost === null) {
                $unitCost = $variationId
                    ? (ProductVariation::find($variationId)?->effective_cost ?? $product->cost_price)
                    : $product->cost_price;
            }

            // Stock promised to transfers / orders can't be sold.
            if ($direction === 'out' && $type === 'out' && $product->track_stock !== false && !$allowNegative) {
                $bal = app(\App\Services\Inventory\InventoryBalances::class);
                if ($bal->ready() && $bal->sellable($locationId)) {
                    $branchId = $bal->bucketFor($locationId)['branch_id'];
                    $reserved = $bal->reservedFor($productId, $variationId, $branchId, true);
                    if ($reserved > 0) {
                        $free = $bal->onHand($productId, $variationId, $branchId, true) - $reserved;
                        if ($free + 0.0001 < $qty) {
                            throw new InsufficientStockException($product->title . ' (some is reserved)', max(0, $free), $qty);
                        }
                    }
                }
            }

            $stock = Stock::create([
                'product_id' => $productId,
                'product_variation_id' => $variationId,
                'stock_location_id' => $locationId,
                'destination_location_id' => $data['destination_location_id'] ?? null,
                'user_id' => $data['user_id'] ?? auth()->id(),
                'type' => $type,
                'quantity' => $qty,
                'previous_quantity' => $previous,
                'new_quantity' => $new,
                'unit_cost' => $unitCost,
                'total_cost' => $unitCost !== null ? round($unitCost * $qty, 2) : null,
                'reference_number' => $data['reference_number'] ?? null,
                'reference_type' => $data['reference_type'] ?? null,
                'adjustment_reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'batch_number' => $data['batch_number'] ?? null,
                'transaction_date' => now(),
            ]);

            if (Schema::hasTable('stock_movements')) {
                $movementType = match ($type) {
                    'in', 'return' => 'in',
                    'out', 'damage' => 'out',
                    'transfer' => 'transfer_out',
                    'transfer_in' => 'transfer_in',
                    default => 'adjustment',
                };
                StockMovement::create([
                    'stock_id' => $stock->id,
                    'product_id' => $productId,
                    'stock_location_id' => $locationId,
                    'movement_type' => $movementType,
                    'quantity' => $qty,
                    'balance' => $new,
                    'reference' => $data['reference_number'] ?? null,
                    'description' => trim(($data['reason'] ?? '') . ' ' . ($data['notes'] ?? '')) ?: null,
                    'user_id' => $data['user_id'] ?? auth()->id(),
                ]);
            }

            app(\App\Services\Inventory\InventoryBalances::class)->apply($locationId, $productId, $variationId, $direction === 'in' ? $qty : -$qty);
            $this->refreshCache($productId, $variationId);

            return $stock;
        };

        return DB::transactionLevel() > 0 ? $run() : DB::transaction($run);
    }

    /** Convenience: move stock between two locations. */
    public function transfer(int $productId, ?int $variationId, int $fromId, int $toId, float $qty, array $meta = []): array
    {
        return DB::transaction(function () use ($productId, $variationId, $fromId, $toId, $qty, $meta) {
            $ref = $meta['reference_number'] ?? ('TRF-' . now()->format('ymdHis') . rand(10, 99));
            $out = $this->record($meta + [
                'product_id' => $productId, 'variation_id' => $variationId, 'location_id' => $fromId,
                'direction' => 'out', 'type' => 'transfer', 'quantity' => $qty, 'reference_type' => 'transfer',
                'reference_number' => $ref, 'destination_location_id' => $toId, 'allow_negative' => false,
            ]);
            $in = $this->record($meta + [
                'product_id' => $productId, 'variation_id' => $variationId, 'location_id' => $toId,
                'direction' => 'in', 'type' => 'transfer_in', 'quantity' => $qty, 'reference_type' => 'transfer',
                'reference_number' => $ref,
            ]);
            return [$out, $in];
        });
    }

    /**
     * Refresh products.stock / product_variations.stock: what this branch can sell
     * (store + warehouse locations of this server's branch).
     */
    public function refreshCache(int $productId, ?int $variationId = null): void
    {
        $bal = app(\App\Services\Inventory\InventoryBalances::class);
        $useBalances = $bal->ready() && \App\Services\MultiBranch\Install::branchId();

        DB::table('products')->where('id', $productId)
            ->update(['stock' => $useBalances ? $bal->onHand($productId, null, null, true) : $this->balance($productId, null, null, true)]);

        if ($variationId && Schema::hasColumn('product_variations', 'stock')) {
            DB::table('product_variations')->where('id', $variationId)
                ->update(['stock' => $useBalances ? $bal->onHand($productId, $variationId, null, true) : $this->balance($productId, $variationId, null, true)]);
        }
    }

    /** Rebuild every cached balance – used by the "Sync stock" button / command. */
    public function rebuildAll(): int
    {
        $bal = app(\App\Services\Inventory\InventoryBalances::class);
        if ($bal->ready() && \App\Services\MultiBranch\Install::branchId()) {
            $bal->rebuild();
            $branchId = \App\Services\MultiBranch\Install::branchId();
            DB::statement('UPDATE products p LEFT JOIN (SELECT product_id, SUM(on_hand) q FROM inventory_balances WHERE branch_id = ? GROUP BY product_id) b
                ON b.product_id = p.id SET p.stock = COALESCE(b.q, 0)', [$branchId]);
            if (Schema::hasColumn('product_variations', 'stock')) {
                DB::statement('UPDATE product_variations v LEFT JOIN inventory_balances b ON b.variation_key = v.id AND b.product_id = v.product_id AND b.branch_id = ?
                    SET v.stock = COALESCE(b.on_hand, 0)', [$branchId]);
            }
            return (int) DB::table('products')->count();
        }

        $count = 0;
        Product::query()->select('id')->chunkById(500, function ($products) use (&$count) {
            foreach ($products as $p) {
                $this->refreshCache($p->id);
                $count++;
            }
        });
        if (Schema::hasColumn('product_variations', 'stock')) {
            DB::statement('
                UPDATE product_variations v
                LEFT JOIN (
                    SELECT product_variation_id, ' . self::signedSql() . ' AS bal
                    FROM stocks WHERE product_variation_id IS NOT NULL AND deleted_at IS NULL
                    GROUP BY product_variation_id
                ) s ON s.product_variation_id = v.id
                SET v.stock = COALESCE(s.bal, 0)
            ');
        }
        return $count;
    }
}
