<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use App\Services\MultiBranch\DocumentNumber;
use App\Services\StockAdjustmentService;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock-takes.  Counters see only the products, not what the system expects ("blind"), so they count
 * what is really there.  On submit the system quantity is taken at that moment; big differences are sent
 * back for a recount first; the result becomes a stock-count adjustment that a supervisor approves.
 */
class CountService
{
    public function __construct(protected StockService $stock, protected StockAdjustmentService $adjustments)
    {
    }

    /**
     * scope: all | category | selected | in_stock
     */
    public function create(array $data): StockCount
    {
        $location = (int) ($data['location_id'] ?? $this->stock->defaultLocation()->id);
        $q = Product::query()->where('is_active', true)->where(fn ($w) => $w->where('track_stock', true)->orWhereNull('track_stock'))
            ->with(['variations' => fn ($v) => $v->where('is_active', true)]);
        switch ($data['scope'] ?? 'all') {
            case 'category':
                $q->where('category_id', $data['category_id'] ?? 0);
                break;
            case 'selected':
                $q->whereIn('id', (array) ($data['product_ids'] ?? []));
                break;
            case 'in_stock':
                $q->where('stock', '!=', 0);
                break;
        }
        $products = $q->orderBy('title')->get();
        if ($products->isEmpty()) {
            throw ValidationException::withMessages(['scope' => 'No products match – choose another category or add products.']);
        }

        $count = DB::transaction(function () use ($data, $location, $products) {
            $count = StockCount::create([
                'number' => DocumentNumber::next('count'),
                'stock_location_id' => $location,
                'title' => $data['title'] ?? ('Stock count ' . now()->format('d M Y')),
                'status' => 'open',
                'blind' => (bool) ($data['blind'] ?? true),
                'recount_threshold' => $data['recount_threshold'] ?? SystemSetting::get('count_recount_threshold', 10),
                'created_by' => auth()->id(),
                'notes' => $data['notes'] ?? null,
            ]);
            foreach ($products as $p) {
                if ($p->product_type === 'variable' && $p->variations->isNotEmpty()) {
                    foreach ($p->variations as $v) {
                        StockCountLine::create(['stock_count_id' => $count->id, 'product_id' => $p->id, 'product_variation_id' => $v->id]);
                    }
                } else {
                    StockCountLine::create(['stock_count_id' => $count->id, 'product_id' => $p->id]);
                }
            }
            return $count;
        });
        AuditLogger::log('count_started', "Stock count {$count->number} started ({$count->lines()->count()} lines)", 'inventory');
        return $count;
    }

    /** Save counts as they are entered (several people can count the same sheet). */
    public function record(StockCount $count, array $entries): int
    {
        if ($count->status !== 'open') {
            throw ValidationException::withMessages(['status' => 'This count has already been submitted.']);
        }
        $n = 0;
        foreach ($entries as $e) {
            $line = StockCountLine::where('stock_count_id', $count->id)->find($e['line_id'] ?? 0);
            if (!$line) {
                continue;
            }
            $val = $e['counted_qty'];
            $line->update([
                'counted_qty' => $val === null || $val === '' ? null : round(max(0, (float) $val), 3),
                'counted_by' => auth()->id(),
                'counted_at' => now(),
                'recount' => $val === null || $val === '' ? $line->recount : false,
            ]);
            $n++;
        }
        return $n;
    }

    /**
     * @return array{status: string, count: StockCount, recount?: int}
     */
    public function submit(StockCount $count, bool $uncountedAsZero = false, bool $skipRecount = false): array
    {
        if ($count->status !== 'open') {
            throw ValidationException::withMessages(['status' => 'This count has already been submitted.']);
        }
        $lines = $count->lines()->with('product')->get();
        if ($uncountedAsZero) {
            $lines->whereNull('counted_qty')->each(fn ($l) => $l->update(['counted_qty' => 0]));
        }
        $counted = $lines->filter(fn ($l) => $l->counted_qty !== null);
        if ($counted->isEmpty()) {
            throw ValidationException::withMessages(['lines' => 'Nothing has been counted yet.']);
        }

        // Large differences → ask for a recount (once) before posting
        $threshold = (float) ($count->recount_threshold ?? 0);
        if (!$skipRecount && $threshold > 0) {
            $flag = [];
            foreach ($counted as $l) {
                if ($l->recount || $l->counted_at === null) {
                    continue;
                }
                $sys = $this->stock->balance($l->product_id, $l->product_variation_id, $count->stock_location_id);
                $diff = abs($l->counted_qty - $sys);
                $pct = $sys != 0 ? $diff / abs($sys) * 100 : ($diff > 0 ? 100 : 0);
                if ($pct > $threshold && $diff >= 1 && !$l->recounted) {
                    $flag[] = $l->id;
                }
            }
            if ($flag) {
                StockCountLine::whereIn('id', $flag)->update(['recount' => true, 'recounted' => true, 'counted_qty' => null, 'counted_at' => null]);
                AuditLogger::log('count_recount', "Stock count {$count->number}: " . count($flag) . ' line(s) sent back for a recount', 'inventory');
                return ['status' => 'recount', 'count' => $count->fresh('lines'), 'recount' => count($flag)];
            }
        }

        $adj = DB::transaction(function () use ($count, $counted) {
            $items = [];
            foreach ($counted as $l) {
                $sys = $this->stock->balance($l->product_id, $l->product_variation_id, $count->stock_location_id);
                $l->update(['system_qty' => $sys, 'variance' => round($l->counted_qty - $sys, 3), 'recount' => false]);
                $items[] = ['product_id' => $l->product_id, 'variation_id' => $l->product_variation_id, 'mode' => 'set', 'quantity' => $l->counted_qty];
            }
            $adj = $this->adjustments->create([
                'type' => 'count', 'location_id' => $count->stock_location_id,
                'reason' => "Stock count {$count->number}", 'notes' => $count->title,
                'items' => $items,
            ], 'submit');
            $count->update(['status' => 'submitted', 'stock_adjustment_id' => $adj->id, 'submitted_by' => auth()->id(), 'submitted_at' => now()]);
            return $adj;
        });
        AuditLogger::log('count_submitted', "Stock count {$count->number} submitted → {$adj->reference}", 'inventory');
        return ['status' => 'submitted', 'count' => $count->fresh('lines')];
    }

    public function cancel(StockCount $count): void
    {
        if ($count->status !== 'open') {
            throw ValidationException::withMessages(['status' => 'Only an open count can be cancelled.']);
        }
        $count->update(['status' => 'cancelled']);
        AuditLogger::log('count_cancelled', "Stock count {$count->number} cancelled", 'inventory');
    }
}
