<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Maker–checker workflow for stock changes:
 *   create (pending) → approve (posts to the ledger) | reject | cancel
 *
 * The quantity change is worked out when the adjustment is created (so a stock
 * count compares against the balance at the moment of counting) and is posted as
 * a delta on approval – sales made in between are not lost.
 */
class StockAdjustmentService
{
    public function __construct(protected StockService $stock)
    {
    }

    public function needsApproval(?User $user): bool
    {
        if (!SystemSetting::get('adjustment_requires_approval', true)) {
            return false;
        }
        return !($user && $user->can('Approve stock adjustment'));
    }

    /**
     * @param array $data  type, location_id, reason, notes, supplier, supplier_invoice,
     *                     items[]: product_id, variation_id, pack_id, mode(add|remove|set), quantity, unit_cost, reason, expiry_date, batch_number
     * @param string $action draft|submit|approve
     */
    public function create(array $data, string $action = 'submit'): StockAdjustment
    {
        $user = auth()->user();
        $type = $data['type'] ?? 'adjustment';
        $locationId = (int) ($data['location_id'] ?? $this->stock->defaultLocation()->id);

        if (empty($data['items'])) {
            throw ValidationException::withMessages(['items' => 'Add at least one product.']);
        }

        $adjustment = DB::transaction(function () use ($data, $type, $locationId, $user, $action) {
            $adj = StockAdjustment::create([
                'reference' => StockAdjustment::nextReference($type),
                'type' => $type,
                'status' => $action === 'draft' ? 'draft' : 'pending',
                'stock_location_id' => $locationId,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'supplier' => $data['supplier'] ?? null,
                'supplier_invoice' => $data['supplier_invoice'] ?? null,
                'created_by' => $user?->id,
            ]);

            $value = 0;
            $count = 0;
            foreach ($data['items'] as $row) {
                $product = Product::find($row['product_id'] ?? null);
                if (!$product) {
                    continue;
                }
                $variationId = !empty($row['variation_id']) ? (int) $row['variation_id'] : null;
                $pack = !empty($row['pack_id']) ? ProductUnit::where('product_id', $product->id)->find($row['pack_id']) : null;
                $per = $pack ? max(0.001, (float) $pack->quantity_per_unit) : 1.0;

                $mode = $row['mode'] ?? match ($type) {
                    'count' => 'set',
                    'receive' => 'add',
                    'damage', 'expired' => 'remove',
                    default => 'add',
                };
                $entered = (float) ($row['quantity'] ?? 0);
                $baseQty = round($entered * $per, 3);
                $system = $this->stock->balance($product->id, $variationId, $locationId);

                $change = match ($mode) {
                    'set' => round($baseQty - $system, 3),
                    'remove' => -abs($baseQty),
                    default => abs($baseQty),
                };

                if ($mode !== 'set' && $baseQty <= 0) {
                    continue;
                }

                $unitCost = isset($row['unit_cost']) && $row['unit_cost'] !== ''
                    ? (float) $row['unit_cost'] / $per   // cost entered per pack → per base unit
                    : ($variationId ? \App\Models\ProductVariation::find($variationId)?->effective_cost : (float) $product->cost_price);

                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adj->id,
                    'product_id' => $product->id,
                    'product_variation_id' => $variationId,
                    'product_unit_id' => $pack?->id,
                    'quantity_per_unit' => $per,
                    'mode' => $mode,
                    'entered_quantity' => $entered,
                    'system_quantity' => $system,
                    'quantity_change' => $change,
                    'unit_cost' => $unitCost,
                    'reason' => $row['reason'] ?? null,
                    'expiry_date' => $row['expiry_date'] ?? null,
                    'batch_number' => $row['batch_number'] ?? null,
                ]);

                $value += $change * (float) $unitCost;
                $count++;
            }

            if ($count === 0) {
                throw ValidationException::withMessages(['items' => 'Enter a quantity for at least one product.']);
            }

            $adj->update(['items_count' => $count, 'total_value_change' => round($value, 2)]);
            return $adj;
        });

        AuditLogger::log('created', "{$adjustment->type_label} {$adjustment->reference} created ({$adjustment->items_count} items)", 'inventory', $adjustment);

        if ($action === 'approve' || ($action === 'submit' && !$this->needsApproval($user))) {
            $this->approve($adjustment->fresh());
        }

        return $adjustment->fresh(['items']);
    }

    public function approve(StockAdjustment $adj): StockAdjustment
    {
        if (!in_array($adj->status, ['pending', 'draft'], true)) {
            throw new \RuntimeException("This adjustment is already {$adj->status}.");
        }

        DB::transaction(function () use ($adj) {
            $adj->load('items.product');
            foreach ($adj->items as $item) {
                if (abs($item->quantity_change) < 0.0005) {
                    $item->update(['balance_after' => $this->stock->balance($item->product_id, $item->product_variation_id, $adj->stock_location_id)]);
                    continue;
                }

                $refType = match ($adj->type) {
                    'receive' => 'purchase',
                    'damage', 'expired' => 'damage',
                    default => 'adjustment',
                };

                $direction = $item->quantity_change > 0 ? 'in' : 'out';
                $ledgerType = $direction === 'in' ? 'in' : (in_array($adj->type, ['damage', 'expired']) ? 'damage' : 'out');

                $row = $this->stock->record([
                    'product_id' => $item->product_id,
                    'variation_id' => $item->product_variation_id,
                    'location_id' => $adj->stock_location_id,
                    'direction' => $direction,
                    'type' => $ledgerType,
                    'quantity' => abs($item->quantity_change),
                    'unit_cost' => $item->unit_cost,
                    'reference_type' => $refType,
                    'reference_number' => $adj->reference,
                    'reason' => $item->reason ?: $adj->reason,
                    'notes' => $adj->notes,
                    'expiry_date' => $item->expiry_date,
                    'batch_number' => $item->batch_number,
                    // Counts must post even if sales happened after counting.
                    'allow_negative' => $adj->type === 'count' ? true : null,
                ]);
                $item->update(['balance_after' => $row->new_quantity]);

                // Goods received at a new cost → update product cost price.
                if ($adj->type === 'receive' && $item->unit_cost && !$item->product_variation_id
                    && abs((float) $item->unit_cost - (float) $item->product->cost_price) >= 0.01) {
                    app(PricingService::class)->setPrice($item->product, 'cost_price', round((float) $item->unit_cost, 2), 'receive', "GRN {$adj->reference}");
                }
            }

            $adj->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        });

        AuditLogger::log('approved', "{$adj->type_label} {$adj->reference} approved and posted to stock", 'inventory', $adj);

        return $adj->fresh();
    }

    public function reject(StockAdjustment $adj, ?string $reason): StockAdjustment
    {
        if (!in_array($adj->status, ['pending', 'draft'], true)) {
            throw new \RuntimeException("This adjustment is already {$adj->status}.");
        }
        $adj->update([
            'status' => 'rejected', 'rejected_by' => auth()->id(), 'rejected_at' => now(), 'rejection_reason' => $reason,
        ]);
        AuditLogger::log('rejected', "{$adj->type_label} {$adj->reference} rejected" . ($reason ? ": $reason" : ''), 'inventory', $adj);
        return $adj;
    }
}
