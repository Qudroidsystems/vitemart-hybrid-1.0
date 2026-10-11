<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\StockAdjustment;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * All dashboard numbers, computed in SQL.
 *
 * A "sale" is any order that was not cancelled and is either a till sale or a paid /
 * delivered online order. Profit = sale totals − cost of the items sold (cost is
 * snapshotted on every new till sale; older lines fall back to today's cost price).
 */
class DashboardService
{
    public array $periods = [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        'month' => 'This month',
        'last_month' => 'Last month',
        'year' => 'This year',
    ];

    public function range(string $period, ?string $from = null, ?string $to = null): array
    {
        $now = now();
        [$start, $end] = match ($period) {
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            '30d' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfDay()],
            'custom' => [Carbon::parse($from ?: $now)->startOfDay(), Carbon::parse($to ?: $now)->endOfDay()],
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };
        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }
        $seconds = (int) abs($start->diffInSeconds($end)) + 1;
        $prevEnd = $start->copy()->subSecond();
        $prevStart = $prevEnd->copy()->subSeconds($seconds - 1);

        return [$start, $end, $prevStart, $prevEnd];
    }

    /** Base query for orders that count as sales. */
    protected function sales(Carbon $start, Carbon $end, string $alias = 'o')
    {
        $hasChannel = Schema::hasColumn('orders', 'channel');
        return DB::table("orders as $alias")
            ->whereBetween("$alias.order_date", [$start, $end])
            ->whereNotIn("$alias.status", ['cancelled', 'failed'])
            ->where(function ($q) use ($alias, $hasChannel) {
                if ($hasChannel) {
                    $q->where("$alias.channel", 'pos');
                }
                $q->orWhere("$alias.id", 'like', 'POS-%')
                  ->orWhereIn("$alias.payment_status", ['paid', 'partial', 'partially_refunded'])
                  ->orWhereIn("$alias.status", ['completed', 'delivered']);
            });
    }

    protected function costSql(): string
    {
        // Per order item: snapshot cost × qty sold, else current product cost × base qty.
        return 'COALESCE(oi.cost_price * oi.quantity, p.cost_price * COALESCE(oi.base_quantity, oi.quantity), 0)';
    }

    public function kpis(Carbon $start, Carbon $end): array
    {
        $o = $this->sales($start, $end)->selectRaw('COUNT(*) c, COALESCE(SUM(o.total_amount),0) revenue, COALESCE(SUM(o.discount_amount),0) discounts')->first();

        $items = DB::table('order_items as oi')
            ->joinSub($this->sales($start, $end)->select('o.id'), 's', 's.id', '=', 'oi.order_id')
            ->leftJoin('products as p', 'p.id', '=', 'oi.product_id')
            ->selectRaw('COALESCE(SUM(COALESCE(oi.base_quantity, oi.quantity)),0) qty, COALESCE(SUM(' . $this->costSql() . '),0) cost')
            ->first();

        $refunds = (float) DB::table('refunds')->whereBetween('created_at', [$start, $end])->where('status', 'processed')->sum('amount');
        $voided = DB::table('orders')->whereBetween('order_date', [$start, $end])->where('status', 'cancelled')->count();
        $customers = DB::table('customers')->whereBetween('created_at', [$start, $end])->count();

        $revenue = (float) $o->revenue;
        $cost = (float) $items->cost;
        $profit = $revenue - $cost - $refunds * ($revenue > 0 ? ($revenue - $cost) / $revenue : 0);

        return [
            'revenue' => round($revenue, 2),
            'orders' => (int) $o->c,
            'basket' => $o->c ? round($revenue / $o->c, 2) : 0,
            'items' => round((float) $items->qty, 2),
            'cost' => round($cost, 2),
            'profit' => round($profit, 2),
            'margin' => $revenue > 0 ? round($profit / $revenue * 100, 1) : 0,
            'discounts' => round((float) $o->discounts, 2),
            'refunds' => round($refunds, 2),
            'net' => round($revenue - $refunds, 2),
            'voided' => $voided,
            'new_customers' => $customers,
        ];
    }

    public function change(array $now, array $prev): array
    {
        $out = [];
        foreach ($now as $k => $v) {
            $p = $prev[$k] ?? 0;
            $out[$k] = $p == 0 ? ($v == 0 ? 0 : null) : round(($v - $p) / abs($p) * 100, 1);
        }
        return $out;
    }

    /** Revenue & profit per day (or per hour when the range is one day). */
    public function trend(Carbon $start, Carbon $end): array
    {
        $hourly = $start->isSameDay($end);
        $bucket = $hourly ? 'HOUR(o.order_date)' : 'DATE(o.order_date)';

        $rev = $this->sales($start, $end)->selectRaw("$bucket b, SUM(o.total_amount) revenue, COUNT(*) c")->groupBy('b')->get()->keyBy('b');
        $cost = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->joinSub($this->sales($start, $end, 'o2')->select('o2.id'), 's', 's.id', '=', 'oi.order_id')
            ->leftJoin('products as p', 'p.id', '=', 'oi.product_id')
            ->selectRaw("$bucket b, SUM(" . $this->costSql() . ') cost')->groupBy('b')->get()->keyBy('b');

        $labels = $revenue = $profit = $orders = [];
        if ($hourly) {
            $first = 6;
            $last = max(22, (int) now()->format('G'));
            for ($h = min($first, (int) ($rev->keys()->min() ?? $first)); $h <= max($last, (int) ($rev->keys()->max() ?? $last)); $h++) {
                $labels[] = sprintf('%02d:00', $h);
                $r = (float) ($rev[$h]->revenue ?? 0);
                $revenue[] = round($r, 2);
                $profit[] = round($r - (float) ($cost[$h]->cost ?? 0), 2);
                $orders[] = (int) ($rev[$h]->c ?? 0);
            }
        } else {
            foreach (CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->startOfDay()) as $d) {
                $k = $d->toDateString();
                $labels[] = $d->format($start->diffInDays($end) > 60 ? 'd M y' : 'd M');
                $r = (float) ($rev[$k]->revenue ?? 0);
                $revenue[] = round($r, 2);
                $profit[] = round($r - (float) ($cost[$k]->cost ?? 0), 2);
                $orders[] = (int) ($rev[$k]->c ?? 0);
            }
        }

        return compact('labels', 'revenue', 'profit', 'orders', 'hourly');
    }

    public function paymentMix(Carbon $start, Carbon $end): array
    {
        $rows = collect();
        if (Schema::hasTable('order_payments')) {
            $rows = DB::table('order_payments as op')
                ->joinSub($this->sales($start, $end)->select('o.id'), 's', 's.id', '=', 'op.order_id')
                ->selectRaw('op.method m, SUM(op.amount) t')->groupBy('m')->get();
        }
        // Older orders without payment rows
        $covered = Schema::hasTable('order_payments') ? DB::table('order_payments')->select('order_id') : null;
        $legacy = $this->sales($start, $end)
            ->when($covered, fn ($q) => $q->whereNotIn('o.id', $covered))
            ->selectRaw('o.payment_method m, SUM(o.total_amount) t')->groupBy('m')->get();

        $mix = [];
        foreach ($rows->concat($legacy) as $r) {
            $label = match (strtolower((string) $r->m)) {
                'cash' => 'Cash', 'card' => 'Card / POS', 'transfer' => 'Transfer', 'credit' => 'Credit',
                'paystack' => 'Paystack (online)', 'cash on delivery' => 'Cash on delivery',
                default => ucfirst((string) $r->m ?: 'Other'),
            };
            $mix[$label] = round(($mix[$label] ?? 0) + (float) $r->t, 2);
        }
        arsort($mix);
        return $mix;
    }

    public function topProducts(Carbon $start, Carbon $end, int $limit = 10): array
    {
        return DB::table('order_items as oi')
            ->joinSub($this->sales($start, $end)->select('o.id'), 's', 's.id', '=', 'oi.order_id')
            ->leftJoin('products as p', 'p.id', '=', 'oi.product_id')
            ->selectRaw('oi.product_id, COALESCE(p.title, MAX(oi.title)) title, SUM(COALESCE(oi.base_quantity, oi.quantity)) qty, SUM(oi.total_price) revenue, SUM(oi.total_price - ' . $this->costSql() . ') profit')
            ->groupBy('oi.product_id', 'p.title')
            ->orderByDesc('revenue')->limit($limit)->get()->map(fn ($r) => (array) $r)->all();
    }

    public function categories(Carbon $start, Carbon $end, int $limit = 8): array
    {
        return DB::table('order_items as oi')
            ->joinSub($this->sales($start, $end)->select('o.id'), 's', 's.id', '=', 'oi.order_id')
            ->leftJoin('products as p', 'p.id', '=', 'oi.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->selectRaw("COALESCE(c.name, 'Uncategorised') name, SUM(oi.total_price) revenue")
            ->groupBy('name')->orderByDesc('revenue')->limit($limit)->get()->map(fn ($r) => (array) $r)->all();
    }

    public function cashiers(Carbon $start, Carbon $end): array
    {
        return $this->sales($start, $end)
            ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
            ->when(Schema::hasColumn('orders', 'channel'), fn ($q) => $q->where('o.channel', 'pos'))
            ->selectRaw("o.user_id, CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) name, COUNT(*) orders, SUM(o.total_amount) revenue, AVG(o.total_amount) basket, SUM(COALESCE(o.discount_amount,0)) discounts")
            ->groupBy('o.user_id', 'u.first_name', 'u.last_name')->orderByDesc('revenue')->limit(10)->get()
            ->map(function ($r) use ($start, $end) {
                $r = (array) $r;
                $r['voids'] = DB::table('orders')->where('user_id', $r['user_id'])->where('status', 'cancelled')
                    ->whereBetween('order_date', [$start, $end])->count();
                return $r;
            })->all();
    }

    public function inventory(): array
    {
        $v = Product::query()->selectRaw('
            SUM(CASE WHEN stock > 0 THEN stock * COALESCE(cost_price,0) ELSE 0 END) cost_value,
            SUM(CASE WHEN stock > 0 THEN stock * COALESCE(NULLIF(sale_price,0), price) ELSE 0 END) retail_value,
            SUM(CASE WHEN stock <= 0 AND is_active = 1 THEN 1 ELSE 0 END) out_count,
            SUM(CASE WHEN stock > 0 AND stock <= reorder_level AND is_active = 1 THEN 1 ELSE 0 END) low_count
        ')->first();

        $low = Product::query()->where('is_active', true)->where('track_stock', true)
            ->whereColumn('stock', '<=', 'reorder_level')
            ->orderByRaw('stock / NULLIF(reorder_level, 0)')->orderBy('stock')
            ->limit(8)->get(['id', 'title', 'stock', 'reorder_level']);

        $expiring = collect();
        if (Schema::hasColumn('stocks', 'expiry_date')) {
            $expiring = DB::table('stocks as s')->join('products as p', 'p.id', '=', 's.product_id')
                ->whereNull('s.deleted_at')->whereIn('s.type', ['in', 'adjustment'])
                ->whereBetween('s.expiry_date', [today(), today()->addDays(30)])
                ->selectRaw('p.id, p.title, MIN(s.expiry_date) expiry, SUM(s.quantity) qty')
                ->groupBy('p.id', 'p.title')->orderBy('expiry')->limit(6)->get();
        }

        return [
            'cost_value' => round((float) $v->cost_value, 2),
            'retail_value' => round((float) $v->retail_value, 2),
            'out_count' => (int) $v->out_count,
            'low_count' => (int) $v->low_count,
            'low' => $low,
            'expiring' => $expiring,
            'pending_adjustments' => Schema::hasTable('stock_adjustments') ? StockAdjustment::where('status', 'pending')->count() : 0,
        ];
    }

    public function recentSales(int $limit = 8)
    {
        return DB::table('orders as o')->leftJoin('users as u', 'u.id', '=', 'o.user_id')
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
            ->orderByDesc('o.order_date')->limit($limit)
            ->get(['o.id', 'o.total_amount', 'o.order_date', 'o.status', 'o.payment_method', 'u.first_name as cashier', 'c.first_name as customer']);
    }

    public function security(): array
    {
        $lifetime = (int) config('session.lifetime', 120);
        $online = DB::table('sessions')->whereNotNull('user_id')
            ->where('last_activity', '>=', now()->subMinutes(min($lifetime, 30))->timestamp)
            ->distinct()->count('user_id');

        $hasAudit = Schema::hasTable('audit_logs');
        return [
            'online' => $online,
            'failed_logins' => $hasAudit ? AuditLog::where('event', 'login_failed')->where('created_at', '>=', today())->count() : 0,
            'events' => $hasAudit ? AuditLog::latest('created_at')->limit(6)->get() : collect(),
        ];
    }
}
