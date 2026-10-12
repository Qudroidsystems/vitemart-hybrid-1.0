<?php

namespace App\Services\Inventory;

use App\Models\Branch;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Models\SupplierReturnLine;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use App\Services\Catalogue\CatalogueRequests;
use App\Services\MultiBranch\DocumentNumber;
use App\Services\MultiBranch\Install;
use App\Services\PricingService;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Buying stock:  purchase order (draft → submitted → approved → sent → partly received → received)
 *                goods received note (with or without an order) – stock in, cost price updated
 *                return to supplier – stock out
 */
class PurchasingService
{
    public function __construct(protected StockService $stock, protected PricingService $pricing)
    {
    }

    protected function who(): string
    {
        $u = auth()->user();
        return $u ? trim($u->first_name . ' ' . $u->last_name) : 'system';
    }

    /** [{product_id, variation_id?, pack_id?, qty, unit_cost}] → line rows (qty in packs, cost per pack). */
    protected function lines(array $lines, bool $allowFreeOnly = false): array
    {
        $out = [];
        foreach ($lines as $l) {
            $p = Product::find($l['product_id'] ?? null);
            $qty = max(0, (float) ($l['qty'] ?? 0));
            if (!$p || ($qty <= 0 && !($allowFreeOnly && (float) ($l['free_qty'] ?? 0) > 0))) {
                continue;
            }
            $pack = !empty($l['pack_id']) ? ProductUnit::where('product_id', $p->id)->find($l['pack_id']) : null;
            $per = $pack ? max(0.001, (float) $pack->quantity_per_unit) : 1.0;
            $vid = !empty($l['variation_id']) ? (int) $l['variation_id'] : null;
            $baseCost = $vid ? (ProductVariation::find($vid)?->effective_cost ?? 0) : (float) $p->cost_price;
            $cost = isset($l['unit_cost']) && $l['unit_cost'] !== '' && $l['unit_cost'] !== null ? (float) $l['unit_cost'] : round($baseCost * $per, 2);
            $out[] = ['product_id' => $p->id, 'product_variation_id' => $vid, 'product_unit_id' => $pack?->id, 'qty_per_unit' => $per,
                'qty' => round($qty, 3), 'unit_cost' => round($cost, 2), 'raw' => $l];
        }
        return $out;
    }

    // ------------------------------------------------------------------ purchase orders

    public function createOrder(array $data): PurchaseOrder
    {
        $branchId = (int) ($data['branch_id'] ?? Install::branchId());
        if (Install::isBranch() && $branchId !== Install::branchId()) {
            throw ValidationException::withMessages(['branch_id' => 'A branch orders for itself; head office can order for any branch.']);
        }
        $rows = $this->lines($data['lines'] ?? []);
        if (!$rows) {
            throw ValidationException::withMessages(['lines' => 'Add at least one product with a quantity.']);
        }
        $po = DB::transaction(function () use ($data, $branchId, $rows) {
            $here = Install::branchId() ?: $branchId;
            $po = PurchaseOrder::create([
                'number' => DocumentNumber::next('po', $here, Branch::whereKey($here)->value('code')),
                'branch_id' => $branchId,
                'supplier_id' => (int) $data['supplier_id'],
                'status' => 'draft',
                'expected_date' => $data['expected_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by_name' => $this->who(),
            ]);
            $total = 0;
            foreach ($rows as $r) {
                PurchaseOrderLine::create(['purchase_order_id' => $po->id, 'product_id' => $r['product_id'], 'product_variation_id' => $r['product_variation_id'],
                    'product_unit_id' => $r['product_unit_id'], 'qty_per_unit' => $r['qty_per_unit'], 'ordered_qty' => $r['qty'], 'unit_cost' => $r['unit_cost']]);
                $total += $r['qty'] * $r['unit_cost'];
            }
            $po->update(['total' => round($total, 2)]);
            return $po;
        });
        AuditLogger::log('po_created', "Purchase order {$po->number} for " . money($po->total), 'purchasing');
        if (($data['action'] ?? 'draft') === 'submit') {
            return $this->submitOrder($po->fresh('lines'));
        }
        DocumentSync::share('purchase_order', $po);
        return $po->fresh('lines');
    }

    protected function bump(PurchaseOrder $po, array $attrs): void
    {
        $po->forceFill($attrs + ['version' => $po->version + 1])->save();
    }

    public function submitOrder(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only a draft can be submitted.']);
        }
        $limit = (float) SystemSetting::get('po_approval_limit', 0);
        $auto = auth()->user()?->can('Approve purchase order') || ($limit > 0 && $po->total <= $limit);
        $this->bump($po, $auto ? ['status' => 'approved', 'approved_by_name' => $this->who(), 'approved_at' => now()] : ['status' => 'submitted']);
        AuditLogger::log('po_submitted', "Purchase order {$po->number} " . ($auto ? 'approved' : 'sent for approval'), 'purchasing');
        DocumentSync::share('purchase_order', $po);
        return $po->fresh('lines');
    }

    public function approveOrder(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->status !== 'submitted') {
            throw ValidationException::withMessages(['status' => 'Only a submitted order can be approved.']);
        }
        $this->bump($po, ['status' => 'approved', 'approved_by_name' => $this->who(), 'approved_at' => now()]);
        AuditLogger::log('po_approved', "Purchase order {$po->number} approved", 'purchasing');
        DocumentSync::share('purchase_order', $po);
        return $po->fresh('lines');
    }

    public function markSent(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->status !== 'approved') {
            throw ValidationException::withMessages(['status' => 'Approve the order first.']);
        }
        $this->bump($po, ['status' => 'sent', 'sent_at' => now()]);
        DocumentSync::share('purchase_order', $po);
        return $po->fresh('lines');
    }

    public function cancelOrder(PurchaseOrder $po, ?string $reason): PurchaseOrder
    {
        if (in_array($po->status, ['partial', 'received', 'closed', 'cancelled'], true)) {
            throw ValidationException::withMessages(['status' => 'Goods have already been received on this order – close it instead.']);
        }
        $this->bump($po, ['status' => 'cancelled', 'cancel_reason' => $reason, 'closed_at' => now()]);
        AuditLogger::log('po_cancelled', "Purchase order {$po->number} cancelled: {$reason}", 'purchasing');
        DocumentSync::share('purchase_order', $po);
        return $po->fresh('lines');
    }

    public function closeOrder(PurchaseOrder $po): PurchaseOrder
    {
        if (!in_array($po->status, ['partial', 'received', 'sent', 'approved'], true)) {
            throw ValidationException::withMessages(['status' => 'This order can\'t be closed.']);
        }
        $this->bump($po, ['status' => 'closed', 'closed_at' => now()]);
        AuditLogger::log('po_closed', "Purchase order {$po->number} closed", 'purchasing');
        DocumentSync::share('purchase_order', $po);
        return $po->fresh('lines');
    }

    // ------------------------------------------------------------------ goods received

    /**
     * @param array $data location_id, supplier_id, purchase_order_id?, supplier_invoice, invoice_date, notes,
     *                    lines[]: product_id, variation_id?, pack_id?, qty, free_qty?, unit_cost, expiry_date?, batch_number?, po_line_id?
     */
    public function receive(array $data): GoodsReceipt
    {
        $po = !empty($data['purchase_order_id']) ? PurchaseOrder::with('lines')->findOrFail($data['purchase_order_id']) : null;
        if ($po) {
            if (!in_array($po->status, ['approved', 'sent', 'partial'], true)) {
                throw ValidationException::withMessages(['purchase_order_id' => 'This order is not open for receiving (' . (PurchaseOrder::STATUSES[$po->status] ?? $po->status) . ').']);
            }
            if (!Install::isStandalone() && $po->branch_id !== Install::branchId()) {
                throw ValidationException::withMessages(['purchase_order_id' => 'This order is for another branch.']);
            }
        }
        $rows = $this->lines($data['lines'] ?? [], true);
        if (!$rows) {
            throw ValidationException::withMessages(['lines' => 'Enter the quantity received for at least one product.']);
        }
        $location = !empty($data['location_id']) ? (int) $data['location_id'] : $this->stock->defaultLocation()->id;

        $grn = DB::transaction(function () use ($data, $po, $rows, $location) {
            $grn = GoodsReceipt::create([
                'number' => DocumentNumber::next('receive'),
                'stock_location_id' => $location,
                'supplier_id' => $data['supplier_id'] ?? $po?->supplier_id,
                'purchase_order_id' => $po?->id,
                'supplier_invoice' => $data['supplier_invoice'] ?? null,
                'invoice_date' => $data['invoice_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'received_by' => auth()->id(),
            ]);
            $total = 0;
            foreach ($rows as $r) {
                $paid = $r['qty'];
                $free = max(0, (float) ($r['raw']['free_qty'] ?? 0));
                $line = GoodsReceiptLine::create([
                    'goods_receipt_id' => $grn->id, 'purchase_order_line_id' => $r['raw']['po_line_id'] ?? null,
                    'product_id' => $r['product_id'], 'product_variation_id' => $r['product_variation_id'], 'product_unit_id' => $r['product_unit_id'],
                    'qty_per_unit' => $r['qty_per_unit'], 'quantity' => $paid, 'free_qty' => $free, 'unit_cost' => $r['unit_cost'],
                    'expiry_date' => $r['raw']['expiry_date'] ?? null, 'batch_number' => $r['raw']['batch_number'] ?? null,
                ]);
                $baseQty = round(($paid + $free) * $r['qty_per_unit'], 3);
                $lineCost = $paid * $r['unit_cost'];
                $baseCost = $baseQty > 0 ? round($lineCost / $baseQty, 4) : 0;
                $total += $lineCost;
                if ($baseQty > 0) {
                    $this->stock->record(['product_id' => $r['product_id'], 'variation_id' => $r['product_variation_id'], 'location_id' => $location,
                        'direction' => 'in', 'type' => 'in', 'quantity' => $baseQty, 'unit_cost' => $baseCost, 'reference_type' => 'purchase',
                        'reference_number' => $grn->number, 'reason' => 'Goods received' . ($grn->supplier ? ' from ' . $grn->supplier->name : ''),
                        'expiry_date' => $line->expiry_date?->toDateString(), 'batch_number' => $line->batch_number]);
                }
                // keep the cost price current (last cost per base unit, free goods included)
                if ($paid > 0 && $baseCost > 0) {
                    $product = Product::find($r['product_id']);
                    $model = $r['product_variation_id'] ? ProductVariation::find($r['product_variation_id']) : $product;
                    $this->pricing->setPrice($model, 'cost_price', round($baseCost, 2), 'receive', "{$grn->number}");
                    if (CatalogueRequests::governed($product)) {
                        DB::table('product_local_overrides')->insertOrIgnore(['product_id' => $product->id, 'field' => 'cost_price', 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
                if ($po && !empty($r['raw']['po_line_id']) && ($pl = $po->lines->firstWhere('id', (int) $r['raw']['po_line_id']))) {
                    $pl->update(['received_qty' => round($pl->received_qty + $paid + $free, 3)]);
                }
            }
            $grn->update(['total' => round($total, 2)]);

            if ($po) {
                $po->load('lines');
                $done = $po->lines->every(fn ($l) => $l->received_qty + 0.0005 >= $l->ordered_qty);
                $this->bump($po, ['status' => $done ? 'received' : 'partial']);
            }
            return $grn;
        });
        AuditLogger::log('goods_received', "{$grn->number}: " . count($rows) . ' item(s), ' . money($grn->total) . ($po ? " on {$po->number}" : ''), 'purchasing');
        if ($po) {
            DocumentSync::share('purchase_order', $po);
        }
        return $grn->fresh('lines');
    }

    // ------------------------------------------------------------------ returns

    public function returnToSupplier(array $data): SupplierReturn
    {
        $rows = $this->lines($data['lines'] ?? []);
        if (!$rows) {
            throw ValidationException::withMessages(['lines' => 'Add at least one product with a quantity.']);
        }
        $location = !empty($data['location_id']) ? (int) $data['location_id'] : $this->stock->defaultLocation()->id;
        $ret = DB::transaction(function () use ($data, $rows, $location) {
            $ret = SupplierReturn::create([
                'number' => DocumentNumber::next('supplier_return'),
                'stock_location_id' => $location,
                'supplier_id' => (int) $data['supplier_id'],
                'goods_receipt_id' => $data['goods_receipt_id'] ?? null,
                'reason' => $data['reason'],
                'credit_note' => $data['credit_note'] ?? null,
                'created_by' => auth()->id(),
            ]);
            $total = 0;
            foreach ($rows as $r) {
                SupplierReturnLine::create(['supplier_return_id' => $ret->id, 'product_id' => $r['product_id'], 'product_variation_id' => $r['product_variation_id'],
                    'product_unit_id' => $r['product_unit_id'], 'qty_per_unit' => $r['qty_per_unit'], 'quantity' => $r['qty'], 'unit_cost' => $r['unit_cost']]);
                $this->stock->record(['product_id' => $r['product_id'], 'variation_id' => $r['product_variation_id'], 'location_id' => $location,
                    'direction' => 'out', 'type' => 'out', 'quantity' => round($r['qty'] * $r['qty_per_unit'], 3),
                    'unit_cost' => round($r['unit_cost'] / $r['qty_per_unit'], 4), 'reference_type' => 'return',
                    'reference_number' => $ret->number, 'reason' => 'Returned to supplier: ' . $data['reason']]);
                $total += $r['qty'] * $r['unit_cost'];
            }
            $ret->update(['total' => round($total, 2)]);
            return $ret;
        });
        AuditLogger::log('supplier_return', "{$ret->number}: returned " . money($ret->total) . ' to ' . ($ret->supplier->name ?? 'supplier') . " – {$ret->reason}", 'purchasing');
        return $ret->fresh('lines');
    }
}
