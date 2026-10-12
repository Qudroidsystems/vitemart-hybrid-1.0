<?php

namespace App\Services\Inventory;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\Supplier;
use App\Services\Sync\MissingReference;
use App\Services\Sync\RowCodec;
use Illuminate\Support\Facades\DB;

/**
 * Transfers and purchase orders are shared documents: several servers change them, one step each
 * (the receiving branch asks, the sending branch dispatches, the receiving branch receives …).
 * They travel whole, and a server only accepts a copy that is further along than its own
 * (higher status, or the same status with a higher version).
 */
class DocumentPayload
{
    // ------------------------------------------------------------------ transfers

    public static function transfer(StockTransfer $t): array
    {
        $t->loadMissing('lines');
        $head = collect($t->getAttributes())->except(['id', 'from_branch_id', 'to_branch_id'])->all();
        $head['from_branch_uid'] = RowCodec::uidOf(Branch::class, $t->from_branch_id);
        $head['to_branch_uid'] = RowCodec::uidOf(Branch::class, $t->to_branch_id);
        $head['lines'] = $t->lines->map(fn (StockTransferLine $l) => collect($l->getAttributes())
            ->except(['id', 'stock_transfer_id', 'product_id', 'product_variation_id', 'product_unit_id'])->all() + [
                'product_uid' => RowCodec::uidOf(Product::class, $l->product_id),
                'variation_uid' => RowCodec::uidOf(ProductVariation::class, $l->product_variation_id),
                'pack_uid' => RowCodec::uidOf(ProductUnit::class, $l->product_unit_id),
            ])->values()->all();
        return $head;
    }

    /** @return array{0: ?StockTransfer, 1: bool} the local copy, and whether it changed */
    public static function importTransfer(array $d): array
    {
        $local = StockTransfer::where('uid', $d['uid'])->first();
        if ($local && !static::newer($d['status'], (int) $d['version'], $local->status, (int) $local->version, StockTransfer::RANK)) {
            return [$local, false];
        }
        $from = RowCodec::idOf(Branch::class, $d['from_branch_uid'] ?? null);
        $to = RowCodec::idOf(Branch::class, $d['to_branch_uid'] ?? null);
        if (!$from || !$to) {
            throw new MissingReference('Branch', (string) ($from ? $d['to_branch_uid'] : $d['from_branch_uid']));
        }
        $lines = [];
        foreach ($d['lines'] ?? [] as $l) {
            $pid = RowCodec::idOf(Product::class, $l['product_uid'] ?? null);
            if (!$pid) {
                throw new MissingReference('Product', (string) ($l['product_uid'] ?? '?'));
            }
            $lines[] = $l + [
                'product_id' => $pid,
                'product_variation_id' => RowCodec::idOf(ProductVariation::class, $l['variation_uid'] ?? null),
                'product_unit_id' => RowCodec::idOf(ProductUnit::class, $l['pack_uid'] ?? null),
            ];
        }

        return DB::transaction(function () use ($d, $local, $from, $to, $lines) {
            $head = collect($d)->only((new StockTransfer())->getFillable())->except(['uid'])->all();
            $t = $local ?? (new StockTransfer())->forceFill(['uid' => $d['uid']]);
            $t->forceFill($head + ['from_branch_id' => $from, 'to_branch_id' => $to]);
            $t->saveQuietly();
            $keep = [];
            foreach ($lines as $l) {
                $line = StockTransferLine::where('uid', $l['uid'])->first() ?? (new StockTransferLine())->forceFill(['uid' => $l['uid']]);
                $line->forceFill(collect($l)->only((new StockTransferLine())->getFillable())->except('uid')->all() + ['stock_transfer_id' => $t->id]);
                $line->saveQuietly();
                $keep[] = $line->id;
            }
            StockTransferLine::where('stock_transfer_id', $t->id)->whereNotIn('id', $keep ?: [0])->delete();
            return [$t->fresh('lines'), true];
        });
    }

    // ------------------------------------------------------------------ purchase orders

    public static function purchaseOrder(PurchaseOrder $po): array
    {
        $po->loadMissing('lines');
        $head = collect($po->getAttributes())->except(['id', 'branch_id', 'supplier_id'])->all();
        $head['branch_uid'] = RowCodec::uidOf(Branch::class, $po->branch_id);
        $head['supplier_uid'] = RowCodec::uidOf(Supplier::class, $po->supplier_id);
        $head['supplier_name'] = $po->supplier?->name;
        $head['lines'] = $po->lines->map(fn (PurchaseOrderLine $l) => collect($l->getAttributes())
            ->except(['id', 'purchase_order_id', 'product_id', 'product_variation_id', 'product_unit_id'])->all() + [
                'product_uid' => RowCodec::uidOf(Product::class, $l->product_id),
                'variation_uid' => RowCodec::uidOf(ProductVariation::class, $l->product_variation_id),
                'pack_uid' => RowCodec::uidOf(ProductUnit::class, $l->product_unit_id),
            ])->values()->all();
        return $head;
    }

    public static function importPurchaseOrder(array $d): array
    {
        $local = PurchaseOrder::where('uid', $d['uid'])->first();
        if ($local && !static::newer($d['status'], (int) $d['version'], $local->status, (int) $local->version, PurchaseOrder::RANK)) {
            return [$local, false];
        }
        $branch = RowCodec::idOf(Branch::class, $d['branch_uid'] ?? null);
        if (!$branch) {
            throw new MissingReference('Branch', (string) ($d['branch_uid'] ?? '?'));
        }
        $supplier = RowCodec::idOf(Supplier::class, $d['supplier_uid'] ?? null)
            ?? (!empty($d['supplier_name']) ? Supplier::where('name', $d['supplier_name'])->value('id') : null);
        if (!$supplier) {
            throw new MissingReference('Supplier', (string) ($d['supplier_uid'] ?? '?'));
        }
        $lines = [];
        foreach ($d['lines'] ?? [] as $l) {
            $pid = RowCodec::idOf(Product::class, $l['product_uid'] ?? null);
            if (!$pid) {
                throw new MissingReference('Product', (string) ($l['product_uid'] ?? '?'));
            }
            $lines[] = $l + [
                'product_id' => $pid,
                'product_variation_id' => RowCodec::idOf(ProductVariation::class, $l['variation_uid'] ?? null),
                'product_unit_id' => RowCodec::idOf(ProductUnit::class, $l['pack_uid'] ?? null),
            ];
        }
        return DB::transaction(function () use ($d, $local, $branch, $supplier, $lines) {
            $po = $local ?? (new PurchaseOrder())->forceFill(['uid' => $d['uid']]);
            $po->forceFill(collect($d)->only($po->getFillable())->except('uid')->all() + ['branch_id' => $branch, 'supplier_id' => $supplier]);
            $po->saveQuietly();
            $keep = [];
            foreach ($lines as $l) {
                $line = PurchaseOrderLine::where('uid', $l['uid'])->first() ?? (new PurchaseOrderLine())->forceFill(['uid' => $l['uid']]);
                $line->forceFill(collect($l)->only($line->getFillable())->except('uid')->all() + ['purchase_order_id' => $po->id]);
                $line->saveQuietly();
                $keep[] = $line->id;
            }
            PurchaseOrderLine::where('purchase_order_id', $po->id)->whereNotIn('id', $keep ?: [0])->delete();
            return [$po->fresh('lines'), true];
        });
    }

    public static function newer(string $inStatus, int $inVersion, string $localStatus, int $localVersion, array $rank): bool
    {
        $a = $rank[$inStatus] ?? 0;
        $b = $rank[$localStatus] ?? 0;
        return $a > $b || ($a === $b && $inVersion > $localVersion);
    }
}
