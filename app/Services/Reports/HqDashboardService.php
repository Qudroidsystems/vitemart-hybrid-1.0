<?php

namespace App\Services\Reports;

use App\Models\Branch;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Head office: every branch side by side.
 *
 * Branch figures are only as recent as that branch's last sync, so every number comes with
 * a freshness state:  live (synced ≤ 15 min ago) · behind (≤ 2 h) · stale (older / never).
 */
class HqDashboardService
{
    public const FRESH_MINUTES = 15;
    public const STALE_MINUTES = 120;

    /** Branches to report on (the head-office warehouse included only if it sells). */
    public function branches(?array $ids = null): Collection
    {
        return Branch::with('activeInstallation')
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('number')->get();
    }

    /** live | behind | stale | local, with the time the figures are good up to. */
    public function freshness(Branch $b): array
    {
        $local = \App\Services\MultiBranch\Install::branchId() === $b->id;
        if ($local) {
            return ['state' => 'local', 'label' => 'This server', 'as_of' => now(), 'minutes' => 0];
        }
        $inst = $b->activeInstallation;
        $at = $inst?->last_push_at ?? $inst?->last_seen_at;
        if (!$at) {
            return ['state' => 'stale', 'label' => 'Never synced', 'as_of' => null, 'minutes' => null];
        }
        $mins = (int) $at->diffInMinutes(now(), true);
        $state = $mins <= self::FRESH_MINUTES ? 'live' : ($mins <= self::STALE_MINUTES ? 'behind' : 'stale');
        $label = ['live' => 'Up to date', 'behind' => 'Behind', 'stale' => 'Out of date'][$state];
        return ['state' => $state, 'label' => $label, 'as_of' => $at, 'minutes' => $mins];
    }

    protected function sales(Carbon $start, Carbon $end, array $branchIds, string $a = 'o')
    {
        return DB::table("orders as $a")
            ->whereBetween("$a.order_date", [$start, $end])
            ->whereNotIn("$a.status", ['cancelled', 'failed'])
            ->whereIn("$a.branch_id", $branchIds);
    }

    /** One row per branch with the comparison figures. */
    public function comparison(Carbon $start, Carbon $end, Collection $branches): array
    {
        $ids = $branches->pluck('id')->all();
        $sales = $this->sales($start, $end, $ids)
            ->selectRaw('o.branch_id, COUNT(*) orders, COALESCE(SUM(o.total_amount),0) revenue, COALESCE(SUM(o.cost_total),0) cost,
                COALESCE(SUM(o.discount_amount),0) discounts')
            ->groupBy('o.branch_id')->get()->keyBy('branch_id');
        $voids = DB::table('orders')->whereBetween('order_date', [$start, $end])->where('status', 'cancelled')->whereIn('branch_id', $ids)
            ->selectRaw('branch_id, COUNT(*) c')->groupBy('branch_id')->pluck('c', 'branch_id');
        $refunds = Schema::hasTable('refunds')
            ? DB::table('refunds')->whereBetween('created_at', [$start, $end])->whereIn('branch_id', $ids)
                ->selectRaw('branch_id, COALESCE(SUM(amount),0) t')->groupBy('branch_id')->pluck('t', 'branch_id')
            : collect();
        $stock = $this->stockByBranch($ids);
        $transfers = Schema::hasTable('stock_transfers') ? $this->openTransfers($ids) : [];

        $rows = [];
        foreach ($branches as $b) {
            $s = $sales[$b->id] ?? null;
            $rev = (float) ($s->revenue ?? 0);
            $cost = (float) ($s->cost ?? 0);
            $orders = (int) ($s->orders ?? 0);
            $rows[] = [
                'branch' => $b,
                'fresh' => $this->freshness($b),
                'orders' => $orders,
                'revenue' => round($rev, 2),
                'basket' => $orders ? round($rev / $orders, 2) : 0,
                'profit' => round($rev - $cost, 2),
                'margin' => $rev > 0 ? round(($rev - $cost) / $rev * 100, 1) : null,
                'discounts' => round((float) ($s->discounts ?? 0), 2),
                'refunds' => round((float) ($refunds[$b->id] ?? 0), 2),
                'voids' => (int) ($voids[$b->id] ?? 0),
                'stock_value' => $stock[$b->id]['value'] ?? 0,
                'low_stock' => $stock[$b->id]['low'] ?? 0,
                'out_of_stock' => $stock[$b->id]['out'] ?? 0,
                'transfers_in' => $transfers[$b->id]['in'] ?? 0,
                'transfers_out' => $transfers[$b->id]['out'] ?? 0,
                'transfer_issues' => $transfers[$b->id]['issues'] ?? 0,
            ];
        }
        return $rows;
    }

    /** Stock value at cost, low / out of stock counts per branch (from the synced balances). */
    public function stockByBranch(array $ids): array
    {
        if (!Schema::hasTable('inventory_balances')) {
            return [];
        }
        $rows = DB::table('inventory_balances as b')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->leftJoin('product_variations as v', fn ($j) => $j->on('v.id', '=', 'b.variation_key')->where('b.variation_key', '>', 0))
            ->whereIn('b.branch_id', $ids)
            ->where(fn ($q) => $q->whereNull('p.track_stock')->orWhere('p.track_stock', true))
            ->selectRaw('b.branch_id,
                COALESCE(SUM(GREATEST(b.on_hand, 0) * COALESCE(v.cost_price, p.cost_price, 0)), 0) value,
                SUM(CASE WHEN b.on_hand <= 0 THEN 1 ELSE 0 END) out_cnt,
                SUM(CASE WHEN b.on_hand > 0 AND b.on_hand <= COALESCE(p.reorder_level, 0) THEN 1 ELSE 0 END) low_cnt')
            ->groupBy('b.branch_id')->get();
        $out = [];
        foreach ($rows as $r) {
            $out[$r->branch_id] = ['value' => round((float) $r->value, 2), 'low' => (int) $r->low_cnt, 'out' => (int) $r->out_cnt];
        }
        return $out;
    }

    protected function openTransfers(array $ids): array
    {
        $cols = Schema::getColumnListing('stock_transfers');
        $from = in_array('from_branch_id', $cols, true) ? 'from_branch_id' : 'source_branch_id';
        $to = in_array('to_branch_id', $cols, true) ? 'to_branch_id' : 'dest_branch_id';
        $open = ['requested', 'approved', 'dispatched'];
        $out = [];
        foreach (DB::table('stock_transfers')->whereIn('status', $open)->selectRaw("$from f, $to t, COUNT(*) c")->groupBy($from, $to)->get() as $r) {
            $out[$r->t]['in'] = ($out[$r->t]['in'] ?? 0) + $r->c;
            $out[$r->f]['out'] = ($out[$r->f]['out'] ?? 0) + $r->c;
        }
        foreach (DB::table('stock_transfers')->where('status', 'received')->where('has_discrepancy', true)->selectRaw("$to t, COUNT(*) c")->groupBy($to)->get() as $r) {
            $out[$r->t]['issues'] = ($out[$r->t]['issues'] ?? 0) + $r->c;
        }
        return $out;
    }

    /** Daily revenue per branch. */
    public function trend(Carbon $start, Carbon $end, Collection $branches): array
    {
        $days = [];
        for ($d = $start->copy()->startOfDay(); $d <= $end; $d->addDay()) {
            $days[] = $d->toDateString();
            if (count($days) > 120) {
                break;
            }
        }
        $raw = $this->sales($start, $end, $branches->pluck('id')->all())
            ->selectRaw('o.branch_id, DATE(o.order_date) d, COALESCE(SUM(o.total_amount),0) t')
            ->groupBy('o.branch_id', DB::raw('DATE(o.order_date)'))->get();
        $series = [];
        foreach ($branches as $b) {
            $by = $raw->where('branch_id', $b->id)->pluck('t', 'd');
            $series[] = ['code' => $b->code, 'name' => $b->name, 'values' => array_map(fn ($d) => round((float) ($by[$d] ?? 0), 2), $days)];
        }
        return ['labels' => $days, 'series' => $series];
    }

    /** Best sellers across the selected branches, with each branch's share. */
    public function topProducts(Carbon $start, Carbon $end, Collection $branches, int $limit = 10): array
    {
        $ids = $branches->pluck('id')->all();
        $rows = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin('products as p', 'p.id', '=', 'oi.product_id')
            ->whereBetween('o.order_date', [$start, $end])->whereNotIn('o.status', ['cancelled', 'failed'])->whereIn('o.branch_id', $ids)
            ->selectRaw('oi.product_id, COALESCE(p.title, MAX(oi.title)) title, o.branch_id,
                SUM(COALESCE(oi.base_quantity, oi.quantity)) qty, SUM(oi.total_price) revenue')
            ->groupBy('oi.product_id', 'p.title', 'o.branch_id')->get();
        return $rows->groupBy('product_id')->map(function ($g) use ($branches) {
            return [
                'title' => $g->first()->title,
                'qty' => round((float) $g->sum('qty'), 3),
                'revenue' => round((float) $g->sum('revenue'), 2),
                'by_branch' => $branches->mapWithKeys(fn ($b) => [$b->code => round((float) $g->where('branch_id', $b->id)->sum('revenue'), 2)])->all(),
            ];
        })->sortByDesc('revenue')->take($limit)->values()->all();
    }

    public function paymentMix(Carbon $start, Carbon $end, Collection $branches): array
    {
        $rows = DB::table('order_payments as op')->join('orders as o', 'o.id', '=', 'op.order_id')
            ->whereBetween('o.order_date', [$start, $end])->whereNotIn('o.status', ['cancelled', 'failed'])
            ->whereIn('o.branch_id', $branches->pluck('id')->all())
            ->when(Schema::hasColumn('order_payments', 'status'), fn ($q) => $q->where('op.status', '!=', 'failed'))
            ->selectRaw('o.branch_id, op.method, COALESCE(SUM(op.amount),0) t')->groupBy('o.branch_id', 'op.method')->get();
        $out = [];
        foreach ($branches as $b) {
            $out[$b->code] = $rows->where('branch_id', $b->id)->pluck('t', 'method')->map(fn ($v) => round((float) $v, 2))->all();
        }
        return $out;
    }

    /** Totals across branches, plus how many branches the totals are missing recent data for. */
    public function totals(array $rows): array
    {
        $c = collect($rows);
        $rev = $c->sum('revenue');
        $orders = $c->sum('orders');
        return [
            'revenue' => round($rev, 2),
            'orders' => $orders,
            'basket' => $orders ? round($rev / $orders, 2) : 0,
            'profit' => round($c->sum('profit'), 2),
            'margin' => $rev > 0 ? round($c->sum('profit') / $rev * 100, 1) : null,
            'stock_value' => round($c->sum('stock_value'), 2),
            'low_stock' => $c->sum('low_stock'),
            'not_fresh' => $c->filter(fn ($r) => in_array($r['fresh']['state'], ['behind', 'stale'], true))->count(),
        ];
    }
}
