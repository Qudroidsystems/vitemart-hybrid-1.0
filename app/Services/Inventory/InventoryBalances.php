<?php

namespace App\Services\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryBalance;
use App\Models\StockReservation;
use App\Services\MultiBranch\Install;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-branch stock positions, kept in step with the stock ledger.
 *
 *   on_hand      store + warehouse locations of the branch (what is on the shelf / in the back)
 *   quarantined  quarantine locations (damaged, recalled, waiting for a decision)
 *   in_transit   transit locations (dispatched by this branch, not yet received)
 *   reserved     promised to a transfer / order and not yet moved   (StockReservation)
 *   incoming     expected from a transfer / purchase order           (set by those flows)
 *   available    on_hand − reserved
 */
class InventoryBalances
{
    /** @var array<int, array{branch_id:?int, type:string}> */
    protected array $locations = [];
    protected ?bool $ready = null;

    public function ready(): bool
    {
        return $this->ready ??= Schema::hasTable('inventory_balances');
    }

    /** branch + bucket for a stock location. */
    public function bucketFor(int $locationId): array
    {
        if (!isset($this->locations[$locationId])) {
            $l = DB::table('stock_locations')->where('id', $locationId)->first(['branch_id', 'type']);
            $type = $l->type ?? 'store';
            $this->locations[$locationId] = [
                'branch_id' => $l->branch_id ?? Install::branchId(),
                'column' => match ($type) {
                    'quarantine' => 'quarantined',
                    'transit' => 'in_transit',
                    default => 'on_hand',
                },
            ];
        }
        return $this->locations[$locationId];
    }

    public function sellable(int $locationId): bool
    {
        return $this->bucketFor($locationId)['column'] === 'on_hand';
    }

    /** Apply one ledger movement (signed quantity) to the balance row. */
    public function apply(int $locationId, int $productId, ?int $variationId, float $signedQty): void
    {
        if (!$this->ready() || abs($signedQty) < 0.0000001) {
            return;
        }
        $b = $this->bucketFor($locationId);
        if (!$b['branch_id']) {
            return;
        }
        $col = $b['column'];
        DB::statement(
            "INSERT INTO inventory_balances (branch_id, product_id, variation_key, {$col}, updated_at) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE {$col} = {$col} + VALUES({$col}), updated_at = VALUES(updated_at)",
            [$b['branch_id'], $productId, (int) ($variationId ?: 0), round($signedQty, 3), now()]
        );
    }

    public function row(int $productId, ?int $variationId = null, ?int $branchId = null): ?InventoryBalance
    {
        if (!$this->ready()) {
            return null;
        }
        return InventoryBalance::where('branch_id', $branchId ?? Install::branchId())
            ->where('product_id', $productId)->where('variation_key', (int) ($variationId ?: 0))->first();
    }

    /** Sellable stock for this branch (all variations when $variationId is null). */
    public function onHand(int $productId, ?int $variationId = null, ?int $branchId = null): float
    {
        $q = DB::table('inventory_balances')->where('branch_id', $branchId ?? Install::branchId())->where('product_id', $productId);
        if ($variationId) {
            $q->where('variation_key', $variationId);
        }
        return round((float) $q->sum('on_hand'), 3);
    }

    public function available(int $productId, ?int $variationId = null, ?int $branchId = null): float
    {
        $q = DB::table('inventory_balances')->where('branch_id', $branchId ?? Install::branchId())->where('product_id', $productId);
        if ($variationId) {
            $q->where('variation_key', $variationId);
        }
        return round((float) $q->sum(DB::raw('on_hand - reserved')), 3);
    }

    public function reservedFor(int $productId, ?int $variationId = null, ?int $branchId = null): float
    {
        $q = DB::table('inventory_balances')->where('branch_id', $branchId ?? Install::branchId())->where('product_id', $productId);
        if ($variationId) {
            $q->where('variation_key', $variationId);
        }
        return round((float) $q->sum('reserved'), 3);
    }

    // ------------------------------------------------------------------
    // Reservations
    // ------------------------------------------------------------------

    /** Promise stock to something.  Refuses when it would take available below zero. */
    public function reserve(int $productId, ?int $variationId, float $qty, string $refType, string $ref, ?int $branchId = null, bool $allowShort = false): StockReservation
    {
        $branchId ??= Install::branchId();
        return DB::transaction(function () use ($productId, $variationId, $qty, $refType, $ref, $branchId, $allowShort) {
            DB::table('products')->where('id', $productId)->lockForUpdate()->value('id');
            if (!$allowShort) {
                $avail = $this->available($productId, $variationId, $branchId);
                if ($avail + 0.0001 < $qty) {
                    $title = DB::table('products')->where('id', $productId)->value('title');
                    throw new InsufficientStockException($title, max(0, $avail), $qty);
                }
            }
            $r = StockReservation::create([
                'branch_id' => $branchId, 'product_id' => $productId, 'product_variation_id' => $variationId,
                'quantity' => round($qty, 3), 'reference_type' => $refType, 'reference' => $ref, 'status' => 'active',
                'created_by' => auth()->id(),
            ]);
            $this->bump($branchId, $productId, $variationId, 'reserved', $qty);
            return $r;
        });
    }

    /** Release (cancelled) or consume (the stock moved) all active reservations for a reference. */
    public function release(string $refType, string $ref, string $as = 'released'): int
    {
        $n = 0;
        DB::transaction(function () use ($refType, $ref, $as, &$n) {
            foreach (StockReservation::where('reference_type', $refType)->where('reference', $ref)->where('status', 'active')->lockForUpdate()->get() as $r) {
                $this->bump($r->branch_id, $r->product_id, $r->product_variation_id, 'reserved', -$r->quantity);
                $r->update(['status' => $as]);
                $n++;
            }
        });
        return $n;
    }

    public function setIncoming(int $branchId, int $productId, ?int $variationId, float $delta): void
    {
        $this->bump($branchId, $productId, $variationId, 'incoming', $delta);
    }

    protected function bump(int $branchId, int $productId, ?int $variationId, string $col, float $delta): void
    {
        DB::statement(
            "INSERT INTO inventory_balances (branch_id, product_id, variation_key, {$col}, updated_at) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE {$col} = GREATEST(0, {$col} + ?), updated_at = VALUES(updated_at)",
            [$branchId, $productId, (int) ($variationId ?: 0), round(max(0, $delta), 3), now(), round($delta, 3)]
        );
    }

    // ------------------------------------------------------------------
    // Rebuild
    // ------------------------------------------------------------------

    /** Recompute on_hand / quarantined / in_transit from the ledger and reserved from reservations. */
    public function rebuild(?int $productId = null): int
    {
        $signed = "CASE WHEN s.type IN ('in','adjustment','transfer_in','return') THEN s.quantity WHEN s.type IN ('out','damage','transfer') THEN -s.quantity ELSE 0 END";
        $fallback = (int) (Install::branchId() ?? 0);

        return DB::transaction(function () use ($signed, $fallback, $productId) {
            $q = DB::table('inventory_balances');
            if ($productId) {
                $q->where('product_id', $productId);
            }
            $q->update(['on_hand' => 0, 'quarantined' => 0, 'in_transit' => 0, 'reserved' => 0]);

            $rows = DB::table('stocks as s')->leftJoin('stock_locations as l', 'l.id', '=', 's.stock_location_id')
                ->whereNull('s.deleted_at')
                ->when($productId, fn ($w) => $w->where('s.product_id', $productId))
                ->selectRaw("COALESCE(l.branch_id, s.branch_id, {$fallback}) as branch_id, s.product_id, COALESCE(s.product_variation_id, 0) as vkey,
                    SUM(CASE WHEN COALESCE(l.type, 'store') IN ('store','warehouse') THEN {$signed} ELSE 0 END) as on_hand,
                    SUM(CASE WHEN l.type = 'quarantine' THEN {$signed} ELSE 0 END) as quarantined,
                    SUM(CASE WHEN l.type = 'transit' THEN {$signed} ELSE 0 END) as in_transit")
                ->groupBy(DB::raw("COALESCE(l.branch_id, s.branch_id, {$fallback})"), 's.product_id', DB::raw('COALESCE(s.product_variation_id, 0)'))
                ->get();
            $now = now();
            foreach ($rows->chunk(500) as $chunk) {
                DB::table('inventory_balances')->upsert($chunk->map(fn ($r) => [
                    'branch_id' => $r->branch_id, 'product_id' => $r->product_id, 'variation_key' => $r->vkey,
                    'on_hand' => round((float) $r->on_hand, 3), 'quarantined' => round((float) $r->quarantined, 3),
                    'in_transit' => round((float) $r->in_transit, 3), 'updated_at' => $now,
                ])->all(), ['branch_id', 'product_id', 'variation_key'], ['on_hand', 'quarantined', 'in_transit', 'updated_at']);
            }
            $res = DB::table('stock_reservations')->where('status', 'active')
                ->when($productId, fn ($w) => $w->where('product_id', $productId))
                ->selectRaw('branch_id, product_id, COALESCE(product_variation_id, 0) as vkey, SUM(quantity) as q')
                ->groupBy('branch_id', 'product_id', DB::raw('COALESCE(product_variation_id, 0)'))->get();
            foreach ($res as $r) {
                DB::table('inventory_balances')->upsert([[
                    'branch_id' => $r->branch_id, 'product_id' => $r->product_id, 'variation_key' => $r->vkey, 'reserved' => $r->q, 'updated_at' => $now,
                ]], ['branch_id', 'product_id', 'variation_key'], ['reserved', 'updated_at']);
            }
            $this->locations = [];
            return $rows->count();
        });
    }
}
